<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-libs
 * @subpackage QuickForm
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

require_once('modules/Libs/QuickForm/requires.php');
require_once('modules/Libs/QuickForm/FieldTypes/automulti/automulti.php');
require_once('modules/Libs/QuickForm/FieldTypes/autoselect/autoselect.php');

class Libs_QuickFormCommon extends ModuleCommon {
	private static $on_submit = array();
	
	public static function add_on_submit_action($action) {
		self::$on_submit[] = rtrim($action,';').';';
	}
	
	public static function get_on_submit_actions() {
		$ret = '';
		foreach(self::$on_submit as $t)
			$ret .= $t;
		return $ret;
	}
	
	public static function user_settings() {
		return array(__('Forms')=>array(
			array('name'=>'autoselect_mode', 'label'=>__('Auto-select - display for empty fields'), 'type'=>'select', 'values'=>array(0=>__('Text field'), 1=>__('Select field')), 'default'=>0)
		));
	}
}
?>
