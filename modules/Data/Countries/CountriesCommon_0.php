<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-data
 * @subpackage countries
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Data_CountriesCommon extends Base_AdminModuleCommon {
	public static function admin_caption() {
		return "Countries";
	}
	
	public static function get() {
		return Utils_CommonDataCommon::get_translated_array('Countries');
	}

	public static function QFfield_country(&$form, $field, $label, $mode, $default, $desc) {
		$param = explode('::',$desc['param']['array_id']);
		foreach ($param as $k=>$v) if ($k!==0) $param[$k] = strtolower(str_replace(' ','_',$v));
		$form->addElement('commondata', $field, $label, $param, array('empty_option'=>true), array('id'=>$field));
		if ($mode!=='add') $form->setDefaults(array($field=>$default));
	}

	public static function QFfield_zone(&$form, $field, $label, $mode, $default, $desc) {
		$param = explode('::',$desc['param']['array_id']);
		foreach ($param as $k=>$v) if ($k!==0) $param[$k] = strtolower(str_replace(' ','_',$v));
		$form->addElement('commondata', $field, $label, $param, array('empty_option'=>true), array('id'=>$field));
		if ($mode!=='add') $form->setDefaults(array($field=>$default));
	}

}
?>