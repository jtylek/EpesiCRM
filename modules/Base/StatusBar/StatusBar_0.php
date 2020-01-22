<?php
/**
 * Fancy statusbar.
 *
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-base
 * @subpackage statusbar
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_StatusBar extends Module {

	public function body() {
		$theme = $this->init_module("Base/Theme");
		$theme->assign('statusbar_id','Base_StatusBar');
		$theme->assign('text_id','statusbar_text');
		$theme->assign('close_text', __('Click anywhere to dismiss'));
		$theme->assign('status_message', __('Processing. Please wait...'));
		$theme->display();
		$this->load_js();
		on_exit(array($this, 'messages'),null,false);
	}

	public function messages() {
		eval_js("statusbar_message('".Epesi::escapeJS(Base_StatusBarCommon::$message,false)."')");
	}

	private function load_js() {
		load_js('modules/Base/StatusBar/js/main.js');
	}
}
?>
