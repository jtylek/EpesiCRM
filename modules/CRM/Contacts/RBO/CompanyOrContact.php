<?php

/**
 * RecordBrowser field type definition class for Company/Contact
 * @author Adam Bukowski <abukowski@telaxus.com>
 */
class CRM_Contacts_RBO_CompanyOrContact extends CRM_Contacts_RBO_Company {

    const type = 'crm_company_contact';

    public function __construct($display_name) {
        parent::__construct($display_name);
        $this->type = self::type;
    }

}

?>