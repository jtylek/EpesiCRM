<?php
/**
 * HomePage class.
 * 
 * This class provides saving any page as homepage for each user.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @license SPL
 * @package epesi-base-extra
 * @subpackage homepage
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_HomePageCommon extends ModuleCommon {
	public static $logged;
	
	public static function load() {
		if(!Acl::is_user()) return;
		$uid = Acl::get_user();
		if($uid === null)
			return;
		$ret = DB::GetOne('SELECT url FROM home_page WHERE user_login_id=%d',$uid);
		if(!$ret) {
			$_REQUEST = array_merge($_REQUEST,Base_BoxCommon::create_href_array(null,Base_BoxCommon::get_main_module_name()));
			return;
		}
		$_SESSION['client']['__module_vars__'] = unserialize($ret);
		location(array());
	}
	
	public static function save() {
		if(!Acl::is_user()) return;
		$uid = Acl::get_user();
		$url = serialize($_SESSION['client']['__module_vars__']);
		DB::Replace('home_page',array('user_login_id'=>$uid,'url'=>$url), 'user_login_id',true);
	}
	
	public static function menu() {
		if(Acl::is_user())
			return array('My settings'=>array('__submenu__'=>1,'Set my epesi home page'=>array('Base_HomePage_save'=>'1','__module__'=>null)));
		return array();
	}
	
	public static function login_check_init() {
		self::$logged = Acl::is_user();
	}

	public static function login_check_exit() {
		$after = Acl::is_user();
		if($after!==self::$logged) {
			if($after) Base_HomePageCommon::load();
				else Base_BoxCommon::location(Base_BoxCommon::get_main_module_name());
		}
	}
	
	public static function homepage_icon() {
		Base_ActionBarCommon::add('home','Home',Module::create_href(array('Base_HomePage_load'=>'1')));
	}
}

if(isset($_REQUEST['Base_HomePage_load'])) {
	if(Acl::is_user())
		Base_HomePageCommon::load();
	else
		$_REQUEST = array_merge($_REQUEST,Base_BoxCommon::create_href_array(null,Base_BoxCommon::get_main_module_name()));
} elseif(isset($_REQUEST['Base_HomePage_save'])) {
	Base_HomePageCommon::save();
	unset($_REQUEST['box_main_href']);
	Base_StatusBarCommon::message(Base_LangCommon::ts('Base_HomePage','Home page saved'));
}

on_init(array('Base_HomePageCommon','login_check_init'));
on_exit(array('Base_HomePageCommon','login_check_exit'));

on_init(array('Base_HomePageCommon','homepage_icon'));

?>
