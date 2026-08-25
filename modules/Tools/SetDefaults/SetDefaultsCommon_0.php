<?php
/**
 * @author Janusz Tylek <jtylek@telaxus.com>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @package epesi-tools
 * @subpackage setdefaults
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tools_SetDefaultsCommon extends ModuleCommon {

	// Was inlined in Tools_SetDefaultsInstall::install() (module-install-time
	// only, per that class's own comment on why it can't call its own Common
	// class from within install()). Moved here and now called from
	// FirstRun_0.php::done(), AFTER Base_Menu_QuickAccessCommon::
	// freeze_current_items_as_grandfathered() - calling it during install(),
	// before the freeze, silently produced zero effect: every item's
	// "default" was already 0 (nothing grandfathered yet) at that point, so
	// Base_User_SettingsCommon::save_admin()'s value==default optimization
	// deleted/no-op'd every one of these writes instead of storing a real
	// override; freezing right after then flipped every default to 1
	// (visible) with nothing on record to hold the non-curated items back
	// down - "Dashboard is full of links again" (2026-08-25). Now that this
	// runs after the freeze, '0' is a genuine departure from the (now 1)
	// default and actually persists.
	public static function apply_quickaccess_defaults() {
		$keep_enabled_dashboard = array(
			'CRM: Calendar',
			'CRM: Companies',
			'CRM: Contacts',
			'CRM: Meetings',
			'CRM: Phonecalls',
			'CRM: Tasks',
			'E-mail',
		);
		$keep_enabled_launchpad = array_merge($keep_enabled_dashboard, array(
			'Dashboard',
			'Shoutbox',
		));
		foreach (Base_Menu_QuickAccessCommon::get_options() as $opt) {
			if (!in_array($opt['label'], $keep_enabled_dashboard, true)) {
				Base_User_SettingsCommon::save_admin(Base_Menu_QuickAccessInstall::module_name(), $opt['name'] . '_d', '0');
			}
			if (!in_array($opt['label'], $keep_enabled_launchpad, true)) {
				Base_User_SettingsCommon::save_admin(Base_Menu_QuickAccessInstall::module_name(), $opt['name'] . '_l', '0');
			}
		}
	}
}

?>
