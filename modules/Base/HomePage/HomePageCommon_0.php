<?php
/**
 * HomePage class.
 * 
 * This class provides saving any page as homepage for each user.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @licence SPL
 * @package epesi-base-extra
 * @subpackage homepage
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_HomePageCommon {
	public static $logged;
	
	public static function load() {
		global $base;
		if(!Acl::is_user()) return;
		$uid = Base_UserCommon::get_user_id(Acl::get_user());
		if($uid == '')
			return;
		$session = & $base->get_session();
		$ret = DB::Execute('SELECT url FROM home_page WHERE user_login_id=%d',$uid);
		if(!($row = $ret->FetchRow())) {
			$_REQUEST['box_main_module'] = Base_BoxCommon::get_main_module_name();
			return;
		}
		parse_str($row[0], $session['__module_vars__']);
		location(array());
	}
	
	public static function save() {
		if(!Acl::is_user()) return;
		global $base;
		$uid = Base_UserCommon::get_user_id(Acl::get_user());
		$session = & $base->get_session();
		$url = http_build_query($session['__module_vars__']);
		DB::Execute('INSERT INTO home_page VALUES(%d, %s) ON DUPLICATE KEY UPDATE url=%s',array($uid, $url, $url));
	}
	
	public static function tool_menu() {
		if(Acl::is_user())
			return array('Set as your epesi home page'=>array('Base_HomePage_save'=>'1'));
		return array();
	}
	
	public static function login_check_init() {
		self::$logged = Acl::is_user();
	}

	public static function login_check_exit() {
		$after = Acl::is_user();
		if($after!==self::$logged) {
			if($after) Base_HomePageCommon::load();
				else location(array('box_main_module'=>Base_BoxCommon::get_main_module_name()));
		}
	}
	
	public static function homepage_icon() {
		Base_ActionBarCommon::add('home','Home page',Module::create_href(array('Base_HomePage_load'=>'1')));
	}
}

if(isset($_REQUEST['Base_HomePage_load'])) {
	if(Acl::is_user())
		Base_HomePageCommon::load();
	else
		$_REQUEST['box_main_module'] = Base_BoxCommon::get_main_module_name();
} elseif(isset($_REQUEST['Base_HomePage_save'])) {
	Base_HomePageCommon::save();
	unset($_REQUEST['box_main_module']);
	Base_StatusBarCommon::message(Base_LangCommon::ts('Base_HomePage','Home page saved'));
}

on_init(array('Base_HomePageCommon','login_check_init'));
on_exit(array('Base_HomePageCommon','login_check_exit'));

on_init(array('Base_HomePageCommon','homepage_icon'));

?>
