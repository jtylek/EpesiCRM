<?php

/**
 * RecordBrowser field type definition class for Contact
 * @author Adam Bukowski <abukowski@telaxus.com>
 */
class CRM_Contacts_RBO_Contact extends CRM_Contacts_RBO_Company {

    const type = 'crm_contact';

    private $format_callback = null;

    public function __construct($display_name) {
        parent::__construct($display_name);
        $this->type = self::type;
    }

    public function set_format_callback($callback) {
        $this->format_callback = $callback;
        return $this;
    }

    public function set_format_without_company() {
        $this->format_callback = array('CRM_ContactsCommon', 'contact_format_no_company');
        return $this;
    }

    public function get_definition() {
        if ($this->format_callback)
            $this->param['format'] = $this->format_callback;
        return parent::get_definition();
    }

}

?>