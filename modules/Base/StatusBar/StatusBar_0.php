<?php
/**
 * Fancy statusbar.
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage statusbar
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_StatusBar extends Module {

	public function body() {
		$close_text = __('Click anywhere to dismiss');
		eval_js("document.getElementById('dismiss').innerHTML = '{$close_text}'");
		on_exit(array($this, 'messages'),null,false);
	}

	public function messages() {
		eval_js("statusbar_message('".Epesi::escapeJS(Base_StatusBarCommon::$message,false)."')");
	}
}
?>
