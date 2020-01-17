<?php


/**
 * RecordBrowser field type definition class for Phone
 * @author  Janusz Tylek <j@epe.si>
 */
class CRM_Contacts_RBO_Phone extends RBO_Field_Text {

    public function __construct($display_name) {
        parent::__construct($display_name, 64);
        $this->display_callback = array('CRM_ContactsCommon', 'display_phone');
    }

}

?>