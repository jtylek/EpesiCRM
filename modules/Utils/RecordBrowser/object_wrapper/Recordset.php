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
        if (!array_key_exists('RBO_Record', class_parents($this->class)) || !($this->class == 'RBO_Record'))
            trigger_error('Record class (' . $this->class . ') for recordset ' . $this->tab . ' is not instance of RBO_Record', E_USER_ERROR);
    }

    private function record_to_object($array) {
        if (is_array($array)) {
            return new $this->class($this, $array);
        }
        return null;
    }

    private function array_of_records_to_array_of_objects($array) {
        foreach ($array as & $entry) {
            $entry = $this->record_to_object($entry);
        }
        return $array;
    }

    protected static function create_record_object($recordset_class, $record) {
        $recordset = new $recordset_class();
        $record_class = $recordset->class_name();
        return new $record_class($recordset, $record);
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
        return self::_generic_magic_callback($recordset_class, 'QFfield_' . $field, $rb_obj->record, $args);
    }

    public static final function __display_magic_callback($record, $nolink, $desc) {
        list($recordset_class, $method) = Utils_RecordBrowser::$rb_obj->get_display_method($desc['name']);
        $args = func_get_args();
        $args[0] = self::create_record_object($recordset_class, $record);
        return self::_generic_magic_callback($recordset_class, 'display_' . $desc['id'], $record, $args);
    }

    private static final function _generic_magic_callback($recordset_class, $callback_name, $record, $args) {
        $recordset = new $recordset_class();
        // check for qffield callback in Recordset class
        if (method_exists($recordset, $callback_name)) {
            $method = new ReflectionMethod($recordset_class, $callback_name);
            return $method->invokeArgs($recordset, $args);
        }
        // next check for qffield callback in Record class
        $record_class = $recordset->class_name();
        if (method_exists($record_class, $callback_name)) {
            $record = new $record_class($recordset, $record);
            $method = new ReflectionMethod($record_class, $callback_name);
            return $method->invokeArgs($record, $args);
        }
        trigger_error("Method $callback_name does not exist in class $recordset_class, nor $record_class", E_USER_ERROR);
    }
    
    /**
     * Get Utils/RecordBrowser instance for current Recordset.
     * @param Module $parent_module Parent module used to create Utils/RecordBrowser instance. Usually $this.
     * @param string $unique_instance_name unique name of Utils/RecordBrowser instance.
     * @return Utils_RecordBrowser
     */
    public function create_rb_object($parent_module, $unique_instance_name = null){
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
    
    /**
     * Get field string representation - display callback gets called.
     * @param string $field Exact field name as defined during install. e.g. 'Company Name'
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

    public function create_default_linked_label($id, $nolink = false, $table_name = true) {
        if (!is_numeric($id))
            trigger_error('Create default linked label requires proper record id.');
        Utils_RecordBrowserCommon::create_linked_label_r($tab, $col, $r);
        return Utils_RecordBrowserCommon::create_default_linked_label($this->tab, $id, $nolink, $table_name);
    }

    public function create_linked_label($field, $id, $nolink = false) {
        return Utils_RecordBrowserCommon::create_linked_label($this->tab, $field, $id, $nolink);
    }

    public function install() {
        $fields = array();
        foreach ($this->fields() as $def)
            $fields[] = $def->get_definition();

        $success = Utils_RecordBrowserCommon::install_new_recordset($this->tab, $fields);
        if ($success)
            $this->refresh_magic_callbacks();
        return $success;
    }

    public function uninstall() {
        return Utils_RecordBrowserCommon::uninstall_recordset($this->tab);
    }

    public function new_record_field($definition) {
        if (!is_array($definition)
                && is_a($definition, 'RBO_FieldDefinition')) {
            $definition = $definition->get_definition();
        }

        return Utils_RecordBrowserCommon::new_record_field($this->tab, $definition);
    }

    public function get_caption() {
        return Utils_RecordBrowserCommon::get_caption($this->tab);
    }

    public function set_caption($value) {
        Utils_RecordBrowserCommon::set_caption($this->tab, $value);
    }

    public function set_record_properties($id, $created_on = null, $created_by = null) {
        $info = array();
        if ($created_on !== null)
            $info['created_on'] = $created_on;
        if ($created_by !== null)
            $info['created_by'] = $created_by;
        Utils_RecordBrowserCommon::set_record_properties($this->tab, $id, $info);
    }

    public function set_icon($icon_file) {
        Utils_RecordBrowserCommon::set_icon($this->tab, $icon_file);
    }

}

?>