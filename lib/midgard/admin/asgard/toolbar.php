<?php
/**
 * @package midgard.admin.asgard
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * Wrapper for Asgard's toolbar
 *
 * @package midgard.admin.asgard
 */
class midgard_admin_asgard_toolbar extends midcom_helper_toolbar
{
    private function _generate_url($action, $object)
    {
        return '__mfa/asgard/object/' . $action . '/' . $object->guid . '/';
    }

    /**
     * Populate the object toolbar
     *
     * @param mixed $object        MgdSchema object for which the toolbar will be created
     * @param String $handler_id   Initialized handler id
     * @param array $data          Local request data
     */
    public function bind_to_object($object, $handler_id, $data)
    {
        // Show view toolbar button, if the user hasn't configured to use straight the edit mode
        if ($data['default_mode'] === 'view')
        {
            $this->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => $this->_generate_url('view', $object),
                    MIDCOM_TOOLBAR_LABEL => midcom::get('i18n')->get_string('view', 'midcom'),
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/view.png',
                    MIDCOM_TOOLBAR_ACCESSKEY => 'v',
                )
            );
        }

        if (   !is_a($object, 'midcom_db_style')
            && !is_a($object, 'midcom_db_element')
            && !is_a($object, 'midcom_db_snippetdir')
            && !is_a($object, 'midcom_db_snippet')
            && !is_a($object, 'midcom_db_page')
            && !is_a($object, 'midcom_db_pageelement')
            && !is_a($object, 'midcom_db_parameter')
            && substr($object->__mgdschema_class_name__, 0, 23) != 'org_routamc_positioning'
            && substr($object->__mgdschema_class_name__, 0, 14) != 'net_nemein_tag')
        {
            $link = midcom::get('permalinks')->resolve_permalink($object->guid);
            if ($link)
            {
                $this->add_item
                (
                    array
                    (
                        MIDCOM_TOOLBAR_URL => $link,
                        MIDCOM_TOOLBAR_LABEL => midcom::get('i18n')->get_string('view on site', 'midgard.admin.asgard'),
                        MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/stock_internet.png',
                    )
                );
            }
        }

        if ($object->can_do('midgard:update'))
        {
            $this->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => $this->_generate_url('edit', $object),
                    MIDCOM_TOOLBAR_LABEL => midcom::get('i18n')->get_string('edit', 'midcom'),
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/edit.png',
                    MIDCOM_TOOLBAR_ACCESSKEY => 'e',
                )
            );
        }

        if ($object->can_do('midgard:create'))
        {
            if (midcom_helper_reflector_tree::get_child_objects($object))
            {
                $this->add_item
                (
                    array
                    (
                        MIDCOM_TOOLBAR_URL => $this->_generate_url('copy/tree', $object),
                        MIDCOM_TOOLBAR_LABEL => midcom::get('i18n')->get_string('copy', 'midcom'),
                        MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/editcopy.png',
                    )
                );
            }
            else
            {
                $this->add_item
                (
                    array
                    (
                        MIDCOM_TOOLBAR_URL => $this->_generate_url('copy', $object),
                        MIDCOM_TOOLBAR_LABEL => midcom::get('i18n')->get_string('copy', 'midcom'),
                        MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/editcopy.png',
                    )
                );
            }
        }

        if ($object->can_do('midgard:update'))
        {
            $this->_add_toolbar_update_items($object);
        }

        if ($object->can_do('midgard:create'))
        {
            // Find out what types of children the object can have and show create buttons for them
            $child_types = $data['tree_reflector']->get_child_classes();
            if (!is_array($child_types))
            {
                debug_add("\$data['tree_reflector']->get_child_classes() failed critically, recasting \$child_types as array", MIDCOM_LOG_WARN);
                $child_types = array();
            }
            foreach ($child_types as $type)
            {
                $display_button = true;
                if (is_a($object, 'midcom_db_topic'))
                {
                    // With topics we should check for component before populating create buttons as so many types can be children of topics
                    switch ($type)
                    {
                        case 'midgard_topic':
                        case 'midgard_article':
                            // Articles and topics can always be created
                            break;
                        default:
                            $midcom_dba_classname = midcom::get('dbclassloader')->get_midcom_class_name_for_mgdschema_object($type);
                            if (!$midcom_dba_classname)
                            {
                                $display_button = false;
                                break;
                            }
                            $component = midcom::get('dbclassloader')->get_component_for_class($type);
                            if ($component != $object->component)
                            {
                                $display_button = false;
                            }
                            break;
                    }
                }
                else if (is_a($object, 'midcom_db_article'))
                {
                    try
                    {
                        $topic = new midcom_db_topic($object->topic);
                        // With articles we should check for topic component before populating create buttons as so many types can be children of topics
                        switch ($type)
                        {
                            case 'midgard_article':
                                // Articles can always be created
                                break;
                            default:
                                $component = midcom::get('dbclassloader')->get_component_for_class($type);
                                if ($component != $topic->component)
                                {
                                    $display_button = false;
                                }
                                break;
                        }
                    }
                    catch (midcom_error $e)
                    {
                        $e->log();
                    }
                }

                if (!$display_button)
                {
                    // Skip this type
                    continue;
                }

                $this->add_item
                (
                    array
                    (
                        MIDCOM_TOOLBAR_URL => $this->_generate_url('create/' . $type, $object),
                        MIDCOM_TOOLBAR_LABEL => sprintf(midcom::get('i18n')->get_string('create %s', 'midcom'), midgard_admin_asgard_plugin::get_type_label($type)),
                        MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/' . $data['tree_reflector']->get_create_icon($type),
                    )
                );
            }
        }

        if ($object->can_do('midgard:delete'))
        {
            $this->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => $this->_generate_url('delete', $object),
                    MIDCOM_TOOLBAR_LABEL => midcom::get('i18n')->get_string('delete', 'midcom'),
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/trash.png',
                    MIDCOM_TOOLBAR_ACCESSKEY => 'd',
                )
            );
        }

        if (   midcom::get('config')->get('midcom_services_rcs_enable')
            && $object->can_do('midgard:update')
            && $object->_use_rcs)
        {
            $this->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => $this->_generate_url('rcs', $object),
                    MIDCOM_TOOLBAR_LABEL => midcom::get('i18n')->get_string('show history', 'midgard.admin.asgard'),
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/history.png',
                    MIDCOM_TOOLBAR_ENABLED => (substr($handler_id, 0, 25) !== '____mfa-asgard-object_rcs'),
                    MIDCOM_TOOLBAR_ACCESSKEY => 'h',
                )
            );
        }
        $this->_disable_active_item($handler_id, $object, $data);
    }

    private function _disable_active_item($handler_id, $object, &$data)
    {
        switch ($handler_id)
        {
            case '____mfa-asgard-object_view':
                $this->disable_item($this->_generate_url('view', $object));
                break;
            case '____mfa-asgard-object_edit':
                $this->disable_item($this->_generate_url('edit', $object));
                break;
            case '____mfa-asgard-object_copy':
                $this->disable_item($this->_generate_url('copy', $object));
                break;
            case '____mfa-asgard-object_copy_tree':
                $this->disable_item($this->_generate_url('copy/tree', $object));
                break;
            case '____mfa-asgard-components_configuration_edit_folder':
                $this->disable_item("__mfa/asgard/components/configuration/edit/{$object->component}/{$object->guid}/");
                break;
            case '____mfa-asgard-object_metadata':
                $this->disable_item($this->_generate_url('metadata', $object));
                break;
            case '____mfa-asgard-object_attachments':
            case '____mfa-asgard-object_attachments_edit':
            case '____mfa-asgard-object_attachments_delete':
                $this->disable_item($this->_generate_url('attachments', $object));
                break;
            case '____mfa-asgard-object_parameters':
                $this->disable_item($this->_generate_url('parameters', $object));
                break;
            case '____mfa-asgard-object_permissions':
                $this->disable_item($this->_generate_url('permissions', $object));
                break;
            case '____mfa-asgard-object_create':
                $this->disable_item($this->_generate_url('create/' . $data['current_type'], $object));
                break;
            case '____mfa-asgard-object_delete':
                $this->disable_item($this->_generate_url('delete', $object));
                break;
            case '____mfa-asgard_midcom.helper.replicator-object':
                $this->disable_item("__mfa/asgard_midcom.helper.replicator/object/{$object->guid}/");
                break;
        }
    }

    private function _add_toolbar_update_items($object)
    {
        if (   is_a($object, 'midcom_db_topic')
            && $object->component
            && $object->can_do('midcom:component_config'))
        {
            $this->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => "__mfa/asgard/components/configuration/edit/{$object->component}/{$object->guid}/",
                    MIDCOM_TOOLBAR_LABEL => midcom::get('i18n')->get_string('component configuration', 'midcom'),
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/stock_folder-properties.png',
                )
            );
        }

        $this->add_item
        (
            array
            (
                MIDCOM_TOOLBAR_URL => $this->_generate_url('metadata', $object),
                MIDCOM_TOOLBAR_LABEL => midcom::get('i18n')->get_string('metadata', 'midcom'),
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/metadata.png',
                MIDCOM_TOOLBAR_ACCESSKEY => 'm',
            )
        );
        midcom::get('toolbars')->add_approval_controls($this, $object);

        $this->add_item
        (
            array
            (
                MIDCOM_TOOLBAR_URL => $this->_generate_url('attachments', $object),
                MIDCOM_TOOLBAR_LABEL => midcom::get('i18n')->get_string('attachments', 'midgard.admin.asgard'),
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/attach.png',
            )
        );

        $this->add_item
        (
            array
            (
                MIDCOM_TOOLBAR_URL => $this->_generate_url('parameters', $object),
                MIDCOM_TOOLBAR_LABEL => midcom::get('i18n')->get_string('parameters', 'midcom'),
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/properties.png',
                MIDCOM_TOOLBAR_ENABLED => $object->can_do('midgard:parameters'),
            )
        );

        $this->add_item
        (
            array
            (
                MIDCOM_TOOLBAR_URL => $this->_generate_url('permissions', $object),
                MIDCOM_TOOLBAR_LABEL => midcom::get('i18n')->get_string('privileges', 'midcom'),
                MIDCOM_TOOLBAR_ICON => 'midgard.admin.asgard/permissions-16.png',
                MIDCOM_TOOLBAR_ENABLED => $object->can_do('midgard:privileges'),
            )
        );

        if (   midcom::get('componentloader')->is_installed('midcom.helper.replicator')
            && midcom::get('auth')->admin)
        {
            $this->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => "__mfa/asgard_midcom.helper.replicator/object/{$object->guid}/",
                    MIDCOM_TOOLBAR_LABEL => midcom::get('i18n')->get_string('replication information', 'midcom.helper.replicator'),
                    MIDCOM_TOOLBAR_ICON => 'midcom.helper.replicator/replicate-server-16.png',
                    MIDCOM_TOOLBAR_ACCESSKEY => 'r',
                )
            );
        }
    }
}
?>