<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-apps
 * @subpackage shoutbox
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

	public static function tray_notification($time) {
		if(!$time)
			$time = time()-24*3600;
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
