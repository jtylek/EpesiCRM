<?php
/**
 * Example event module
 * @author Janusz Tylek and Claude Code AI
 * @version 2.0
 * @license MIT
 * @package epesi-tests
 * @subpackage callbacks
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_CallbacksCommon extends ModuleCommon {
	// AdminLTE-only: Base_BootstrapIcons::resolve() looks this up for this
	// module's icon (sidebar menu, ActionBar launcher, admin panels, module
	// indicator, etc.) instead of a central map - see
	// modules/Base/Theme/bootstrap_icons.php.
	public static function bootstrap_icon() { return 'bi-arrow-return-right'; }

	public static function menu() {
		return array('Tests'=>array('__submenu__'=>1,'Callbacks page'=>array()));
	}
}

?>