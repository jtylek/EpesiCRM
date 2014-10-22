<?php
/**
 * 
 * @author Georgi Hristov <ghristov@gmx.de>
 * @copyright Copyright &copy; 2014, Xoff Software GmbH
 * @license MIT
 * @version 1.0
 * @package epesi-notify
 * 
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Notify extends Module {
	//interval to poll for new notifications
	const refresh_rate = 30; //seconds
	//interval at which to look back in time
	const reset_time = 24; //hours

	public function body() {
		$this->load_js();
	}

	private function load_js() {
		load_js('modules/Base/Notify/js/desktop-notify.js');
		load_js('modules/Base/Notify/js/main.js');

		eval_js("if (notify.isSupported) {
		clearInterval(Base_Notify__interval);
		var Base_Notify__interval = setInterval(function () {Base_Notify__refresh('".CID."');}, ".(self::refresh_rate*1000).");
		}");

		eval_js_once('function Base_Notify__alert () {alert(\''.__('Notifications disabled or not supported!').'\n'.__('Check your browser settings...').'\');}');
	}
}
?>