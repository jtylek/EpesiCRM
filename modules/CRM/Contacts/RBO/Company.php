<?php

/**
 * RecordBrowser field type definition class for Company
 * @author Adam Bukowski <abukowski@telaxus.com>
 */
class CRM_Contacts_RBO_Company extends RBO_FieldDefinition {

    const type = 'crm_company';

    private $multi = false;
    private $crits_callback = null;

    public function __construct($display_name) {
        parent::__construct($display_name, self::type);
        // because field type crm_company defines own QFfield and display
        // callbacks we have to forbid checking for magic callbacks
        $this->disable_magic_callbacks();
        $this->param = array();
    }

    public function set_multiple($bool = true) {
        $this->multi = $bool;
        return $this;
    }

    public function set_crits_callback($callback) {
        $this->crits_callback = $callback;
        return $this;
    }

    public function get_definition() {
        $this->param['field_type'] = $this->multi ? 'multiselect' : 'select';
        if ($this->crits_callback)
            $this->param['crits'] = $this->crits_callback;
        return parent::get_definition();
    }

}

?>