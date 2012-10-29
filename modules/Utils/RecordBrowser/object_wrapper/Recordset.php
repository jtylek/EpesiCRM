<?php

abstract class RBO_Recordset {

    private $tab;
    private $class;

    abstract function table_name();

    function class_name() {
        return 'RBO_Record';
    }

    abstract function fields();

    public function __construct() {
        $this->tab = $this->table_name();
        $this->class = $this->class_name();
        // replace it with something like is_a
        if (!array_key_exists('RBO_Record', class_parents($this->class)) && !($this->class == 'RBO_Record'))
            trigger_error('Record class (' . $this->class . ') for recordset ' . $this->tab . ' is not instance of RBO_Record', E_USER_ERROR);
    }

    public function record_to_object($array) {
        if (is_array($array)) {
            return new $this->class($this, $array);
        }
        return null;
    }

    public function array_of_records_to_array_of_objects($array) {
        $ret = array();
        foreach ($array as $k => $entry) {
            $ret[$k] = $this->record_to_object($entry);
        }
        return $ret;
    }

    protected static function create_record_object($recordset_class, $record) {
        $recordset = self::recordset_instance($recordset_class);
        return $recordset->record_to_object($record);
    }

    protected static $recordsets_instances = array();

    protected static function recordset_instance($recordset_class) {
        if (is_object($recordset_class))
            return $recordset_class;
        if (isset(self::$recordsets_instances[$recordset_class]))
            return self::$recordsets_instances[$recordset_class];
        else
            return self::$recordsets_instances[$recordset_class] = new $recordset_class();
    }

    ///////////// magic QFfield and display callbacks //////////////

    public function refresh_magic_callbacks() {
        foreach ($this->fields() as $field) {
            $field_id = Utils_RecordBrowserCommon::get_field_id($field->name);

            if (!$field->QFfield_callback) {
                $qffield_callback = 'QFfield_' . $field_id;
                if (method_exists($this, $qffield_callback) || method_exists($this->class, $qffield_callback))
                    Utils_RecordBrowserCommon::set_QFfield_callback($this->tab, $field->name, get_class($this) . '::__QFfield_magic_callback');
                else
                    Utils_RecordBrowserCommon::unset_QFfield_callback($this->tab, $field->name);
            }

            if (!$field->display_callback) {
                $display_callback = 'display_' . $field_id;
                if (method_exists($this, $display_callback) || method_exists($this->class, $display_callback))
                    Utils_RecordBrowserCommon::set_display_callback($this->tab, $field->name, get_class($this) . '::__display_magic_callback');
                else
                    Utils_RecordBrowserCommon::unset_display_callback($this->tab, $field->name);
            }
        }
    }

    public static final function __QFfield_magic_callback(&$form, $field, $label, $mode, $default, $desc, $rb_obj = null) {
        list($recordset_class, $method) = $rb_obj->get_qffield_method($desc['name']);
        $args = func_get_args();
        $callback_name = 'QFfield_' . $field;
        if (self::_callback_recordset($recordset_class, $callback_name, $args, $return_value)) {
            return $return_value;
        }
        if (self::_callback_record($recordset_class, $rb_obj->record, $callback_name, $args, $return_value2)) {
            return $return_value2;
        }
        trigger_error("Method $callback_name does not exist in recordset, nor record class", E_USER_ERROR);
    }

    public static final function __display_magic_callback($record, $nolink, $desc) {
        list($recordset_class, $method) = $desc['display_callback'];
        $args = func_get_args();
        $callback_name = 'display_' . $desc['id'];

        $args[0] = self::create_record_object($recordset_class, $record);
        if (self::_callback_recordset($recordset_class, $callback_name, $args, $return_value)) {
            return $return_value;
        }
        array_shift($args); // remove first argument, because it's record
        if (self::_callback_record($recordset_class, $record, $callback_name, $args, $return_value2)) {
            return $return_value2;
        }
        trigger_error("Method $callback_name does not exist in recordset, nor record class", E_USER_ERROR);
    }

    private static final function _callback_recordset($recordset_class, $callback_name, $args, &$return_value) {
        $recordset = self::recordset_instance($recordset_class);
        // check for qffield callback in Recordset class
        if (method_exists($recordset, $callback_name)) {
            $method = new ReflectionMethod($recordset_class, $callback_name);
            $return_value = $method->invokeArgs($recordset, $args);
            return true;
        }
        return false;
    }

    private static final function _callback_record($recordset_class, $record, $callback_name, $args, &$return_value) {
        $recordset = self::recordset_instance($recordset_class);
        $record_class = $recordset->class_name();
        if (method_exists($record_class, $callback_name)) {
            $record = new $record_class($recordset, $record);
            $method = new ReflectionMethod($record_class, $callback_name);
            $return_value = $method->invokeArgs($record, $args);
            return true;
        }
        return false;
    }

    /**
     * Get Utils/RecordBrowser instance for current Recordset.
     * @param Module $parent_module Parent module used to create Utils/RecordBrowser instance. Usually $this.
     * @param string $unique_instance_name unique name of Utils/RecordBrowser instance.
     * @return Utils_RecordBrowser
     */
    public function create_rb_module($parent_module, $unique_instance_name = null) {
        return $parent_module->init_module('Utils/RecordBrowser', $this->tab, $unique_instance_name);
    }

    ///////////// implement Utils_RecordBrowserCommon methods //////////////

    /**
     * Get single record from recordset by id
     * @param numeric $id
     * @param bool $htmlspecialchars quote values using htmlspecialchars
     * @return RBO_Record
     */
    public function get_record($id, $htmlspecialchars = true) {
        $record = Utils_RecordBrowserCommon::get_record($this->tab, $id, $htmlspecialchars);
        return $this->record_to_object($record);
    }

    /**
     * Get records from recordset.
     * @param array $crits
     * @param array $cols
     * @param array $order
     * @param numeric $limit
     * @param bool $admin
     * @return RBO_Record[]
     */
    public function get_records($crits = array(), $cols = array(), $order = array(), $limit = array(), $admin = false) {
        $records = Utils_RecordBrowserCommon::get_records($this->tab, $crits, $cols, $order, $limit, $admin);
        return $this->array_of_records_to_array_of_objects($records);
    }

    /**
     * Get records count from recordset.
     * @param array $crits
     * @param bool $admin Admin mode - count deleted records also
     * @param array $order
     * @return int records count
     */
    public function get_records_count($crits = null, $admin = false, $order = array()) {
        return Utils_RecordBrowserCommon::get_records_count($this->tab, $crits, $admin, $order);
    }

    /**
     * Add new record to recordset, when array is supplied and return it's object
     * or just returns empty data object to fill it with data and perform save()
     * method.
     * @param array|null $array_or_null associative array of data to or null.
     * @return RBO_Record|null
     */
    public function new_record($array_or_null = null) {
        if (is_array($array_or_null)) {
            $id = Utils_RecordBrowserCommon::new_record($this->tab, $array_or_null);
            if (!is_numeric($id))
                return null;
            $array_or_null['id'] = $id;
            $array_or_null['created_by'] = Acl::get_user();
            $array_or_null[':active'] = true;
            return $this->record_to_object($array_or_null);
        }
        return $this->record_to_object(array());
    }

    /**
     * Updates record specified by id
     * @param numeric $id Id of record
     * @param array $values associative array (field => value)
     * @return bool success of records update
     */
    public function update_record($id, $values) {
        return Utils_RecordBrowserCommon::update_record($this->tab, $id, $values);
    }

    public function delete_record($id, $permanent = false) {
        return Utils_RecordBrowserCommon::delete_record($this->tab, $id, $permanent);
    }

    public function restore_record($id) {
        return Utils_RecordBrowserCommon::restore_record($this->tab, $id);
    }

    public function set_active($id, $state) {
        return Utils_RecordBrowserCommon::set_active($this->tab, $id, $state);
    }

    /**
     * Get field string representation - display callback gets called.
     * @param string $field Field id, e.g. 'first_name'
     * @param array|RBO_Record $record Records array or object
     * @param bool $nolink Do not create link
     * @return string String representation of field value
     */
    public function get_val($field, $record, $nolink = false) {
        if (is_object($record) && ($record instanceof RBO_Record))
            $record = $record->to_array();
        return Utils_RecordBrowserCommon::get_val($this->tab, $field, $record, $nolink);
    }

    /**
     * Add default permissions to recordset.
     */
    public function add_default_access() {
        Utils_RecordBrowserCommon::add_default_access($this->tab);
    }

    public function add_access($action, $clearance, $crits = array(), $blocked_fields = array()) {
        return Utils_RecordBrowserCommon::add_access($this->tab, $action, $clearance, $crits, $blocked_fields);
    }

    public function delete_access($rule_id) {
        return Utils_RecordBrowserCommon::delete_access($this->tab, $rule_id);
    }

    /**
     * Create default linked label to record
     * @param numeric $id Record's ID
     * @param bool $nolink Do not create link
     * @param bool $table_name Prepend table caption
     * @return string html with link
     */
    public function create_default_linked_label($id, $nolink = false, $table_name = true) {
        if (!is_numeric($id))
            trigger_error('Create default linked label requires proper record id.');
        return Utils_RecordBrowserCommon::create_default_linked_label($this->tab, $id, $nolink, $table_name);
    }

    /**
     * Create linked label with text values from fields.
     * @param string|array $field Fields' ids list. In array or string separated
     * by '|'. e.g. array('first_name', 'last_name') or 'first_name|last_name'.
     * @param numeric $id Record's ID
     * @param bool $nolink Do not create link
     * @return string html with link
     */
    public function create_linked_label($field, $id, $nolink = false) {
        return Utils_RecordBrowserCommon::create_linked_label($this->tab, $field, $id, $nolink);
    }

    /**
     * Create link to record with specific text.
     * @param numeric $id Record id
     * @param string $text Text to display as link
     * @param bool $nolink Do not create link switch
     * @param string $action Link to specific action. 'view' or 'edit'.
     * @return string html string with link
     */
    public function record_link($id, $text, $nolink = false, $action = 'view') {
        return Utils_RecordBrowserCommon::record_link_open_tag($this->tab, $id, $nolink, $action)
                . $text
                . Utils_RecordBrowserCommon::record_link_close_tag();
    }

    /**
     * Install recordset and create magic callbacks.
     * @return bool success
     */
    public function install() {
        $fields = array();
        foreach ($this->fields() as $def)
            $fields[] = $def->get_definition();

        $success = Utils_RecordBrowserCommon::install_new_recordset($this->tab, $fields);
        if ($success)
            $this->refresh_magic_callbacks();
        return $success;
    }

    /**
     * Uninstall recordset.
     * @return bool success
     */
    public function uninstall() {
        return Utils_RecordBrowserCommon::uninstall_recordset($this->tab);
    }

    /**
     * Add new field to RecordSet.
     * @param array|RBO_FieldDefinition $definition Field definition
     */
    public function new_record_field($definition) {
        if ($definition instanceof RBO_FieldDefinition) {
            $definition = $definition->get_definition();
        }

        Utils_RecordBrowserCommon::new_record_field($this->tab, $definition);
    }

    /**
     * Retrieve currrent caption of RecordSet.
     * @return string caption
     */
    public function get_caption() {
        return Utils_RecordBrowserCommon::get_caption($this->tab);
    }

    /**
     * Set caption of RecordSet. Caption is a text used as record set name.
     * It's displayed in several places to indicate what RecordSet you are
     * currently viewing.
     * @param string $value
     */
    public function set_caption($value) {
        Utils_RecordBrowserCommon::set_caption($this->tab, $value);
    }

    /**
     * Set record's description callback. It's used when create_default_linked_label
     * is called.
     * 
     * This callback gets record's data as first parameter and nolink directive
     * as second. Nolink directive is only for your information and you don't
     * have to create link to record manually.
     * 
     * <code>static function description($record, $nolink) { ... }</code>
     * @param callable $callback static callback method or function
     */
    public function set_description_callback($callback) {
        Utils_RecordBrowserCommon::set_description_callback($this->tab, $callback);
    }

    /**
     * Enable marking records as favorites.
     * @param bool $value enable
     */
    public function set_favorites($value) {
        Utils_RecordBrowserCommon::set_favorites($this->tab, $value);
    }

    /**
     * Enable historical view for records.
     * @param bool $value enable
     */
    public function set_full_history($value) {
        Utils_RecordBrowserCommon::set_full_history($this->tab, $value);
    }

    /**
     * Enable quickjump feature for RecordSet. You will be able to jump to
     * records that supplied here field starts with selected letter.
     * @param string $column_name
     */
    public function set_quickjump($column_name) {
        Utils_RecordBrowserCommon::set_quickjump($this->tab, $column_name);
    }

    /**
     * Set recent entries amount.
     * @param int $amount Number of records to store as recent
     */
    public function set_recent($amount) {
        Utils_RecordBrowserCommon::set_recent($this->tab, $amount);
    }

    /**
     * Set custom smarty template for RecordSet.
     * @param string $filename path to file of template.
     */
    public function set_tpl($filename) {
        Utils_RecordBrowserCommon::set_tpl($this->tab, $filename);
    }

    /**
     * Set author and creation timestamp of record. Use this function only
     * in special cases and if you are sure what you are doing.
     * @param numeric $id Record's ID
     * @param string|null $created_on Formatted date and time like date('Y-m-d H:i:s')
     * or null to omit
     * @param numeric|null $created_by Id of user from ACL module or null to omit.
     */
    public function set_record_properties($id, $created_on = null, $created_by = null) {
        $info = array();
        if ($created_on !== null)
            $info['created_on'] = $created_on;
        if ($created_by !== null)
            $info['created_by'] = $created_by;
        Utils_RecordBrowserCommon::set_record_properties($this->tab, $id, $info);
    }

    /**
     * Set RecordSet icon. Full path to module dir required,
     * e.g. 'modules/Base/Box/theme/clock.png'
     * @param string $icon_file path to file
     */
    public function set_icon($icon_file) {
        Utils_RecordBrowserCommon::set_icon($this->tab, $icon_file);
    }

    /**
     * Add new addon to this RecordSet.
     * @param string $module Callback module name
     * @param string $method Callback method name
     * @param string|callable $label String with label or callback to static
     * method which returns label.
     */
    public function new_addon($module, $method, $label) {
        Utils_RecordBrowserCommon::new_addon($this->tab, $module, $method, $label);
    }

    /**
     * Delete addon from this RecordSet.
     * @param string $module Callback module name
     * @param string $method Callback method name
     */
    public function delete_addon($module, $method) {
        Utils_RecordBrowserCommon::delete_addon($this->tab, $module, $method);
    }

    /**
     * Register processing callback to this RecordSet. It has to be static method,
     * which gets record's data as array in first argument and mode in second.
     * @param callable $callback callback to static method
     */
    public function register_processing_callback($callback) {
        Utils_RecordBrowserCommon::register_processing_callback($this->tab, $callback);
    }

    /**
     * Unregister processing callback from this RecordSet.
     * @param callable $callback callback to static method 
     */
    public function unregister_processing_callback($callback) {
        Utils_RecordBrowserCommon::unregister_processing_callback($this->tab, $callback);
    }

    /**
     * Get html formatted info about record
     * @param numeric $id Record's ID
     * @return string html with info about record
     */
    public function get_html_record_info($id) {
        return Utils_RecordBrowserCommon::get_html_record_info($this->tab, $id);
    }

    /**
     * Get info about record. 0 means deleted, 1 means active.
     * @param numeric $id Record's ID
     * @return bool record's state
     */
    public function is_active($id) {
        return Utils_RecordBrowserCommon::is_active($this->tab, $id);
    }

    /**
     * Enable filtering for specified column. Better do this during field definition.
     * But if you have to use this method, please beware of that you have to supply
     * column name, not id. e.g. <code>"First Name"</code>.
     * @param string $column_name column name. e.g. "First Name"
     */
    public function new_filter($column_name) {
        Utils_RecordBrowserCommon::new_filter($this->tab, $column_name);
    }
    
    /**
     * Disable filtering by specified field.
     * @param string $column_name column name. e.g. "First Name"
     */
    public function delete_filter($column_name) {
        Utils_RecordBrowserCommon::delete_filter($this->tab, $column_name);
    }
}

?>