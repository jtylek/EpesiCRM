<?php

/**
 * Field definition of RB wrapper
 *
 * @author Adam Bukowski <abukowski@telaxus.com>
 */
class RBO_FieldDefinition {

    public $name;
    public $type;
    public $param;
    public $extra;
    public $required;
    public $visible;
    public $filter;
    public $display_callback;
    public $QFfield_callback;
    public $position;

    /**
     * Creates new generic FieldDefinition object.
     * Check if there is specific class for field type and use it, as it's
     * simpler to use, more readable and flexible. Look for RBO_Field_*
     * 
     * @link http://www.epesi.org/index.php?title=Utils/RecordBrowser#Field_Properties
     * @param string $display_name field name
     * @param string $type field type
     * @param string|array $param parameters specific to field type
     * @param bool $extra extra field
     * @param bool $required is required
     * @param bool $visible is visible
     * @param bool $filter is used to filtering
     * @param callable $display_callback display callback
     * @param callable $QFfield_callback QFfield callback
     * @param numeric|string $position position - use only to alter existing RecordSet
     */
    function __construct($display_name, $type, $param = null, $extra = false, $required = false, $visible = false, $filter = false, $display_callback = null, $QFfield_callback = null, $position = null) {
        $this->name = $display_name;
        $this->type = $type;
        $this->param = $param;
        $this->extra = $extra;
        $this->required = $required;
        $this->visible = $visible;
        $this->filter = $filter;
        $this->display_callback = $display_callback;
        $this->QFfield_callback = $QFfield_callback;
        $this->position = $position;
    }

    /**
     * Get definition array for use in RB. Or just use it to get all field
     * properties as array.
     * @return array
     */
    function get_definition() {
        return get_object_vars($this);
    }

    /**
     * Sets field as 'extra', which has several results:
     * - in view and edit modes is displayed below.
     * - can be managed by super administrator from GUI.
     * @return \RBO_FieldDefinition
     */
    function set_extra() {
        $this->extra = true;
        return $this;
    }

    /**
     * Sets field as required.
     * @return \RBO_FieldDefinition
     */
    function set_required() {
        $this->required = true;
        return $this;
    }

    /**
     * Sets field visible in 'browse' view - tabular view of several records.
     * @return \RBO_FieldDefinition
     */
    function set_visible() {
        $this->visible = true;
        return $this;
    }

    /**
     * Enable filtering data by this field.
     * @return \RBO_FieldDefinition
     */
    function set_filter() {
        $this->filter = true;
        return $this;
    }

    /**
     * Sets custom display callback.
     * @param type $callback
     * @return \RBO_FieldDefinition
     */
    function set_display_callback($callback) {
        $this->display_callback = $callback;
        return $this;
    }

    /**
     * Sets custom QFfield callback.
     * @param callable $callback
     * @return \RBO_FieldDefinition
     */
    function set_QFfield_callback($callback) {
        $this->QFfield_callback = $callback;
        return $this;
    }

    /**
     * Set position of new field relative to old fields.
     * 
     * <b>Use only when adding new fields to existing RecordSet</b>
     * 
     * @param numeric|string|RBO_FieldDefinition $position
     * May be numeric - starting from 1. Field with position = 1 will be set
     * as first field in view. With position = 2 as second, etc.
     * All following fields order will remain as it was.
     * 
     * Better option is to supply here name of field (e.g. 'First Name')
     * as it was set in field definition. New field will be set just before
     * supplied field.
     * 
     * Best way is to supply RBO_FieldDefinition object. New field will
     * be placed as for field name parameter - just before field supplied here.
     * @return \RBO_FieldDefinition
     */
    function set_position($position) {
        if (is_object($position) && is_a($position, 'RBO_FieldDefinition'))
            $this->position = $position->name;
        else
            $this->position = $position;
        return $this;
    }

}

?>