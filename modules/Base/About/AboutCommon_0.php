<?php
/**
 * About Epesi
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage about
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_AboutCommon extends ModuleCommon {
	// AdminLTE-only: Base_BootstrapIcons::resolve() looks this up for this
	// module's icon (sidebar menu, ActionBar launcher, admin panels, module
	// indicator, etc.) instead of a central map - see
	// modules/Base/Theme/bootstrap_icons.php.
	public static function bootstrap_icon() { return 'bi-info-circle'; }

	public static function menu() {
		return array(_M('Support')=>array('__submenu__'=>1,'__weight__'=>1000,_M('About')=>array('__weight__'=>100,'__function__'=>'info')));
	}	
}

?>
