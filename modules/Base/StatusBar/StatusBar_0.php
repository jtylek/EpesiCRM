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
		$theme = $this->init_module("Base/Theme");
		$theme->assign('statusbar_id','Base_StatusBar');
		$theme->assign('text_id','statusbar_text');
		$theme->display();
		$this->load_js();
		on_exit(array($this, 'messages'),null,false);
	}

	public function messages() {
		eval_js("statusbar_message('".Epesi::escapeJS(implode('<br>',Base_StatusBarCommon::$messages),false)."')");
	}

	private function load_js() {
		load_js('modules/Base/StatusBar/js/main.js');
	}
}
?>
