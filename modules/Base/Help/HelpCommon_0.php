<?php
/**
 * Help class.
 *
 * This class provides interactive help.
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2012, Janusz Tylek
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage help
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_HelpCommon extends ModuleCommon {
	// adminlte only, per request ("move Help to menu under Support") -
	// merges into the same "Support" submenu group Base_About/Base_EssClient/
	// Base_EpesiStore/Base_Mail_ContactUs already contribute to
	// (Base_MenuCommon::get_menus()'s own add_menu() merges same-named
	// top-level groups together). The default theme keeps its existing
	// separate navbar Help icon untouched - this doesn't change what it
	// shows. __url__ is a real, safe click action rather than a page
	// navigation: Help's own toggle (Help_0.php: 'href="javascript:void(0);"
	// onclick="Helper.menu()"') opens a client-side overlay with no server
	// round-trip, which doesn't fit Base_Menu's normal "navigate to a
	// module/function" leaf-item model at all - void(...) guarantees the
	// javascript: URL's completion value is undefined regardless of what
	// Helper.menu() itself returns, avoiding the (mostly legacy, but not
	// worth relying on) browser behaviour of replacing the page with a
	// javascript: URL's string return value. __target__ overrides
	// Base_Menu's own default of "_blank" for any __url__ entry (meant for
	// real external links) - irrelevant to a javascript: URL's actual
	// behavior, but left explicit rather than relying on that mismatch.
	public static function menu() {
		if (!Base_ThemeCommon::is_adminlte_family()) return;
		return array(_M('Support') => array('__submenu__' => 1,
			_M('Help') => array('__url__' => 'javascript:void(Helper.menu());', '__target__' => '_self')));
	}

	public static function screen_name($name) {
		print('<span style="display:none;" class="Base_Help__screen_name" value="'.$name.'"></span>');
	}
	public static function retrieve_help_from_file($module) {
		$file = 'modules/'.str_replace('_','/',$module).'/help/tutorials.hlp';
		if (file_exists($file))
		$f = fopen($file, 'r');
		$ret = array();
		$i = 0;
		while (!feof($f)) {
			$line = '';
			while (!feof($f) && !str_ends_with($line, ']')) {
				$line .= ($line?'##':'').fgets($f);
				$line = trim($line);
			}
			$line = trim($line, '[]');
			if (!$line) continue;
			$line = explode(':', $line);
			$func = array_shift($line);
			$arg = implode(':', $line);
			switch ($func) {
				case 'LABEL': 	$i++;
								$ret[$i] = array('label'=>_V($arg), 'keywords'=>'', 'context'=>false, 'steps'=>'');
								break;
				case 'STEPS': 	$arg = explode('##', $arg);
								foreach ($arg as $k=>$v) {
									if (!$v) {
										unset($arg[$k]);
										continue;
									}
									$tmp = explode('//', $v);
									if (isset($tmp[1])) {
										$arg[$k] = $tmp[0].'//'._V(trim($tmp[1]));
									}
								}
								$arg = implode('##', $arg);
								$ret[$i]['steps'] = $arg;
								break;
				case 'KEYWORDS': $ret[$i]['keywords'] = _V($arg);
								break;
				case 'CONTEXT': $ret[$i]['context'] = (strtolower($arg)=='true')?true:false;
								break;
				default:
			}
		}
		return $ret;
	}
}

?>
