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

    function __construct($display_name, $type, $param = null, $extra = false, $required = false, $visible = false, $filter = false, $display_callback = null, $QFfield_callback = null) {
        $this->name = $display_name;
        $this->type = $type;
        $this->param = $param;
        $this->extra = $extra;
        $this->required = $required;
        $this->visible = $visible;
        $this->filter = $filter;
        $this->display_callback = $display_callback;
        $this->QFfield_callback = $QFfield_callback;
    }

    function get_definition() {
        return get_object_vars($this);
    }

    function set_extra() {
        $this->extra = true;
        return $this;
    }

    function set_required() {
        $this->required = true;
        return $this;
    }

    function set_visible() {
        $this->visible = true;
        return $this;
    }

    function set_filter() {
        $this->filter = true;
        return $this;
    }

    function set_display_callback($callback) {
        $this->display_callback = $callback;
    }

    function set_QFfield_callback($callback) {
        $this->QFfield_callback = $callback;
    }

}

?>