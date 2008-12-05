<?php
/**
 * Shows who is logged to epesi.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-tools
 * @subpackage WhoIsOnline
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tools_WhoIsOnlineCommon extends ModuleCommon {
	public static function applet_caption() {
		return "Who is online";
	}

	public static function applet_info() {
		return "Shows online users";
	}
	
	public static function body_access() {
		return Acl::is_user();
	}
	
	public static function user_settings() {
		return array('Misc'=>array(
			array('name'=>'show_me','type'=>'checkbox','label'=>'Show me in online users','default'=>1)
			));
	}
	
	public static function get() {
		DB::Execute('delete from tools_whoisonline_users where session_name not in (select name from session)');
		$ret = DB::Execute('SELECT DISTINCT ul.login FROM tools_whoisonline_users twu INNER JOIN user_login ul on ul.id=twu.user_login_id');
		$all = array();
		while($r = $ret->FetchRow())
			if($r['login']!=Acl::get_user()) $all[] = $r['login'];
		return $all;
	}
}
if(!isset($_SESSION['tools_whoisonline']) || $_SESSION['tools_whoisonline']!=Acl::get_user()) {
	if(Base_User_SettingsCommon::get('Tools_WhoIsOnline','show_me'))
		@DB::Execute('INSERT INTO tools_whoisonline_users(session_name,user_login_id) VALUES(%s,%d)',array(session_id(),Acl::get_user()));
	$_SESSION['tools_whoisonline']=Acl::get_user();
}
?>