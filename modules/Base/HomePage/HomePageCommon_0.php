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
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides saving any page as homepage for each user.
 * @package epesi-base-extra
 * @subpackage homepage
 */
class Base_HomePageCommon {
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
}

if($_REQUEST['Base_HomePage_load']) {
	if(Acl::is_user())
		Base_HomePageCommon::load();
	else
		$_REQUEST['box_main_module'] = Base_BoxCommon::get_main_module_name();
} elseif($_REQUEST['Base_HomePage_save']) {
	Base_HomePageCommon::save();
	unset($_REQUEST['box_main_module']);
	Base_StatusBarCommon::message(Base_LangCommon::ts('Home page saved'));
}


$session = & $base->get_session();
if(Acl::is_user()) {
	if(!$session['base_homepage_logged']) {
		Base_HomePageCommon::load();
		$session['base_homepage_logged'] = true;
	}
} else {
	if($session['base_homepage_logged']) {
		$_REQUEST['box_main_module'] = Base_BoxCommon::get_main_module_name();
		$session['base_homepage_logged'] = false;
	}
}

Base_ActionBarCommon::add_icon('home','Home page',Module::create_href(array('Base_HomePage_load'=>'1')));

?>
