<?php
/**
 * @package midgard.admin.asgard
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Copy/delete tree branch viewer
 * @package midgard.admin.asgard
 */
class midgard_admin_asgard_copytree extends midgard_admin_asgard_navigation
{
    /**
     * Switch to determine if the whole tree should be copied
     *
     * @var boolean
     */
    public $copy_tree = false;

    /**
     * Switch to determine the visibility of inputs
     *
     * @var boolean
     */
    public $inputs = true;

    /**
     * Choose the target type
     *
     * @var String
     */
    public $input_type;

    /**
     * Choose the target name for the form
     *
     * @var String
     */
    public $input_name;

    /**
     * Show the link to view the object
     *
     * @var boolean
     */
    public $view_link = false;

    /**
     * Show the link to view the object
     *
     * @var boolean
     */
    public $edit_link = false;

    /**
     * Page prefix
     *
     * @var String
     */
    public $page_prefix = '';

    /**
     * Constructor, connect to the parent class constructor.
     *
     * @param mixed $object
     * @param array $request_data
     */
    public function __construct($object, &$request_data)
    {
        parent::__construct($object, $request_data);
        $this->page_prefix = midcom_core_context::get()->get_key(MIDCOM_CONTEXT_ANCHORPREFIX);
    }

    /**
     * List the child elements
     *
     * @param mixed $object     MgdSchema object
     * @param string $prefix     Indent for the output
     * @param int $level         Level of depth
     */
    public function _list_child_elements($object, $prefix = '    ', $level = 0)
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
                foreach ($children as $child)
                {
                    if (isset($this->shown_objects[$child->guid]))
                    {
                        continue;
                    }

                    $ref =& $this->_get_reflector($child);

                    $span_class = '';
                    $css_class = $type;
                    $this->_common_css_classes($child, $ref, $css_class);
                    $this->shown_objects[$child->guid] = true;

                    echo "{$prefix}    <li class=\"{$css_class}\">\n";

                    $label = htmlspecialchars($ref->get_object_label($child));
                    $icon = $ref->get_object_icon($child);
                    if (empty($label))
                    {
                        $label = "#{$child->id}";
                    }

                    if ($this->copy_tree)
                    {
                        $checked = ' checked="checked"';
                    }
                    else
                    {
                        $checked = '';
                    }

                    if ($this->inputs)
                    {
                        // This value is used for compiling the exclusion list: if the object is found from this list, but not from the selection list,
                        // it means that the selection did not include the object GUID
                        echo "{$prefix}        <input type=\"hidden\" name=\"all_objects[]\" value=\"{$child->guid}\" />\n";

                        echo "{$prefix}        <label for=\"item_{$child->guid}\">\n";
                        echo "{$prefix}        <input id=\"item_{$child->guid}\" type=\"{$this->input_type}\" name=\"{$this->input_name}\" value=\"{$child->guid}\"{$checked} />\n";
                    }

                    echo "{$prefix}            <span class=\"title{$span_class}\">{$icon}{$label}</span>\n";

                    // Show the link to the object
                    if ($this->view_link)
                    {
                        echo "{$prefix}            <a href=\"{$this->page_prefix}__mfa/asgard/object/view/{$child->guid}/\" class=\"thickbox\" target=\"_blank\" title=\"" . sprintf($this->_l10n->get('%s (%s)'), $label, $ref->get_class_label()) . "\">\n";
                        echo "{$prefix}                <img src=\"" . MIDCOM_STATIC_URL . "/stock-icons/16x16/view.png\" alt=\"" . $this->_l10n->get('view object') . "\" />\n";
                        echo "{$prefix}            </a>\n";
                    }

                    // Show the link to the object
                    if ($this->edit_link)
                    {
                        echo "{$prefix}            <a href=\"{$this->page_prefix}__mfa/asgard/object/edit/{$child->guid}/\" class='target_blank' title=\"" . sprintf($this->_l10n->get('%s (%s)'), $label, $ref->get_class_label()) . "\">\n";
                        echo "{$prefix}                <img src=\"" . MIDCOM_STATIC_URL . "/stock-icons/16x16/edit.png\" alt=\"" . $this->_l10n->get('edit object') . "\" />\n";
                        echo "{$prefix}            </a>\n";
                    }

                    if ($this->inputs)
                    {
                        echo "{$prefix}        </label>\n";
                    }

                    // List the child elements
                    $this->_list_child_elements($child, "{$prefix}        ", $level + 1);

                    echo "{$prefix}    </li>\n";
                }
            }
            echo "{$prefix}</ul>\n";
        }
    }

    /**
     * Draw the tree selector
     */
    public function draw()
    {
        if (!$this->input_type)
        {
            $this->input_type = 'checkbox';
        }

        if (!$this->input_name)
        {
            if ($this->input_type === 'checkbox')
            {
                $this->input_name = 'selected[]';
            }
            else
            {
                $this->input_name = 'target';
            }
        }

        $root_object =& $this->_object;
        $this->_list_child_elements($root_object);
    }
}
?>