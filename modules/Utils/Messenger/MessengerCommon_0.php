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

eval_js_once('utils_messenger_refresh = function(){'.
			'new Ajax.Request(\'modules/Utils/Messenger/refresh.php\',{method:\'get\'});'.
			'};setInterval(\'utils_messenger_refresh()\',180000);utils_messenger_refresh()');


?>