<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package apps-shoutbox
 * @license SPL
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_ShoutboxCommon extends ModuleCommon {
	public static function menu() {
		return array('Shoutbox'=>array());
	}
	
	public static function applet_caption() {
		return "Shoutbox";
	}

	public static function applet_info() {
		return "Mini shoutbox"; //here can be associative array
	}

	public static function tray_notification() {
		$time = DB::GetOne('SELECT call_on FROM apps_shoutbox_notifications WHERE base_user_login_id=%d',array(Acl::get_user()));
		$t = time();
		if(!$time) {
			$time = $t-24*3600;
			DB::Execute('INSERT INTO apps_shoutbox_notifications(call_on,base_user_login_id) VALUES (%T,%d)',array($t,Acl::get_user()));
		} else
			DB::Execute('UPDATE apps_shoutbox_notifications SET call_on=%T WHERE base_user_login_id=%d',array($t,Acl::get_user()));
		$arr = DB::GetAll('SELECT ul.login, asm.id, asm.message, asm.posted_on FROM apps_shoutbox_messages asm LEFT JOIN user_login ul ON ul.id=asm.base_user_login_id WHERE asm.posted_on>=%T AND asm.base_user_login_id!=%d ORDER BY asm.posted_on DESC LIMIT 10',array($time, Acl::get_user()));
		if(empty($arr)) return array();
		//print it out
		$ret = array();
		foreach($arr as $row) {
			if(!$row['login']) $row['login']='Anonymous';
			$ret['shoutbox_'.$row['id']] = Base_LangCommon::ts('Apps_Shoutbox','<font color="gray">[%s]</font><font color="blue">%s</font>: %s',array(Base_RegionalSettingsCommon::time2reg($row['posted_on']), $row['login'], $row['message']));
		}

		return array('notifications'=>$ret);
	}
}
?>
