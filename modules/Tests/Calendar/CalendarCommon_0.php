<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.0
 * @package epesi-tests
 * @subpackage calendar
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_CalendarCommon extends ModuleCommon {
	// AdminLTE-only: Base_BootstrapIcons::resolve() looks this up for this
	// module's icon (sidebar menu, ActionBar launcher, admin panels, module
	// indicator, etc.) instead of a central map - see
	// modules/Base/Theme/bootstrap_icons.php.
	public static function bootstrap_icon() { return 'bi-calendar3'; }

	public static function menu() {
		return array('Tests'=>array('__submenu__'=>1,'Calendar'=>array()));
	}
}

?>