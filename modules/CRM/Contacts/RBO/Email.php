<?php


/**
 * RecordBrowser field type definition class for Email
 * @author Adam Bukowski <abukowski@telaxus.com>
 */
class CRM_Contacts_RBO_Email extends RBO_FieldDefinition {

    const type = 'email';

    public function __construct($display_name) {
        parent::__construct($display_name, self::type);
        $this->disable_magic_callbacks();
        $this->param = array();
    }

    /**
     * Force uniqueness of this field according to recordset
     * @param bool $bool value. By default field is not unique.
     * @return CRM_Contacts_RBO_Email
     */
    public function set_unique($bool = true) {
        $this->param['unique'] = $bool;
        return $this;
    }
}

?>