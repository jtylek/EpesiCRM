<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2007, Janusz Tylek
 * @version 1.0
 * @license MIT
 * @package epesi-tests
 * @subpackage lang
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_LangCommon extends ModuleCommon {
	// AdminLTE-only: Base_AdminlteIcons::resolve() looks this up for this
	// module's icon (sidebar menu, ActionBar launcher, admin panels, module
	// indicator, etc.) instead of a central map - see
	// modules/Base/Theme/adminlte_icons.php.
	public static function adminlte_icon() { return 'bi-translate'; }

	public static function menu(){
		return array('Tests'=>array('__submenu__'=>1,'__weight__'=>-10, 'Lang'=>array()));
	}
}

?>
