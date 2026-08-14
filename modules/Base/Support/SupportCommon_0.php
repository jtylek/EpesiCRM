<?php
/**
 * Get Support landing page.
 *
 * @package epesi-base
 * @subpackage support
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_SupportCommon extends ModuleCommon {
	// AdminLTE-only: Base_BootstrapIcons::resolve() looks this up for this
	// module's icon (sidebar menu, ActionBar launcher, admin panels, module
	// indicator, etc.) instead of a central map - see
	// modules/Base/Theme/bootstrap_icons.php.
	public static function bootstrap_icon() { return 'bi-life-preserver'; }

	// Replaces the old Base_Mail_ContactUsCommon-owned "EPESI Forum" external
	// link (removed from that class - see its own comment) with a real
	// in-app landing page, self-registered here same as every other Support
	// submenu entry (Base_AboutCommon, Base_HelpCommon, ...).
	public static function menu() {
		return array(_M('Support') => array('__submenu__' => 1, '__weight__' => 1000, _M('Get Support') => array()));
	}
}
?>
