<?php
/**
 * BaseInstall class.
 *
 * This class initialization data for Base pack of module.
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage baseinstall
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class BaseInstall extends ModuleInstall {
	public function install() {
		return true;
	}

	public function uninstall() {
		return true;
	}

	public static function info() {
		return array('Author'=>'<a href="mailto:pbukowski@telaxus.com">Paul Bukowski</a> and <a href="mailto:abisaga@telaxus.com">Arkadiusz Bisaga</a> (<a href="http://www.telaxus.com">Telaxus LLC</a>)', 'License'=>'TL', 'Description'=>'Base EPESI modules pack');
	}

	public static function simple_setup() {
		return __('EPESI Core');
	}

	public function version() {
		return array('1.0');
	}

	public function requires($v) {
		return array(
		    array('name'=>Base_AdminInstall::module_name(),'version'=>0),
		    array('name'=>Base_ActionBarInstall::module_name(),'version'=>0),
		    array('name'=>Base_CronInstall::module_name(),'version'=>0),
		    array('name'=>Base_DashboardInstall::module_name(),'version'=>0),
		    array('name'=>Base_SetupInstall::module_name(),'version'=>0),
		    array('name'=>Base_EpesiStoreInstall::module_name(),'version'=>0),
		    array('name'=>Base_Lang_AdministratorInstall::module_name(),'version'=>0),
		    array('name'=>Base_Menu_QuickAccessInstall::module_name(),'version'=>0),
		    array('name'=>Base_MainModuleIndicatorInstall::module_name(),'version'=>0),
		    array('name'=>Base_MenuInstall::module_name(),'version'=>0),
		    array('name'=>Base_RegionalSettingsInstall::module_name(),'version'=>0),
		    array('name'=>Base_StatusBarInstall::module_name(),'version'=>0),
		    array('name'=>Base_SearchInstall::module_name(),'version'=>0),
            array('name'=>Base_PrintInstall::module_name(), 'version' => 0),
		    array('name'=>Base_HomePageInstall::module_name(),'version'=>0),
		    array('name'=>Base_Theme_AdministratorInstall::module_name(),'version'=>0),
		    array('name'=>Base_User_AdministratorInstall::module_name(),'version'=>0));
	}
}

?>
