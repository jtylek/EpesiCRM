<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Janusz Tylek
 * @version 1.0
 * @license MIT
 * @package epesi-tests
 * @subpackage lightbox
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_LeightboxCommon extends ModuleCommon {
	// AdminLTE-only: Base_BootstrapIcons::resolve() looks this up for this
	// module's icon (sidebar menu, ActionBar launcher, admin panels, module
	// indicator, etc.) instead of a central map - see
	// modules/Base/Theme/bootstrap_icons.php.
	public static function bootstrap_icon() { return 'bi-window'; }

	public static function menu(){
		return array('Tests'=>array('__submenu__'=>1,'__weight__'=>-10, 'Leightbox page'=>array()));
	}
	public static function TEST($e){
		return $e['id'];	
	}
}

?>
