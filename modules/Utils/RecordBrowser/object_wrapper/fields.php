<?php

/**
 * @author Adam Bukowski <abukowski@telaxus.com>
 */
class RBO_Field_Text extends RBO_FieldDefinition {

    const type = 'text';

    public function __construct($display_name, $length = null) {
        $param = $length;
        parent::__construct($display_name, self::type, $param);
    }

    public function get_definition() {
        if (!is_numeric($this->param))
            trigger_error("Text field length not set", E_USER_ERROR);
        return parent::get_definition();
    }

    public function set_length($length) {
        $this->param = $length;
        return $this;
    }

}

/**
 * @author Adam Bukowski <abukowski@telaxus.com>
 */
class RBO_Field_LongText extends RBO_FieldDefinition {

    const type = 'long text';

    public function __construct($display_name) {
        parent::__construct($display_name, self::type);
    }

}

/**
 * @author Adam Bukowski <abukowski@telaxus.com>
 */
class RBO_Field_Integer extends RBO_FieldDefinition {

    const type = 'integer';

    public function __construct($display_name) {
        parent::__construct($display_name, self::type);
    }

}

/**
 * @author Adam Bukowski <abukowski@telaxus.com>
 */
class RBO_Field_Float extends RBO_FieldDefinition {

    const type = 'float';

    public function __construct($display_name) {
        parent::__construct($display_name, self::type);
    }

}

/**
 * @author Adam Bukowski <abukowski@telaxus.com>
 */
class RBO_Field_Checkbox extends RBO_FieldDefinition {

    const type = 'checkbox';

    public function __construct($display_name) {
        parent::__construct($display_name, self::type);
    }

}

/**
 * @author Adam Bukowski <abukowski@telaxus.com>
 */
class RBO_Field_Calculated extends RBO_FieldDefinition {

    const type = 'calculated';

    public function __construct($display_name, $type_in_db = null, $param_for_type = null) {
        $param = null;
        if (is_string($type_in_db))
            $param = Utils_RecordBrowserCommon::actual_db_type($type_in_db, $param_for_type);
        parent::__construct($display_name, self::type, $param);
    }

    /**
     * Set database representation of this field.
     * @param string|RBO_FieldDefinition $type name of type or field instance.
     * In case of field instance only type and it's param will be copied
     * and this function $param will be ignored.
     * @param mixed $param numeric for type text, otherwise null.
     */
    public function set_db_type($type, $param = null) {
        if ($type instanceof RBO_FieldDefinition) {
            $def = $type->get_definition();
            $type = $def['type'];
            $param = $def['param'];
        }
        $this->param = Utils_RecordBrowserCommon::actual_db_type($type, $param);
    }

}

/**
 * @author Adam Bukowski <abukowski@telaxus.com>
 */
class RBO_Field_Date extends RBO_FieldDefinition {

    const type = 'date';

    public function __construct($display_name) {
        parent::__construct($display_name, self::type);
    }

}

/**
 * @author Adam Bukowski <abukowski@telaxus.com>
 */
class RBO_Field_Timestamp extends RBO_FieldDefinition {

    const type = 'timestamp';

    public function __construct($display_name) {
        parent::__construct($display_name, self::type);
    }

}

/**
 * @author Adam Bukowski <abukowski@telaxus.com>
 */
class RBO_Field_Currency extends RBO_FieldDefinition {

    const type = 'currency';

    public function __construct($display_name) {
        parent::__construct($display_name, self::type);
    }

}

/**
 * @author Adam Bukowski <abukowski@telaxus.com>
 */
class RBO_Field_Select extends RBO_FieldDefinition {

    const type = 'select';

    public $type = self::type;
    private $linked_recordset;
    private $linked_recordset_fields = array();
    private $crits_method;
    private $advanced_properties_method;

    public function __construct($display_name, $linked_recordset = null, $linked_recordset_fields = array(), $crits_callback = null, $advanced_properties_callback = null) {
        $this->linked_recordset = $linked_recordset;
        $this->linked_recordset_fields = $linked_recordset_fields;
        $this->set_crits_callback($crits_callback);
        $this->set_advanced_properties_callback($advanced_properties_callback);
        parent::__construct($display_name, $this->type);
    }

    /**
     * Sets linked recordset to select records from.
     * @param string|RBO_Recordset $linked_recordset Recordset name or object
     * @return RBO_Field_Select
     */
    public function from($linked_recordset) {
        $this->linked_recordset = $linked_recordset instanceof RBO_Recordset ?
                $linked_recordset->table_name()
                : $linked_recordset;
        return $this;
    }

    /**
     * Set fields name to obtain display text for linked record.
     * @param string|RBO_FieldDefinition $field_name Field name which value will be shown to user.
     * @param string|RBO_FieldDefinition $_ more fields can be given as next parameters
     * @return RBO_Field_Select
     */
    public function fields($field, $_ = null) {
        $this->linked_recordset_fields = func_get_args();
        foreach ($this->linked_recordset_fields as $k => $v) {
            if ($v instanceof RBO_FieldDefinition)
                $this->linked_recordset_fields[$k] = $v->name;
        }
        return $this;
    }

    /**
     * Set crits callback.
     * 
     * For more info see Select/Multiselect parameter sectiom in RB manual.
     * @param callback $crits_callback class_name::static_method_name or array(class_name, static_method_name)
     * @return RBO_Field_Select
     */
    public function set_crits_callback($crits_callback) {
        if (is_array($crits_callback))
            $crits_callback = implode('::', $crits_callback);
        $this->crits_method = $crits_callback;
        return $this;
    }

    /**
     * Sets advanced properties callback method in params.
     * 
     * For more info see Select/Multiselect parameter sectiom in RB manual.
     * @param callback $advanced_properties_callback class_name::static_method_name or array(class_name, static_method_name)
     * @return RBO_Field_Select
     */
    public function set_advanced_properties_callback($advanced_properties_callback) {
        if (is_array($advanced_properties_callback))
            $advanced_properties_callback = implode('::', $advanced_properties_callback);
        $this->advanced_properties_method = $advanced_properties_callback;
        return $this;
    }

    private function _fill_param() {
        if (!is_string($this->linked_recordset))
            trigger_error("Linked recordset not set in select field", E_USER_ERROR);
        if (!$this->linked_recordset_fields)
            trigger_error("Fields from linked recordset not set", E_USER_ERROR);
        $param = $this->linked_recordset . "::" .
                implode('|', $this->linked_recordset_fields);
        if ($this->crits_method)
            $param .= ';' . $this->crits_method;
        if ($this->advanced_properties_method)
            $param .= ';' . $this->advanced_properties_method;
        $this->param = $param;
    }

    public function get_definition() {
        $this->_fill_param();
        return parent::get_definition();
    }

}

/**
 * @author Adam Bukowski <abukowski@telaxus.com>
 */
class RBO_Field_MultiSelect extends RBO_Field_Select {

    const type = 'multiselect';

    public function __construct($display_name, $linked_recordset = null, $linked_recordset_fields = array(), $crits_method = null, $advanced_properties_method = null) {
        $this->type = self::type;
        parent::__construct($display_name, $linked_recordset, $linked_recordset_fields, $crits_method, $advanced_properties_method);
    }

}

/**
 * @author Adam Bukowski <abukowski@telaxus.com>
 */
class RBO_Field_CommonData extends RBO_FieldDefinition {

    const type = 'commondata';

    private $chained_select_fields = array();
    private $commondata_array_name;
    private $order_by_key;

    public function __construct($display_name, $commondata_array_name = null, $order_by_key = false) {
        $this->commondata_array_name = $commondata_array_name;
        $this->order_by_key = $order_by_key;
        parent::__construct($display_name, self::type);
    }

    /**
     * Set commondata array name
     * @param string $commondata_array_name
     * @return RBO_Field_CommonData
     */
    public function from($commondata_array_name) {
        $this->commondata_array_name = $commondata_array_name;
        return $this;
    }

    /**
     * Set sorting by commondata array keys
     * @return RBO_Field_CommonData
     */
    public function set_order_by_key() {
        $this->order_by_key = $value;
        return $this;
    }

    /**
     * Set chained select on this field
     * @param RBO_FieldDefinition $field chained select field
     * @param RBO_FieldDefinition $_ several fields may be supplied
     * @return RBO_Field_CommonData
     */
    public function chained_select($field, $_ = null) {
        $this->chained_select_fields = func_get_args();
        return $this;
    }

    private function _fill_param() {
        if (!is_string($this->commondata_array_name))
            trigger_error("Commondata array name in field {$this->name} must be set!");
        $param = array($this->commondata_array_name);
        foreach ($this->chained_select_fields as $field) {
            if (!is_a($field, 'RBO_FieldDefinition'))
                trigger_error('Chained select param is not subclass of RBO_FieldDefinition', E_USER_ERROR);
            $param[] = $field->name;
        }
        if ($this->order_by_key)
            $param['order_by_key'] = true;
        $this->param = $param;
    }

    public function get_definition() {
        $this->_fill_param();
        return parent::get_definition();
    }

}

/**
 * @author Adam Bukowski <abukowski@telaxus.com>
 */
class RBO_Field_PageSplit extends RBO_FieldDefinition {

    const type = 'page_split';

    /**
     * Special field which tells to insert new tab in view. All following
     * fields will be shown under this new tab.
     * @param string $name String to display as tab name
     */
    public function __construct($name) {
        parent::__construct($name, self::type);
    }
}

?>
