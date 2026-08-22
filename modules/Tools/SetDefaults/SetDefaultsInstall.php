<?php
/**
 * @author Janusz Tylek <jtylek@telaxus.com>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @version 1.0
 * @license MIT
 * @package epesi-tools
 * @subpackage setdefaults
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tools_SetDefaultsInstall extends ModuleInstall {

	public function install() {

        // Every Quick Access item defaults to on (see Base_Menu_QuickAccessCommon::
        // user_settings() - 'default'=>1 for both the Dashboard/ActionBar and
        // Launchpad columns) for both new users, so a curated "limited by
        // default" set needs the unwanted items switched off explicitly rather
        // than the wanted ones switched on. Matched by label (not name/hash)
        // since the hash is an md5() of each module's own, undocumented raw
        // menu key - the label is the only stable, inspectable identifier
        // available without running the app. Inlined here rather than in a
        // Tools_SetDefaultsCommon helper: ModuleManager only registers a
        // module (making its *Common class autoloadable) after install()
        // returns, so this module can't call its own Common class from
        // within its own install().
        // The two columns aren't a single on/off per row: Dashboard and
        // Shoutbox are Launchpad-only (no point pinning either to the
        // Dashboard/ActionBar itself), so they get their own list layered on
        // top of the shared core-CRM set.
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

		// default applets
		DB::Execute('INSERT INTO base_dashboard_default_applets (id, module_name, col, pos, color, tab) VALUES (%d, %s, %d, %d, %d, %d)', array(1,'Applets_Clock',2,0,1,1));
		DB::Execute('INSERT INTO base_dashboard_default_applets (id, module_name, col, pos, color, tab) VALUES (%d, %s, %d, %d, %d, %d)', array(2,'CRM_Tasks',1,0,6,1));
		DB::Execute('INSERT INTO base_dashboard_default_applets (id, module_name, col, pos, color, tab) VALUES (%d, %s, %d, %d, %d, %d)', array(3,'CRM_PhoneCall',1,1,8,1));
		DB::Execute('INSERT INTO base_dashboard_default_applets (id, module_name, col, pos, color, tab) VALUES (%d, %s, %d, %d, %d, %d)', array(4,'Applets_Note',2,0,10,1));
		DB::Execute('INSERT INTO base_dashboard_default_applets (id, module_name, col, pos, color, tab) VALUES (%d, %s, %d, %d, %d, %d)', array(5,'CRM_Calendar',1,2,0,1));
		DB::Execute('INSERT INTO base_dashboard_default_applets (id, module_name, col, pos, color, tab) VALUES (%d, %s, %d, %d, %d, %d)', array(6,'Apps_Shoutbox',0,1,0,1));
		DB::Execute('INSERT INTO base_dashboard_default_applets (id, module_name, col, pos, color, tab) VALUES (%d, %s, %d, %d, %d, %d)', array(7,'Utils_Watchdog',0,0,6,1));


		//default note
		DB::Execute('INSERT INTO base_dashboard_default_settings (applet_id,name,value) VALUES (%d, %s, %s)', array(4,'bcolor','nice-yellow'));
		DB::Execute('INSERT INTO base_dashboard_default_settings (applet_id,name,value) VALUES (%d, %s, %s)',  array(4,'text','<div><strong>'.__('Congratulations!').'</strong><br />'.__('You\'ve just installed EPESI!').'</div><div>'.__('For more information, help and support please visit %sEPESI website %s', array('<a href="http://epe.si" target="_blank">','</a></div>'))));
		DB::Execute('INSERT INTO base_dashboard_default_settings (applet_id,name,value) VALUES (%d, %s, %s)', array(4,'title',__('Welcome')));

		// default favorites and subscriptions
        Base_User_SettingsCommon::save_admin('Utils_RecordBrowser', 'company_auto_fav', '1');
        Base_User_SettingsCommon::save_admin('Utils_RecordBrowser', 'company_auto_subs', '1');
        Base_User_SettingsCommon::save_admin('Utils_RecordBrowser', 'contact_auto_fav', '1');
        Base_User_SettingsCommon::save_admin('Utils_RecordBrowser', 'contact_auto_subs', '1');
        Base_User_SettingsCommon::save_admin('Utils_RecordBrowser', 'phonecall_auto_subs', '1');
        Base_User_SettingsCommon::save_admin('Utils_RecordBrowser', 'task_auto_subs', '1');

		return true;
	}
	
	public function uninstall() {
		return true;
	}
	
	public function version() {
		return array("1.0");
	}
	
	public function requires($v) {
		return array(
			array('name'=>Base_ThemeInstall::module_name(),'version'=>0),
			array('name'=>Base_LangInstall::module_name(),'version'=>0),
			array('name'=>Utils_WizardInstall::module_name(),'version'=>0),
			array('name'=>CRM_CalendarInstall::module_name(),'version'=>0),
			array('name'=>CRM_ContactsInstall::module_name(),'version'=>0),
			array('name'=>CRM_PhoneCallInstall::module_name(),'version'=>0),
			);
	}
}

?>