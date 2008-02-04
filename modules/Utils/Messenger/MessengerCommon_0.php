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

}

eval_js_once('utils_messenger_on = true; utils_messenger_refresh = function(){'.
			'if(utils_messenger_on) new Ajax.Request(\'modules/Utils/Messenger/refresh.php\',{method:\'get\'});'.
			'};setInterval(\'utils_messenger_refresh()\',180000);utils_messenger_refresh()');


?>