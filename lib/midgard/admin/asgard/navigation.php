<?php
/**
 * @package midgard.admin.asgard
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Navigation class for Asgard
 *
 * @package midgard.admin.asgard
 */
class midgard_admin_asgard_navigation extends midcom_baseclasses_components_purecode
{
    /**
     * Root types
     *
     * @var string
     */
    public $root_types = array();

    /**
     * Some object
     *
     * @var midgard_object
     */
    protected $_object = null;

    /**
     * Object path to the current object.
     *
     * @var Array
     */
    private $_object_path = array();

    private $_reflectors = array();
    private $_request_data = array();
    var $expanded_root_types = array();
    var $shown_objects = array();

    public function __construct($object, &$request_data)
    {
        parent::__construct();

        $this->_object = $object;
        $this->_object_path = $this->get_object_path();
        $this->_request_data =& $request_data;

        $this->root_types = midcom_helper_reflector_tree::get_root_classes();

        if (array_key_exists('current_type', $this->_request_data))
        {
            $expanded_type = $this->_request_data['current_type'];
            if (!in_array($expanded_type, $this->root_types))
            {
                $expanded_type = midcom_helper_reflector_tree::get($expanded_type)->get_parent_class();
            }
            $this->expanded_root_types[] = $expanded_type;
        }
        else if (isset($this->_object))
        {
            $object = $this->_object;
            if (!empty($this->_object_path))
            {
                $object = midcom::get('dbfactory')->get_object_by_guid($this->_object_path[0]);
            }

            foreach ($this->root_types as $root_type)
            {
                if (    is_a($object, $root_type)
                     || midcom_helper_reflector::is_same_class($root_type, $object->__midcom_class_name__))
                {
                    $this->expanded_root_types[] = $root_type;
                    break;
                }
            }
        }
    }

    function &_get_reflector(&$object)
    {
        if (is_string($object))
        {
            $classname = $object;
        }
        else
        {
            $classname = get_class($object);
        }
        if (!isset($this->_reflectors[$classname]))
        {
            $this->_reflectors[$classname] = midcom_helper_reflector_tree::get($object);
        }

        return $this->_reflectors[$classname];
    }

    // FIXME: Use reflectors path resolving methods.
    function get_object_path()
    {
        $object_path = array();
        if (!is_object($this->_object))
        {
            return $object_path;
        }

        $object_path[] = $this->_object->guid;

        $parent = $this->_object->get_parent();
        while (   is_object($parent)
               && $parent->guid)
        {
            $object_path[] = $parent->guid;
            $parent = $parent->get_parent();
        }

        return array_reverse($object_path);
    }

    private function _list_child_elements($object, $prefix = '    ', $level = 0)
    {
        if ($level > 25)
        {
            debug_add('Recursion level 25 exceeded, aborting', MIDCOM_LOG_ERROR);
            return;
        }
        $siblings = midcom_helper_reflector_tree::get_child_objects($object);
        if (!empty($siblings))
        {
            echo "{$prefix}<ul>\n";
            foreach ($siblings as $type => $children)
            {
                $label_mapping = Array();
                $i = 0;
                foreach ($children as $child)
                {
                    if (isset($this->shown_objects[$child->guid]))
                    {
                        continue;
                    }

                    $ref =& $this->_get_reflector($child);
                    $label_mapping[$i] = htmlspecialchars($ref->get_object_label($child));
                    $i++;
                }

                asort($label_mapping);

                foreach ($label_mapping as $index => $label)
                {
                    $child =& $children[$index];

                    $ref =& $this->_get_reflector($child);

                    $selected = $this->_is_selected($child);
                    $css_class = $type;
                    $this->_common_css_classes($child, $ref, $css_class);

                    $mode = $this->_request_data['default_mode'];
                    if (strpos($css_class, 'readonly'))
                    {
                        $mode = 'view';
                    }

                    $this->shown_objects[$child->guid] = true;

                    echo "{$prefix}    <li class=\"{$css_class}\">";

                    $icon = $ref->get_object_icon($child);
                    if (empty($label))
                    {
                        $label = "#{$child->id}";
                    }

                    echo "<a href=\"" . midcom_connection::get_url('self') . "__mfa/asgard/object/{$mode}/{$child->guid}/\" title=\"GUID: {$child->guid}, ID: {$child->id}\">{$icon}{$label}</a>\n";

                    if ($selected)
                    {
                        $this->_list_child_elements($child, "{$prefix}        ", $level+1);
                    }

                    echo "{$prefix}    </li>\n";
                }
            }
            echo "{$prefix}</ul>\n";
        }
    }

    /**
     * Renders the given root objects to HTML and calls _list_child_elements()
     *
     * @param midcom_helper_reflector_tree &$ref Reflector singleton
     */
    private function _list_root_elements(midcom_helper_reflector_tree &$ref)
    {
        $total = $ref->count_root_objects();
        if ($total == 0)
        {
            return;
        }
        if ($total > $this->_config->get('max_navigation_entries'))
        {
            if (empty($_GET['show_all_' . $ref->mgdschema_class]))
            {
                echo '<a href="?show_all_' . $ref->mgdschema_class . '=1">' . sprintf($this->_l10n->get('show %s entries'), $total) . '</a>';
                return;
            }
        }
        $root_objects = $ref->get_root_objects();

        echo "<ul class=\"midgard_admin_asgard_navigation\">\n";

        $label_mapping = array();
        foreach ($root_objects as $i => $object)
        {
            $label_mapping[$i] = htmlspecialchars($ref->get_object_label($object));
        }

        asort($label_mapping);

        foreach ($label_mapping as $index => $label)
        {
            $object = $root_objects[$index];
            $selected = $this->_is_selected($object);
            $css_class = get_class($object);
            $this->_common_css_classes($object, $ref, $css_class);

            $mode = $this->_request_data['default_mode'];
            if (strpos($css_class, 'readonly'))
            {
                $mode = 'view';
            }

            $this->shown_objects[$object->guid] = true;

            echo "    <li class=\"{$css_class}\">";

            $icon = $ref->get_object_icon($object);

            if (empty($label))
            {
                $label = "#oid_{$object->id}";
            }

            echo "<a href=\"" . midcom_connection::get_url('self') . "__mfa/asgard/object/{$mode}/{$object->guid}/\" title=\"GUID: {$object->guid}, ID: {$object->id}\">{$icon}{$label}</a>\n";

            // If there is exactly one root object, show its children, since this is what the user most likely wants to reach
            if ($selected || sizeof($root_objects) == 1)
            {
                $this->_list_child_elements($object);
            }

            echo "    </li>\n";
        }

        echo "</ul>\n";
    }

    private function _draw_plugins()
    {
        $customdata = midcom::get('componentloader')->get_all_manifest_customdata('asgard_plugin');
        foreach ($customdata as $component => $plugin_config)
        {
            $this->_request_data['section_url'] = midcom_connection::get_url('self') . "__mfa/asgard_{$component}/";
            $this->_request_data['section_name'] = midcom::get('i18n')->get_string($plugin_config['name'], $component);
            $class = $plugin_config['class'];

            if (!midcom::get('auth')->can_user_do("{$component}:access", null, $class))
            {
                // Disabled plugin
                continue;
            }

            $methods = get_class_methods($class);
            if (   is_array($methods)
                && in_array('navigation', $methods)
                && ($this->_request_data['plugin_name'] == "asgard_{$component}"))
            {
                $this->_request_data['expanded'] = true;
                midcom_show_style('midgard_admin_asgard_navigation_section_header');
                call_user_func(array($class,'navigation'));
            }
            else
            {
                $this->_request_data['expanded'] = false;
                midcom_show_style('midgard_admin_asgard_navigation_section_header');
            }

            midcom_show_style('midgard_admin_asgard_navigation_section_footer');
        }
    }

    private function _is_selected($object)
    {
        return (in_array($object->guid, $this->_object_path));
    }

    protected function _common_css_classes(&$object, &$ref, &$css_class)
    {
        $css_class .= " {$ref->mgdschema_class}";

        // Populate common properties
        $css_class = midcom::get('metadata')->get_object_classes($object, $css_class);

        if ($this->_is_selected($object))
        {
            $css_class .= ' selected';
        }
        if (   is_object($this->_object)
            && (   $object->guid == $this->_object->guid
                || (   is_a($this->_object, 'midcom_db_parameter')
                    && $object->guid == $this->_object->parentguid)))
        {
            $css_class .= ' current';
        }
        if ( !$object->can_do('midgard:update'))
        {
            $css_class .= ' readonly';
        }
    }

    /**
     * Helper function that applies visibility restrictions from various sources
     *
     * Returns an alphabetically sorted list of class => title pairs
     *
     * @return Array
     */
    private function _process_root_types()
    {
        // Included or excluded types
        $types = array();

        // Get the types that might have special display conditions
        if (   $this->_config->get('midgard_types')
            && preg_match_all('/\|([a-z0-9\.\-_]+)/', $this->_config->get('midgard_types'), $regs))
        {
            $types = $regs[1];
        }

        // Override with user selected
        // @TODO: Should this just include to the configuration selection, although it would break the consistency
        // of other similar preference sets, which simply override the global settings?
        if (   midgard_admin_asgard_plugin::get_preference('midgard_types')
            && preg_match_all('/\|([a-z0-9\.\-_]+)/', midgard_admin_asgard_plugin::get_preference('midgard_types'), $regs))
        {
            $types = $regs[1];
        }

        // Get the inclusion/exclusion model
        $model = $this->_config->get('midgard_types_model');
        if (midgard_admin_asgard_plugin::get_preference('midgard_types_model'))
        {
            $model = midgard_admin_asgard_plugin::get_preference('midgard_types_model');
        }

        // Get the possible regular expression
        $regexp = $this->_config->get('midgard_types_regexp');
        if (midgard_admin_asgard_plugin::get_preference('midgard_types_regexp'))
        {
            $regexp = midgard_admin_asgard_plugin::get_preference('midgard_types_regexp');
        }

        // "Convert" quickly to PERL regular expression
        if (!preg_match('/^[\/|]/', $regexp))
        {
            $regexp = "/{$regexp}/";
        }

        $label_mapping = Array();
        foreach ($this->root_types as $root_type)
        {
            // If there are any types or if the regular expression has been set, check which
            // types should be shown
            if (   !empty($types)
                || $regexp !== '//')
            {
                // Skip the excluded
                if ($model === 'exclude')
                {
                    if (   !empty($types)
                        && in_array($root_type, $types))
                    {
                        continue;
                    }

                    if (   $regexp !== '//'
                        && preg_match($regexp, $root_type))
                    {
                        continue;
                    }
                }
                else
                {
                    if (   !empty($types)
                        && !in_array($root_type, $types))
                    {
                        continue;
                    }

                    if (   $regexp !== '//'
                        && !preg_match($regexp, $root_type))
                    {
                        continue;
                    }
                }
            }

            $ref = $this->_get_reflector($root_type);
            $label_mapping[$root_type] = $ref->get_class_label();
        }
        asort($label_mapping);

        return $label_mapping;
    }

    function draw()
    {
        $this->_request_data['chapter_name'] = midcom::get('config')->get('midcom_site_title');
        midcom_show_style('midgard_admin_asgard_navigation_chapter');

        $this->_draw_plugins();

        if (!midcom::get('auth')->can_user_do('midgard.admin.asgard:manage_objects', null, 'midgard_admin_asgard_plugin'))
        {
            return;
        }

        $label_mapping = $this->_process_root_types();

        $expanded_types = array_intersect(array_keys($label_mapping), $this->expanded_root_types);
        $collapsed_types = array_diff($label_mapping, $expanded_types);

        /*
         * Use a dropdown for displaying the navigation if at least one type is expanded
         * and the user has the corresponding preference set. That way, you expanded types
         * can take up the maximum available space while all types are still accessible with one
         * click if nothing is expanded
         */
        $types_shown = false;
        if (    sizeof($expanded_types) > 0
             && midgard_admin_asgard_plugin::get_preference('navigation_type') === 'dropdown')
        {
            $this->_draw_select_navigation();
            $types_shown = true;
        }

        foreach ($expanded_types as $root_type)
        {
            $this->_request_data['section_url'] = midcom_connection::get_url('self') . "__mfa/asgard/{$root_type}";
            $this->_request_data['section_name'] = $label_mapping[$root_type];
            $this->_request_data['expanded'] = true;
            midcom_show_style('midgard_admin_asgard_navigation_section_header');
            $ref = $this->_get_reflector($root_type);
            $this->_list_root_elements($ref);

            midcom_show_style('midgard_admin_asgard_navigation_section_footer');
        }

        if (!$types_shown)
        {
            $this->_request_data['section_name'] = $this->_l10n->get('midgard objects');
            $this->_request_data['expanded'] = true;
            midcom_show_style('midgard_admin_asgard_navigation_section_header');

            $this->_draw_type_list($collapsed_types);

            midcom_show_style('midgard_admin_asgard_navigation_section_footer');
        }
    }

    private function _draw_type_list(array $types)
    {
        echo "<ul class=\"midgard_admin_asgard_navigation\">\n";

        foreach ($types as $type => $label)
        {
            $url = midcom_connection::get_url('self') . "__mfa/asgard/{$type}";
            echo "    <li class=\"mgdschema-type\">";

            $dbaclass = midcom::get('dbclassloader')->get_midcom_class_name_for_mgdschema_object($type);
            if (   $dbaclass
                && class_exists($dbaclass))
            {
                $object = new $dbaclass;
            }
            else
            {
                $object = new $type;
            }
            $icon = midcom_helper_reflector::get_object_icon($object);

            echo "<a href=\"" . $url . "\" title=\"{$label}\">{$icon}{$label}</a>\n";

            echo "    </li>\n";
        }

        echo "</ul>\n";
    }

    private function _draw_select_navigation()
    {
        if (!empty($this->_object_path))
        {
            $root_object = midcom::get('dbfactory')->get_object_by_guid($this->_object_path[0]);
            $this->_request_data['root_object'] =& $root_object;
            $this->_request_data['navigation_type'] = $root_object->__mgdschema_class_name__;
        }
        else if (isset($this->expanded_root_types[0]))
        {
            $this->_request_data['navigation_type'] = $this->expanded_root_types[0];
        }
        else
        {
            $this->_request_data['navigation_type'] = '';
        }

        $label_mapping = Array();

        foreach ($this->root_types as $root_type)
        {
            $ref = $this->_get_reflector($root_type);
            $label_mapping[$root_type] = $ref->get_class_label();
        }
        asort($label_mapping);

        $this->_request_data['label_mapping'] = $label_mapping;
        $this->_request_data['expanded_root_types'] = $this->expanded_root_types;

        midcom_show_style('midgard_admin_asgard_navigation_sections');
    }
}
?>
