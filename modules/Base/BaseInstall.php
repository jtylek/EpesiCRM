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
		    array('name'=>Base_Admin::module_name(),'version'=>0),
		    array('name'=>Base_ActionBar::module_name(),'version'=>0),
		    array('name'=>Base_Cron::module_name(),'version'=>0),
		    array('name'=>Base_Dashboard::module_name(),'version'=>0),
		    array('name'=>Base_Help::module_name(),'version'=>0),
		    array('name'=>Base_Setup::module_name(),'version'=>0),
		    array('name'=>Base_EpesiStore::module_name(),'version'=>0),
		    array('name'=>Base_Lang_Administrator::module_name(),'version'=>0),
		    array('name'=>Base_Menu_QuickAccessInstall::module_name(),'version'=>0),
		    array('name'=>Base_MainModuleIndicator::module_name(),'version'=>0),
		    array('name'=>Base_Menu::module_name(),'version'=>0),
		    array('name'=>Base_RegionalSettingsInstall::module_name(),'version'=>0),
		    array('name'=>Base_StatusBar::module_name(),'version'=>0),
		    array('name'=>Base_Search::module_name(),'version'=>0),
            array('name'=>Base_Print::module_name(), 'version' => 0),
		    array('name'=>Base_HomePage::module_name(),'version'=>0),
		    array('name'=>Base_Theme_Administrator::module_name(),'version'=>0),
		    array('name'=>Base_User_Administrator::module_name(),'version'=>0));
	}
}

?>
