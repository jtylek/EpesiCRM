<?php
/**
 * Setup initial class
 * 
 * This file contains default database and setup module initialization data.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage setup
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_SetupInstall extends ModuleInstall {
	public function version() {
		return array('1.0.0');
	}
	
	public function install() {		
		$ret = DB::CreateTable('available_modules','name C(128), vkey I NOTNULL, version C(64) NOTNULL',array('constraints'=>', PRIMARY KEY(name, vkey)'));
		if($ret===false)
			die('Invalid SQL query - Setup module (modules table)');
		$ret = Variable::set('anonymous_setup',true);
		if($ret === false) {
			print('Invalid SQL query - Setup module (populating variables)');
			return false;
		}
		$ret = Variable::set('simple_setup',1);
		if($ret === false) {
			print('Invalid SQL query - Setup module (populating variables)');
			return false;
		}
		
		$this->create_data_dir();
		Base_ThemeCommon::install_default_theme($this->get_type());
		
		return true;
	}

	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme($this->get_type());
		return (DB::DropTable('available_modules') && Variable::delete('anonymous_setup') && Variable::delete('simple_setup'));
	}
	public function requires($v) {
		return array (
			array('name'=>'Base/Theme','version'=>0),
			array('name'=>'Libs/QuickForm','version'=>0),
			array('name'=>'Libs/Leightbox','version'=>0),
			array('name'=>'Utils/Tree','version'=>0)
		);
	}
}
?>
