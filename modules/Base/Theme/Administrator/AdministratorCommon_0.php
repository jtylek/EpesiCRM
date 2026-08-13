<?php
/**
 * Theme_AdministratorInit_0 class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage theme-administrator
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Theme_AdministratorCommon extends Base_AdminModuleCommon {
	// AdminLTE-only: Base_BootstrapIcons::resolve() looks this up for this
	// module's icon (sidebar menu, ActionBar launcher, admin panels, module
	// indicator, etc.) instead of a central map - see
	// modules/Base/Theme/bootstrap_icons.php.
	public static function bootstrap_icon() { return 'bi-palette'; }

	public static function admin_caption() {
		return array('label'=>__('Change theme'), 'section'=>__('Server Configuration'));
	}	

	public static function body_access() {
		return Base_AclCommon::i_am_admin();
	}
	
}

?>
