<?php
/**
 * @package midcom.helper.reflector
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * The Grand Unified Reflector
 *
 * @package midcom.helper.reflector
 */
class midcom_helper_reflector extends midcom_baseclasses_components_purecode
{
    public $mgdschema_class = false;
    protected $_mgd_reflector = false;
    protected $_dummy_object = false;

    private static $_cache = array
    (
        'l10n' => array(),
        'instance' => array(),
        'title' => array(),
        'name' => array(),
        'object_icon_map' => null,
        'create_icon_map' => null
    );

    /**
     * Constructor, takes classname or object, resolved MgdSchema root class automagically
     *
     * @param string/midgard_object $src classname or object
     */
    public function __construct($src)
    {
        parent::__construct();
        // Handle object vs string
        if (is_object($src))
        {
            $original_class = get_class($src);
        }
        else
        {
            $original_class = $src;
        }

        // Resolve root class name
        $this->mgdschema_class = self::resolve_baseclass($original_class);

        // Could not resolve root class name
        if (empty($this->mgdschema_class))
        {
            debug_add("Could not determine MgdSchema baseclass for '{$original_class}'", MIDCOM_LOG_ERROR);
            return;
        }

        // Instantiate midgard reflector
        if (!class_exists($this->mgdschema_class))
        {
            return;
        }
        $this->_mgd_reflector = new midgard_reflection_property($this->mgdschema_class);

        // Instantiate dummy object
        $this->_dummy_object = new $this->mgdschema_class;
    }

    public static function &get($src)
    {
        if (is_object($src))
        {
            $classname = get_class($src);
        }
        else
        {
            $classname = $src;
        }

        if (!isset(self::$_cache['instance'][$classname]))
        {
            self::$_cache['instance'][$classname] = new midcom_helper_reflector($src);
        }
        return self::$_cache['instance'][$classname];
    }

    /**
     * Gets a midcom_helper_l10n instance for component governing the type
     *
     * @return midcom_services_i18n_l10n  Localization library for the reflector object class
     */
    public function get_component_l10n()
    {
        // Use cache if we have it
        if (isset(self::$_cache['l10n'][$this->mgdschema_class]))
        {
            return self::$_cache['l10n'][$this->mgdschema_class];
        }
        $midcom_dba_classname = midcom::get('dbclassloader')->get_midcom_class_name_for_mgdschema_object($this->_dummy_object);
        if (empty($midcom_dba_classname))
        {
            // Could not resolve MidCOM DBA class name, fallback early to our own l10n
            debug_add("Could not get MidCOM DBA classname for type {$this->mgdschema_class}, using our own l10n", MIDCOM_LOG_INFO);
            self::$_cache['l10n'][$this->mgdschema_class] = $this->_l10n;
            return $this->_l10n;
        }

        $component = midcom::get('dbclassloader')->get_component_for_class($midcom_dba_classname);
        if (!$component)
        {
            debug_add("Could not resolve component for DBA class {$midcom_dba_classname}, using our own l10n", MIDCOM_LOG_INFO);
            self::$_cache['l10n'][$this->mgdschema_class] = $this->_l10n;
            return $this->_l10n;
        }
        // Got component, try to load the l10n helper for it
        $midcom_i18n = midcom::get('i18n');
        $component_l10n = $midcom_i18n->get_l10n($component);
        if (!empty($component_l10n))
        {
            self::$_cache['l10n'][$this->mgdschema_class] = $component_l10n;
            return $component_l10n;
        }

        // Could not get anything else, use our own l10n
        debug_add("Everything else failed, using our own l10n for type {$this->mgdschema_class}", MIDCOM_LOG_WARN);

        self::$_cache['l10n'][$this->mgdschema_class] = $this->_l10n;
        return $this->_l10n;
    }

    /**
     * Get the localized label of the class
     *
     * @return string Class label
     * @todo remove any hardcoded class names/prefixes
     */
    public function get_class_label()
    {
        $component_l10n = $this->get_component_l10n();
        $use_classname = $this->mgdschema_class;

        $midcom_dba_classname = midcom::get('dbclassloader')->get_midcom_class_name_for_mgdschema_object($this->_dummy_object);

        if (!empty($midcom_dba_classname))
        {
            $use_classname = $midcom_dba_classname;
        }

        $use_classname = preg_replace('/_(db|dba)$/', '', $use_classname);

        $label = $component_l10n->get($use_classname);
        if ($label == $use_classname)
        {
            // Class string not localized, try Bergie's way to pretty-print
            $classname_parts = explode('_', $use_classname);
            if (count($classname_parts) >= 3)
            {
                // Drop first two parts of class name
                array_shift($classname_parts);
                array_shift($classname_parts);
            }
            // FIXME: Remove hardcoded class prefixes
            $use_label = preg_replace('/(openpsa|positioning|notifications)_/', '', implode('_', $classname_parts));

            $use_label = str_replace('_', ' ', $use_label);
            $label = $component_l10n->get($use_label);
            if ($use_label == $label)
            {
                $label = ucwords($use_label);
            }
        }
        return $label;
    }

    /**
     * Get property name to use as label
     *
     * @return string name of property to use as label (or false on failure)
     * @todo remove any hardcoded class names/prefixes
     */
    public function get_label_property()
    {
        $midcom_class = midcom::get('dbclassloader')->get_midcom_class_name_for_mgdschema_object($this->mgdschema_class);
        if ($midcom_class)
        {
            $obj = new $midcom_class;
        }
        else
        {
            $obj = new $this->mgdschema_class;
        }
        $properties = get_object_vars($obj);

        if (isset($properties['__object']))
        {
            $tmp = get_object_vars($properties['__object']);

            if (!empty($tmp))
            {
                $properties = $tmp;
            }
        }

        if (empty($properties))
        {
            debug_add("Could not list object properties, aborting", MIDCOM_LOG_ERROR);
            return false;
        }

        // TODO: less trivial implementation
        // FIXME: Remove hardcoded class logic
        switch(true)
        {
            case (method_exists($obj, 'get_label_property')):
                $property = $obj->get_label_property();
                break;
            // TODO: Switch to use the get_name/title_property helpers below
            case (midcom::get('dbfactory')->is_a($obj, 'midcom_db_topic')):
                $property = 'extra';
                break;
            case (midcom::get('dbfactory')->is_a($obj, 'midcom_db_person')):
                $property = array
                (
                    'rname',
                    'username',
                    'id',
                );
                break;
            // TODO: Switch to use the get_name/title_property helpers
            case (array_key_exists('title', $properties)):
                $property = 'title';
                break;
            case (array_key_exists('name', $properties)):
                $property = 'name';
                break;
            default:
                $property = 'guid';
        }

        return $property;
    }

    /**
     * Get the object label property value
     *
     * @param mixed $object    MgdSchema object
     * @return String       Label of the object
     * @todo remove any hardcoded class names/prefixes
     */
    public function get_object_label($object)
    {
        if (!isset($object->__mgdschema_class_name__))
        {
            // Not a MidCOM DBA object
            try
            {
                $obj = midcom::get('dbfactory')->convert_midgard_to_midcom($object);
            }
            catch (midcom_error $e)
            {
                return false;
            }
        }
        else
        {
            $obj = $object;
        }

        if (method_exists($obj, 'get_label'))
        {
            $label = $obj->get_label();
        }
        else
        {
            $properties = array_flip($obj->get_properties());
            if (empty($properties))
            {
                debug_add("Could not list object properties, aborting", MIDCOM_LOG_ERROR);
                return false;
            }
            else if (isset($properties['title']))
            {
                $label = $obj->title;
            }
            else if (isset($properties['name']))
            {
                $label = $obj->name;
            }
            else
            {
                $label = $obj->guid;
            }
        }
        return $label;
    }

    /**
     * Get the name of the create icon image
     *
     * @param string $type  Name of the type
     * @return string       URL name of the image
     */
    public static function get_create_icon($type)
    {
        if (null === self::$_cache['create_icon_map'])
        {
            self::$_cache['create_icon_map'] = self::_get_icon_map('create_type_magic', 'new-text.png');
        }

        $icon_callback = array($type, 'get_create_icon');
        switch (true)
        {
            // class has static method to tell us the answer ? great !
            case (is_callable($icon_callback)):
                $icon = call_user_func($icon_callback);
            // configuration icon
            case (isset(self::$_cache['create_icon_map'][$type])):
                $icon = self::$_cache['create_icon_map'][$type];
                break;

            // heuristics magic (in stead of adding something here, take a look at config key "create_type_magic")
            case (strpos($type, 'member') !== false):
            case (strpos($type, 'organization') !== false):
                $icon = 'stock_people-new.png';
                break;
            case (strpos($type, 'person') !== false):
            case (strpos($type, 'member') !== false):
                $icon = 'stock_person-new.png';
                break;
            case (strpos($type, 'event') !== false):
                $icon = 'stock_event_new.png';
                break;

            // Fallback default value
            default:
                $icon = self::$_cache['create_icon_map']['__default__'];
                break;
        }
        return $icon;
    }

    /**
     * Get the name of the icon image
     *
     * @param mixed $obj          MgdSchema object
     * @param boolean $url_only   Get only the URL location instead of full <img /> tag
     * @return string             URL name of the image
     */
    public static function get_object_icon($obj, $url_only = false)
    {
        if (null === self::$_cache['object_icon_map'])
        {
            self::$_cache['object_icon_map'] = self::_get_icon_map('object_icon_magic', 'document.png');
        }

        $object_class = get_class($obj);
        $object_baseclass = self::resolve_baseclass($obj);
        switch(true)
        {
            // object knows it's icon, how handy!
            case (method_exists($obj, 'get_icon')):
                $icon = $obj->get_icon();
                break;

            // configuration icon
            case (isset(self::$_cache['object_icon_map'][$object_class])):
                $icon = self::$_cache['object_icon_map'][$object_class];
                break;
            case (isset(self::$_cache['object_icon_map'][$object_baseclass])):
                $icon = self::$_cache['object_icon_map'][$object_baseclass];
                break;

            // heuristics magic (in stead of adding something here, take a look at config key "object_icon_magic")
            case (strpos($object_class, 'person') !== false):
                $icon = 'stock_person.png';
                break;
            case (strpos($object_class, 'event') !== false):
                $icon = 'stock_event.png';
                break;
            case (strpos($object_class, 'member') !== false):
            case (strpos($object_class, 'organization') !== false):
                $icon = 'stock_people.png';
                break;
            case (strpos($object_class, 'element') !== false):
                $icon = 'text-x-generic-template.png';
                break;

            // Fallback default value
            default:
                $icon = self::$_cache['object_icon_map']['__default__'];
                break;
        }

        // If the icon name has no slash then it's in stock-icons
        if (strpos($icon, '/') === false)
        {
            $icon_url = MIDCOM_STATIC_URL . "/stock-icons/16x16/{$icon}";
        }
        else
        {
            $icon_url = $icon;
        }
        if ($url_only)
        {
            return $icon_url;
        }
        return "<img src=\"{$icon_url}\" align=\"absmiddle\" border=\"0\" alt=\"{$object_class}\" /> ";
    }

    private static function _get_icon_map($config_key, $fallback)
    {
        $config = midcom_baseclasses_components_configuration::get('midcom.helper.reflector', 'config');
        $icons2classes = $config->get($config_key);
        $icon_map = array();
        //sanity
        if (!is_array($icons2classes))
        {
            debug_add('Config key "create_type_magic" is not an array', MIDCOM_LOG_ERROR);
            debug_print_r("\$this->_config->get('create_type_magic')", $icons2classes, MIDCOM_LOG_INFO);
        }
        else
        {
            foreach ($icons2classes as $icon => $classes)
            {
                foreach ($classes as $class)
                {
                    $icon_map[$class] = $icon;
                }
            }
        }
        if (!isset($icon_map['__default__']))
        {
            $icon_map['__default__'] = $fallback;
        }
        return $icon_map;
    }

    /**
     * Get class properties to use as search fields in choosers or other direct DB searches
     *
     * @return array of property names
     */
    public function get_search_properties()
    {
        // Return cached results if we have them
        static $cache = array();
        if (isset($cache[$this->mgdschema_class]))
        {
            return $cache[$this->mgdschema_class];
        }
        debug_add("Starting analysis for class {$this->mgdschema_class}");
        $obj =& $this->_dummy_object;

        // Get property list and start checking (or abort on error)
        if (midcom::get('dbclassloader')->is_midcom_db_object($obj))
        {
            $properties = $obj->get_object_vars();
        }
        else
        {
            $properties = array_keys(get_object_vars($obj));
        }
        if (empty($properties))
        {
            debug_add("Could not list object properties, aborting", MIDCOM_LOG_ERROR);
            return false;
        }

        $search_properties = array();

        foreach ($properties as $property)
        {
            switch(true)
            {
                case (strpos($property, 'name') !== false):
                    // property contains 'name'
                case ($property == 'title'):
                case ($property == 'tag'):
                case ($property == 'firstname'):
                case ($property == 'lastname'):
                case ($property == 'official'):
                case ($property == 'username'):
                    $search_properties[$property] = true;
                    break;
                // TODO: More per property heuristics
            }
        }
        // TODO: parent and up heuristics

        $label_prop = $this->get_label_property();

        if (    is_string($label_prop)
             && $label_prop != 'guid'
             && property_exists($obj, $label_prop))
        {
            $search_properties[$label_prop] = true;
        }

        // Exceptions - always search these fields
        $always_search_all = $this->_config->get('always_search_fields');
        // safety against misconfiguration
        if (!is_array($always_search_all))
        {
            $always_search_all = array();
        }
        if (isset($always_search_all[$this->mgdschema_class]))
        {
            foreach ($always_search_all[$this->mgdschema_class] as $property)
            {
                if (!in_array($property, $properties))
                {
                    debug_add("Property '{$property}' is set as always search, but is not a property in class '{$this->mgdschema_class}'", MIDCOM_LOG_WARN);
                    continue;
                }
                $search_properties[$property] = true;
            }
        }

        // Exceptions - never search these fields
        $never_search_all = $this->_config->get('never_search_fields');
        // safety against misconfiguration
        if (!is_array($never_search_all))
        {
            $never_search_all = array();
        }
        if (isset($never_search_all[$this->mgdschema_class]))
        {
            foreach ($never_search_all[$this->mgdschema_class] as $property)
            {
                if (array_key_exists($property, $search_properties))
                {
                    debug_add("Removing '{$property}' from \$search_properties", MIDCOM_LOG_INFO);
                    unset($search_properties[$property]);
                }
            }
        }

        $search_properties = array_keys($search_properties);
        debug_print_r("Search properties for {$this->mgdschema_class}: ", $search_properties);
        $cache[$this->mgdschema_class] = $search_properties;
        return $search_properties;
    }

    /**
     * Gets a list of link properties and the links target info
     *
     * Link info key specification
     *     'class' string link target class name
     *     'target' string link target property (of target class)
     *     'parent' boolean link is link to "parent" in object tree
     *     'up' boolean link is link to "up" in object tree
     *
     * @return array multidimensional array keyed by property, values are arrays with link info (or false in case of failure)
     */
    public function get_link_properties()
    {
        // Return cached results if we have them
        static $cache = array();
        if (isset($cache[$this->mgdschema_class]))
        {
            return $cache[$this->mgdschema_class];
        }
        debug_add("Starting analysis for class {$this->mgdschema_class}");

        // Shorthands
        $ref =& $this->_mgd_reflector;
        $obj =& $this->_dummy_object;

        // Get property list and start checking (or abort on error)
        $properties = get_object_vars($obj);
        if (empty($properties))
        {
            debug_add("Could not list object properties, aborting", MIDCOM_LOG_ERROR);
            return false;
        }

        $links = array();
        $parent_property = midgard_object_class::get_property_parent($obj);
        $up_property = midgard_object_class::get_property_up($obj);
        foreach ($properties as $property => $dummy)
        {
            if ($property == 'guid')
            {
                // GUID, even though of type MGD_TYPE_GUID, is never a link
                continue;
            }

            if (   !$ref->is_link($property)
                && $ref->get_midgard_type($property) != MGD_TYPE_GUID)
            {
                continue;
            }
            debug_add("Processing property '{$property}'");
            $linkinfo = array
            (
                'class' => null,
                'target' => null,
                'parent' => false,
                'up' => false,
                'type' => $ref->get_midgard_type($property),
            );
            if (   !empty($parent_property)
                && $parent_property === $property)
            {
                debug_add("Is 'parent' property");
                $linkinfo['parent'] = true;
            }
            if (   !empty($up_property)
                && $up_property === $property)
            {
                debug_add("Is 'up' property");
                $linkinfo['up'] = true;
            }

            $type = $ref->get_link_name($property);
            debug_add("get_link_name returned '{$type}'");
            if (!empty($type))
            {
                $linkinfo['class'] = $type;
            }
            unset($type);

            $target = $ref->get_link_target($property);

            debug_add("get_link_target returned '{$target}'");
            if (!empty($target))
            {
                $linkinfo['target'] = $target;
            }
            elseif ($linkinfo['type'] == MGD_TYPE_GUID)
            {
                $linkinfo['target'] = 'guid';
            }
            unset($target);

            $links[$property] = $linkinfo;
            unset($linkinfo);
        }

        debug_print_r("Links for {$this->mgdschema_class}: ", $links);
        $cache[$this->mgdschema_class] = $links;
        return $links;
    }

    /**
     * Statically callable method to map extended classes
     *
     * For example org.openpsa.* components often expand core objects,
     * in config we specify which classes we wish to substitute with which
     *
     * @param string $schema_type classname to check rewriting for
     * @return string new classname (or original in case no rewriting is to be done)
     */
    public static function class_rewrite($schema_type)
    {
        static $extends = false;
        if ($extends === false)
        {
            $extends = midcom_baseclasses_components_configuration::get('midcom.helper.reflector', 'config')->get('class_extends');
            // Safety against misconfiguration
            if (!is_array($extends))
            {
                debug_add("config->get('class_extends') did not return array, invalid configuration ??", MIDCOM_LOG_ERROR);
                return $schema_type;
            }
        }
        if (   isset($extends[$schema_type])
            && class_exists($extends[$schema_type]))
        {
            return $extends[$schema_type];
        }
        return $schema_type;
    }

    /**
     * Statically callable method to see if two MgdSchema classes are the same
     *
     * NOTE: also takes into account the various extended class scenarios
     *
     * @param string $class_one first class to compare
     * @param string $class_two second class to compare
     * @return boolean response
     */
    public static function is_same_class($class_one, $class_two)
    {
        $one = self::resolve_baseclass($class_one);
        $two = self::resolve_baseclass($class_two);
        if ($one == $two)
        {
            return true;
        }
        if (self::class_rewrite($one) == $two)
        {
            return true;
        }
        if ($one == self::class_rewrite($two))
        {
            return true;
        }

        return false;
    }

    /**
     * Get an object, deleted or not
     *
     * @param string $guid    GUID of the object
     * @param string $type    MgdSchema type
     * @return mixed          MgdSchema object
     */
    public static function get_object($guid, $type)
    {
        static $objects = array();

        if (!isset($objects[$guid]))
        {
            $qb = new midgard_query_builder($type);
            $qb->add_constraint('guid', '=', $guid);
            // We know we want/need only one result
            $qb->set_limit(1);
            $qb->include_deleted();
            $results = $qb->execute();
            if (count($results) == 0)
            {
                $objects[$guid] = null;
            }
            else
            {
                $objects[$guid] = $results[0];
            }
        }

        return $objects[$guid];
    }

    /**
     * Get the MgdSchema classname for given class, statically callable
     *
     * @param mixed $classname either string (class name) or object
     * @return string the base class name
     */
    public static function resolve_baseclass($classname)
    {
        static $cached = array();

        if (is_object($classname))
        {
            $class_instance = $classname;
            $classname = get_class($classname);
        }

        if (empty($classname))
        {
            return null;
        }

        if (isset($cached[$classname]))
        {
            return $cached[$classname];
        }

        if (!isset($class_instance))
        {
            $class_instance = new $classname();
        }

        // Check for decorators first
        $parent_class = false;
        if (   isset($class_instance->__object)
            && is_object($class_instance->__object))
        {
            // Decorated object instance
            $parent_class = get_class($class_instance->__object);
        }
        else if (!empty($class_instance->__mgdschema_class_name__))
        {
            // Decorator without object
            $parent_class = $object->__mgdschema_class_name__;
        }
        else
        {
            $parent_class = $classname;
        }

        $cached[$classname] = $parent_class;

        return $cached[$classname];
    }

    /**
     * Method to resolve the "name" property of given object
     *
     * @see midcom_helper_reflector::get_name_property()
     * @param object $object the object to get the name property for
     * @return string name of property or boolean false on failure
     * @todo when midgard_reflection_property supports flagging name fields use that in stead of heuristics
     */
    public function get_name_property_nonstatic($object)
    {
        // Cache results per class within request
        $key = get_class($object);
        if (isset(self::$_cache['name'][$key]))
        {
            return self::$_cache['name'][$key];
        }
        self::$_cache['name'][$key] = false;

        // Configured properties
        $name_exceptions = $this->_config->get('name_exceptions');
        foreach ($name_exceptions as $class => $property)
        {
            if (midcom::get('dbfactory')->is_a($object, $class))
            {
                if (   $property !== false
                    && !midcom::get('dbfactory')->property_exists($object, $property))
                {
                    debug_add("Matched class '{$key}' to '{$class}' via is_a but property '{$property}' does not exist", MIDCOM_LOG_ERROR);
                    self::$_cache['name'][$key] = false;
                    return self::$_cache['name'][$key];
                }
                self::$_cache['name'][$key] = $property;
                return self::$_cache['name'][$key];
            }
        }

        // The simple heuristic
        if (midcom::get('dbfactory')->property_exists($object, 'name'))
        {
            self::$_cache['name'][$key] = 'name';
        }

        return self::$_cache['name'][$key];
    }

    /**
     * Statically callable method to resolve the "name" property of given object
     *
     * @see midcom_helper_reflector::get_name_property_nonstatic()
     * @param object $object the object to get the name property for
     * @return string name of property or boolean false on failure
     */
    public static function get_name_property($object)
    {
        // Cache results per class within request
        $key = get_class($object);
        if (isset(self::$_cache['name'][$key]))
        {
            return self::$_cache['name'][$key];
        }
        try
        {
            $resolver = self::get($object);
            return $resolver->get_name_property_nonstatic($object);
        }
        catch (midcom_error $e)
        {
            debug_add('Could not get reflector instance for class ' . $key . ': ' . $e->getMessage(), MIDCOM_LOG_ERROR);
            self::$_cache['name'][$key] = null;
        }
        return self::$_cache['name'][$key];
    }

    /**
     * Statically callable method to resolve the "title" of given object
     *
     * NOTE: This is distinctly different from get_object_label, which will always return something
     * even if it's just the class name and GUID, also it will for some classes include extra info (like datetimes)
     * which we do not want here.
     *
     * @param object $object the object to get the name property for
     * @param string $title_property property to use as "name", if left to default (null), will be reflected
     * @return string value of name property or boolean false on failure
     */
    public static function get_object_title($object, $title_property = null)
    {
        if (is_null($title_property))
        {
            $title_property = self::get_title_property($object);
        }
        if (   empty($title_property)
            || !midcom::get('dbfactory')->property_exists($object, $title_property))
        {
            // Could not resolve valid property
            return false;
        }

        return (string) $object->{$title_property};
    }

    /**
     * Statically callable method to resolve the "title" property of given object
     *
     * NOTE: This is distinctly different from get_label_property, which will always return something
     * even if it's just the guid
     *
     * @param object $object The object to get the title property for
     * @return string Name of property or boolean false on failure
     */
    public static function get_title_property($object)
    {
        $resolver = self::get($object);
        return $resolver->get_title_property_nonstatic($object);
    }

    /**
     * Resolve the "title" property of given object
     *
     * NOTE: This is distinctly different from get_label_property, which will always return something
     * even if it's just the guid
     *
     * @see midcom_helper_reflector::get_object_title()
     * @param $object the object to get the title property for
     * @return string name of property or boolean false on failure
     * @todo when midgard_reflection_property supports flagging name fields use that in stead of heuristics
     */
    public function get_title_property_nonstatic($object)
    {
        // Cache results per class within request
        $key = get_class($object);
        if (isset(self::$_cache['title'][$key]))
        {
            return self::$_cache['title'][$key];
        }
        self::$_cache['title'][$key] = false;

        // Configured properties
        $title_exceptions = $this->_config->get('title_exceptions');

        foreach ($title_exceptions as $class => $property)
        {
            if (midcom::get('dbfactory')->is_a($object, $class))
            {
                if (   $property !== false
                    && !midcom::get('dbfactory')->property_exists($object, $property))
                {
                    debug_add("Matched class '{$key}' to '{$class}' via is_a but property '{$property}' does not exist", MIDCOM_LOG_ERROR);
                    self::$_cache['title'][$key] = false;
                    return self::$_cache['title'][$key];
                }
                self::$_cache['title'][$key] = $property;
                return self::$_cache['title'][$key];
            }
        }

        // The easy check
        if (midcom::get('dbfactory')->property_exists($object, 'title'))
        {
            self::$_cache['title'][$key] = 'title';
        }

        return self::$_cache['title'][$key];
    }
}
?>
