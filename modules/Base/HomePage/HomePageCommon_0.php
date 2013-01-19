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

class Base_HomePageCommon extends ModuleCommon {
	public static $logged;
	
	public static function set_home_page($homepage) {
		$args = func_get_args();
		array_shift($args);
		DB::StartTrans();
		foreach ($args as $home_page) {
			$prio = DB::GetOne('SELECT MAX(priority) FROM base_home_page') + 1;
			DB::Execute('INSERT INTO base_home_page (home_page, priority) VALUES (%s, %d)', array($homepage, $prio));
			$home_page_id = DB::Insert_ID('base_home_page', 'id');
			if (!is_array($home_page)) $home_page = array($home_page);
			foreach ($home_page as $clearance) {
				DB::Execute('INSERT INTO base_home_page_clearance (home_page_id, clearance) VALUES (%d, %s)', array($home_page_id, $clearance));
			}
		}
		DB::CompleteTrans();
	}

	public static function get_home_pages() {
		static $cache = null;
		if ($cache===null) {
			$cache = array();
			$tmp = ModuleManager::call_common_methods('home_page');
			foreach ($tmp as $k=>$v)
				$cache = array_merge($cache, $v);
		}
		return $cache;
	}
	
	public static function admin_caption() {
		return array('label'=>__('Home Page'), 'section'=>__('User Management'));
	}

	public static function login_check_exit() {
		$after = Base_AclCommon::is_user();
		if($after!==self::$logged) {
			if($after) Base_HomePageCommon::load();
				else Base_BoxCommon::location(Base_BoxCommon::get_main_module_name());
		}
	}

	public static function homepage_icon() {
		Utils_ShortcutCommon::add(array('Ctrl','H'), 'function(){'.Module::create_href_js(array('Base_HomePage_load'=>'1')).'}');
	}

	public static function get_my_homepage() {
		$clearance = Base_AclCommon::get_clearance();

		$sql = 'SELECT home_page FROM base_home_page AS bhp WHERE ';
		$vals = array();
		if ($clearance!=null) {
			$sql .= ' NOT EXISTS (SELECT * FROM base_home_page_clearance WHERE home_page_id=bhp.id AND '.implode(' AND ',array_fill(0, count($clearance), 'clearance!=%s')).')';
			$vals = array_values($clearance);
		} else {
			$sql .= ' NOT EXISTS (SELECT * FROM base_home_page_clearance WHERE home_page_id=bhp.id)';
		}
		$sql .= ' ORDER BY priority';
		$page = DB::GetOne($sql, $vals);
		$pages = self::get_home_pages();
		return isset($pages[$page])?$pages[$page]:array();
	}
	
	public static function get_href() {
		return Module::create_href(array('Base_HomePage_load'=>'1'));
	}
}

if (isset($_REQUEST['Base_HomePage_load'])) {
	unset($_REQUEST['Base_HomePage_load']);
	$_REQUEST = array_merge($_REQUEST,Base_BoxCommon::create_href_array(null,Base_BoxCommon::get_main_module_name()));
}
on_init(array('Base_HomePageCommon','homepage_icon'));

?>
