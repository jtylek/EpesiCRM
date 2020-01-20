<?php
/**
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-data
 * @subpackage countries
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Data_CountriesCommon extends Base_AdminModuleCommon {
	public static function admin_caption() {
		return array('label'=>__('Countries'), 'section'=>__('Regional Settings'));
	}
	
	public static function get() {
		return Utils_CommonDataCommon::get_translated_array('Countries');
	}

	public static function QFfield_country(&$form, $field, $label, $mode, $default, $desc) {
		$param = explode('::',$desc['param']['array_id']);
		foreach ($param as $k=>$v) if ($k!==0) $param[$k] = strtolower(str_replace(' ','_',$v));
		$order = $desc['param']['order'];
		$form->addElement('commondata', $field, $label, $param, array('empty_option'=>true, 'order' => $order), array('id'=>$field));
		if ($mode!=='add') $form->setDefaults(array($field=>$default));
	}

	public static function QFfield_zone(&$form, $field, $label, $mode, $default, $desc) {
		$param = explode('::',$desc['param']['array_id']);
		foreach ($param as $k=>$v) if ($k!==0) $param[$k] = strtolower(str_replace(' ','_',$v));
		$order = $desc['param']['order'];
		$form->addElement('commondata', $field, $label, $param, array('empty_option'=>true, 'order' => $order), array('id'=>$field));
		if ($mode!=='add') $form->setDefaults(array($field=>$default));
	}

}
?>