<?php
/**
 * 
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage Watchdog
 */
 defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_WatchdogInstall extends ModuleInstall {

	public function install() {
		Base_LangCommon::install_translations($this->get_type());
		Base_ThemeCommon::install_default_theme($this->get_type());
		$ret = true;
		$ret &= DB::CreateTable('utils_watchdog_category',
					'id I AUTO KEY,'.
					'name C(32),'.
					'callback C(128)',
			array('constraints'=>''));
		if(!$ret){
			print('Unable to create table utils_watchdog_category.<br>');
			return false;
		}
		$ret &= DB::CreateTable('utils_watchdog_event',
					'id I AUTO KEY,'.
					'category_id I,'.
					'internal_id I,'.
					'message C(64),'.
					'event_time T',
			array('constraints'=>', FOREIGN KEY (category_id) REFERENCES utils_watchdog_category(id)'));
		if(!$ret){
			print('Unable to create table utils_watchdog_event.<br>');
			return false;
		}
		$ret &= DB::CreateTable('utils_watchdog_subscription',
					'category_id I,'.
					'internal_id I,'.
					'last_seen_event I,'.
					'user_id I',
			array('constraints'=>', FOREIGN KEY (user_id) REFERENCES user_login(id)'));
		if(!$ret){
			print('Unable to create table utils_watchdog_subscription.<br>');
			return false;
		}
		$ret &= DB::CreateTable('utils_watchdog_category_subscription',
					'category_id I,'.
					'user_id I',
			array('constraints'=>', FOREIGN KEY (user_id) REFERENCES user_login(id), FOREIGN KEY (category_id) REFERENCES utils_watchdog_category(id)'));
		if(!$ret){
			print('Unable to create table utils_watchdog_category_subscription.<br>');
			return false;
		}

		DB::CreateIndex('utils_watchdog_event__cat_int__idx', 'utils_watchdog_event', array('category_id','internal_id'));
		DB::CreateIndex('utils_watchdog_subscription__cat_int__idx', 'utils_watchdog_subscription', array('category_id','internal_id'));
		DB::CreateIndex('utils_watchdog_subscription__user__idx', 'utils_watchdog_subscription', 'user_id');
		return $ret;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme($this->get_type());
		$ret = true;
		$ret &= DB::DropTable('utils_watchdog_subscription');
		$ret &= DB::DropTable('utils_watchdog_category_subscription');
		$ret &= DB::DropTable('utils_watchdog_event');
		$ret &= DB::DropTable('utils_watchdog_category');
		return $ret;
	}
	
	public function version() {
		return array("1.0");
	}
	
	public function requires($v) {
		return array(
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Utils/GenericBrowser','version'=>0));
	}
	
	public static function info() {
		return array(
			'Description'=>'',
			'Author'=>'Arkadiusz Bisaga <abisaga@telaxus.com>',
			'License'=>'MIT');
	}
	
	public static function simple_setup() {
		return false;
	}
	
}

?>