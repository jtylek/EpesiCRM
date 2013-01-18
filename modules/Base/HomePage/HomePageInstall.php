<?php
/**
 * HomePage class.
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage homepage
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_HomePageInstall extends ModuleInstall {
	public function install() {
		DB::CreateTable('base_home_page',
			'id I4 AUTO KEY,'.
			'priority I4,'.
			'home_page C(64)',
			array('constraints' => ''));
		DB::CreateTable('base_home_page_clearance',
			'id I4 AUTO KEY,'.
			'home_page_id I,'.
			'clearance C(64)',
			array('constraints' => ', FOREIGN KEY (home_page_id) REFERENCES base_home_page(id)'));
		return true;
	}
	
	public function uninstall() {
		DB::DropTable('base_home_page');
		DB::DropTable('base_home_page_clearance');
		return true;
	}
	
	public function version() {
		return array('1.0');
	}

	public static function simple_setup() {
		return __('EPESI Core');
	}

	public function requires($v) {
		return array(array('name'=>'Base/Box','version'=>0), 
			array('name'=>'Base/Lang', 'version'=>0),
			array('name'=>'Utils/Shortcut', 'version'=>0), 
			array('name'=>'Base/User', 'version'=>0),
			array('name'=>'Base/ActionBar', 'version'=>0)
			);
	}
	
}

?>
