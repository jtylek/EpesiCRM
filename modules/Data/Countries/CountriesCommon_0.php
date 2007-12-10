<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license SPL
 * @package epesi-data
 * @subpackage countries
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Data_CountriesCommon extends Base_AdminModuleCommon {
	public static function admin_caption() {
		return "Countries";
	}
	
	public static function get() {
		return Utils_CommonDataCommon::get_array('Countries');
	}

	public static function QFfield_country(&$form, $field, $label, $mode, $default) {
		$form->addElement('commondata', $field, $label, 'Countries', array('empty_option'=>true), array('id'=>'country'));
		if ($mode!=='add') $form->setDefaults(array($field=>$default));
		else $form->setDefaults(array($field=>Base_User_SettingsCommon::get('Base_RegionalSettings','default_country')));
	}

	public static function QFfield_zone(&$form, $field, $label, $mode, $default) {
		$form->addElement('commondata', $field, $label, array('Countries', 'country'), array('empty_option'=>true), array('id'=>'zone'));
		if ($mode!=='add') $form->setDefaults(array($field=>$default));
		else $form->setDefaults(array($field=>Base_User_SettingsCommon::get('Base_RegionalSettings','default_state')));
	}

}
?>