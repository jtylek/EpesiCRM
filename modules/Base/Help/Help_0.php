<?php
/**
 * Help class.
 *
 * This class provides interactive help.
 * Mouse click icon based on an icon by FatCow Web Hosting (http://www.fatcow.com/free-icons/) [CC-BY-3.0-us (http://creativecommons.org/licenses/by/3.0/us/deed.en)], via Wikimedia Commons
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2012, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage help
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Help extends Module {
	public function body() {
		load_js('modules/Base/Help/js/canvasutilities.js');
		load_js('modules/Base/Help/js/main.js');
		eval_js('Helper.stop_tutorial_message = "'.Epesi::escapeJS('Tutorial was stopped').'";');
		eval_js('setTimeout("Helper.get_all_help_hooks();", 500);');
		$theme = $this->init_module('Base_Theme');
		$theme->assign('href', 'href="javascript:void(0);" onclick="Helper.menu()"');
		$theme->assign('search_placeholder', __('Start typing to search help topics'));
		$theme->assign('label', __('Help'));
		Utils_ShortcutCommon::add(array('esc'), 'function(){Helper.escape();}');
		Utils_ShortcutCommon::add(array('f1'), 'function(){Helper.menu();}');
		$theme->display();
	}
}
?>
