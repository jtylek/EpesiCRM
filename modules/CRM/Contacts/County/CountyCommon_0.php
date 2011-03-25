<?php
/**
 * CRM County class.
 *
 * This class provides aditional fields for Contact and Comapny RecordSets.
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage contacts
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Contacts_CountyCommon extends ModuleCommon {
	public static function QFfield_county(&$form, $field, $label, $mode, $default, $desc) {
		$param = explode('::',$desc['param']['array_id']);
		foreach ($param as $k=>$v) if ($k!==0) $param[$k] = strtolower(str_replace(' ','_',$v));
		$form->addElement('commondata', $field, $label, $param, array('empty_option'=>true), array('id'=>$field));
		if ($mode!=='add') $form->setDefaults(array($field=>$default));
	}
}
?>