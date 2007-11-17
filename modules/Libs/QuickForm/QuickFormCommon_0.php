<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license SPL
 * @package epesi-libs
 * @subpackage QuickForm
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

require_once('requires.php');

class Libs_QuickFormCommon extends ModuleCommon {
	private static $on_submit = '';
	
	public static function add_on_submit_action($action) {
		self::$on_submit .=	$action.';';
	}
	
	public static function get_on_submit_actions() {
		return self::$on_submit;
	}
}
?>
