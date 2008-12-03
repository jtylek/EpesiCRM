<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-apps
 * @subpackage twistergame
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_TwisterGame extends Module {

	public function body() {
		print('<div id="twister_who" style="font-size: 64px;"></div>');
		print('<div id="twister_color" style="width: 300px; height: 300px; border: 5px solid black;"></div>');
		print('<div id="twister_hand" style="font-size: 64px;"></div>');
		eval_js_once('twister_refresh = function(){if(!$(\'twister_color\')) return;'.
			'new Ajax.Request(\'modules/Apps/TwisterGame/refresh.php\',{method:\'get\','.
			'onComplete: function(t) {'.
			'eval(t.responseText);'.
			'}});'.
			'};setInterval(\'twister_refresh()\',10000)');
		eval_js('twister_refresh()');
	}

    public static function caption() {
		return 'Twister';
	}

}

?>