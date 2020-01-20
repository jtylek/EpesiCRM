<?php
/**
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @version 1.9.0
 * @license MIT
 * @package epesi-tools
 * @subpackage setdefaults
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tools_SetDefaultsInstall extends ModuleInstall {

	public function install() {
		
		// Setting default AB icons and launchpad
        Base_User_SettingsCommon::save_admin(Base_Menu_QuickAccessInstall::module_name(), '465e51c2b6eba36161f0115442e7406c_d', '1');
        Base_User_SettingsCommon::save_admin(Base_Menu_QuickAccessInstall::module_name(), '465e51c2b6eba36161f0115442e7406c_l', '1');
        Base_User_SettingsCommon::save_admin(Base_Menu_QuickAccessInstall::module_name(), '4fe4700ae455ac705d9d5efa1292298b_d', '1');
        Base_User_SettingsCommon::save_admin(Base_Menu_QuickAccessInstall::module_name(), '4fe4700ae455ac705d9d5efa1292298b_l', '1');
        Base_User_SettingsCommon::save_admin(Base_Menu_QuickAccessInstall::module_name(), '5ee46bef4c047bd8816ac3810270cc73_d', '1');
        Base_User_SettingsCommon::save_admin(Base_Menu_QuickAccessInstall::module_name(), '5ee46bef4c047bd8816ac3810270cc73_l', '1');
        Base_User_SettingsCommon::save_admin(Base_Menu_QuickAccessInstall::module_name(), '7b3558122283d2a14051c96a7da1fb1b_d', '1');
        Base_User_SettingsCommon::save_admin(Base_Menu_QuickAccessInstall::module_name(), '7b3558122283d2a14051c96a7da1fb1b_l', '1');
        Base_User_SettingsCommon::save_admin(Base_Menu_QuickAccessInstall::module_name(), 'b34db58caa3e6a8b933deca655640047_d', '1');
        Base_User_SettingsCommon::save_admin(Base_Menu_QuickAccessInstall::module_name(), 'b34db58caa3e6a8b933deca655640047_l', '1');

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