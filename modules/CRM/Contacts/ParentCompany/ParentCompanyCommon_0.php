<?php
/**
 * Adds parent company field to companies.
 * @author shacky@poczta.fm
 * @copyright Telaxus LLC
 * @license MIT
 * @version 0.1
 * @package epesi-CRM/Contacts
 * @subpackage ParentCompany
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Contacts_ParentCompanyCommon extends ModuleCommon {
    public static function parent_company_crits($a,$v=array()){
        if(!isset($v['id'])) return array();
        return array('!id'=>$v['id']);
    }

}

?>