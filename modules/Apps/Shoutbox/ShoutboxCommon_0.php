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
		$arr = DB::GetAll('SELECT ul.login, asm.message, asm.posted_on FROM apps_shoutbox_messages asm LEFT JOIN user_login ul ON ul.id=asm.base_user_login_id WHERE asm.posted_on>%T ORDER BY asm.posted_on DESC LIMIT 50',array(time()-45));
		if(empty($arr)) return array();
		//print it out
		$ret = '';
		foreach($arr as $row) {
			if(!$row['login']) $row['login']='Anonymous';
			$ret .= Base_LangCommon::ts('Apps_Shoutbox','<font color="gray">[%s]</font><font color="blue">%s</font>: %s',array(Base_RegionalSettingsCommon::time2reg($row['posted_on']), $row['login'], $row['message'])).'<br>';
		}

		return array('notifications'=>array(Base_LangCommon::ts('Apps_Shoutbox','Shoutbox:').'<br>'.$ret));
	}
}
?>
