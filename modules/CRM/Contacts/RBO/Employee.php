<?php

/**
 * RecordBrowser field type definition class for Employee
 * @author Adam Bukowski <abukowski@telaxus.com>
 */
class CRM_Contacts_RBO_Employee extends CRM_Contacts_RBO_Contact {

    /**
     * Employee field type
     * 
     * Don't use <b>set_crits_callback</b> because it will overwrite
     * employee crits
     * @param string $display_name Field label
     */
    public function __construct($display_name) {
        parent::__construct($display_name);
        $this->set_format_without_company();
        $this->set_crits_callback(array('CRM_ContactsCommon', 'employee_crits'));
    }

    public static function employee_crits() {
        $my_company = CRM_ContactsCommon::get_main_company();
        return array('(company_name' => $my_company, '|related_companies' => array($my_company));
    }

}

?>