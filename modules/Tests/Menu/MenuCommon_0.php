<?php
/**
 * @author Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Janusz Tylek
 * @version 1.0
 * @license MIT
 * @package epesi-tests
 * @subpackage menu
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_MenuCommon extends ModuleCommon {
	// AdminLTE-only: Base_BootstrapIcons::resolve() looks this up for this
	// module's icon (sidebar menu, ActionBar launcher, admin panels, module
	// indicator, etc.) instead of a central map - see
	// modules/Base/Theme/bootstrap_icons.php.
	public static function bootstrap_icon() { return 'bi-list'; }

	public static function menu() {
		return array('Tests'=>array('__submenu__'=>1,'Menu'=>array()));
	}

	public static function quick_menu() {
		return array('Quick menu test'=>array('action'=>'ble'));
	}
}
?>