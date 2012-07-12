<?php
/**
 * HomePage class.
 *
 * This class provides saving any page as homepage for each user.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage homepage
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_HomePageCommon extends ModuleCommon {
	public static $logged;

	public static function load() {
		if(!Base_AclCommon::is_user()) return;
		$uid = Base_AclCommon::get_user();
		if($uid === null)
			return;
		$ret = DB::GetOne('SELECT url FROM home_page WHERE user_login_id=%d',$uid);
		if(!$ret) {
			$_REQUEST = array_merge($_REQUEST,Base_BoxCommon::create_href_array(null,Base_BoxCommon::get_main_module_name()));
			return;
		}
		$_SESSION['client']['__module_vars__'] = unserialize($ret);
		$_REQUEST['__homepage_req_session__'] = 1;
		location(array('__homepage__'=>1));
	}

	public static function save() {
		if(!Base_AclCommon::is_user()) return;
		$uid = Base_AclCommon::get_user();
		
		$m = Module::static_get_module_variable('/Base_Box|0','main');
		$v = end($m);
		if(str_replace('_','/',$v['module']) == Base_BoxCommon::get_main_module_name()) {
			$url = '';
		} else {
			$url = serialize($_SESSION['client']['__module_vars__']);
		}
		DB::Replace('home_page',array('user_login_id'=>$uid,'url'=>$url), 'user_login_id',true);
	}

	public static function menu() {
		return array(_M('My settings')=>array('__submenu__'=>1,_M('Set home page')=>array('Base_HomePage_save'=>'1','__module__'=>null)));
	}

	public static function login_check_init() {
		self::$logged = Base_AclCommon::is_user();
	}

	public static function login_check_exit() {
		$after = Base_AclCommon::is_user();
		if($after!==self::$logged) {
			if($after) Base_HomePageCommon::load();
				else Base_BoxCommon::location(Base_BoxCommon::get_main_module_name());
		}
	}

	public static function homepage_icon() {
//		Base_ActionBarCommon::add('home',__('Home'),Module::create_href(array('Base_HomePage_load'=>'1')));
		Utils_ShortcutCommon::add(array('Ctrl','H'), 'function(){'.Module::create_href_js(array('Base_HomePage_load'=>'1')).'}');
	}
	
	public static function get_href() {
		return Module::create_href(array('Base_HomePage_load'=>'1'));
	}
}

if(isset($_REQUEST['Base_HomePage_load'])) {
	if(Base_AclCommon::is_user())
		Base_HomePageCommon::load();
	else
		$_REQUEST = array_merge($_REQUEST,Base_BoxCommon::create_href_array(null,Base_BoxCommon::get_main_module_name()));
} elseif(isset($_REQUEST['Base_HomePage_save'])) {
	unset($_REQUEST['box_main_href']);
	if (DEMO_MODE) {
		Base_StatusBarCommon::message(__('You can\'t change home page in demo mode'), 'warning');
	} else {
		Base_HomePageCommon::save();
		Base_StatusBarCommon::message(__('Home page saved'));
	}
}

on_init(array('Base_HomePageCommon','login_check_init'));
on_exit(array('Base_HomePageCommon','login_check_exit'));

on_init(array('Base_HomePageCommon','homepage_icon'));

?>
