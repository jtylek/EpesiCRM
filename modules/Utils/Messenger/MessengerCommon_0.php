<?php
/**
 * Popup message to the user
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package utils-messenger
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_MessengerCommon extends ModuleCommon {
	public static function applet_caption() {
		return "Messenger alarms";
	}

	public static function applet_info() {
		return "Displays last alarms";
	}
	
	public static function delete_by_parent_module($m) {
		$ret = DB::Execute('SELECT id FROM utils_messenger_message WHERE parent_module=%s',array($m));
		while($row = $ret->FetchRow())
			DB::Execute('DELETE FROM utils_messenger_users WHERE message_id=%d',array($row['id']));
		DB::Execute('DELETE FROM utils_messenger_message WHERE parent_module=%s',array($m));
	}
	
	public static function delete_by_id($id) {
		$mid = md5($id);
		$ret = DB::Execute('SELECT id FROM utils_messenger_message WHERE page_id=\''.$mid.'\'');
		while($row = $ret->FetchRow())
			DB::Execute('DELETE FROM utils_messenger_users WHERE message_id=%d',array($row['id']));
		DB::Execute('DELETE FROM utils_messenger_message WHERE page_id=\''.$mid.'\'');

	}

}

eval_js_once('utils_messenger_on = true; utils_messenger_refresh = function(){'.
			'if(utils_messenger_on) new Ajax.Request(\'modules/Utils/Messenger/refresh.php\',{method:\'get\'});'.
			'};setInterval(\'utils_messenger_refresh()\',180000);utils_messenger_refresh()');


?>