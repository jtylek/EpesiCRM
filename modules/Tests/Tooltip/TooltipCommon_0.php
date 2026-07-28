<?php
/**
 * @author Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Janusz Tylek
 * @version 1.0
 * @license MIT
 * @package epesi-tests
 * @subpackage tooltip
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_TooltipCommon extends ModuleCommon {
	// AdminLTE-only: Base_AdminlteIcons::resolve() looks this up for this
	// module's icon (sidebar menu, ActionBar launcher, admin panels, module
	// indicator, etc.) instead of a central map - see
	// modules/Base/Theme/adminlte_icons.php.
	public static function adminlte_icon() { return 'bi-chat-square-dots'; }

	public static function menu() {
		return array('Tests'=>array('__submenu__'=>1,'Tooltip'=>array()));
	}
}
?>