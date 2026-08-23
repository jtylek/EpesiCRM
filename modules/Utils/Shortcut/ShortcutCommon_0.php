<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Janusz Tylek 
 * @version 1.0
 * @license MIT 
 * @package epesi-utils 
 * @subpackage shortcut
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_ShortcutCommon extends ModuleCommon {
	private static $clean = false;
	
	public static function add($keys, $func, $opts = array()) {
		if ((isset($_REQUEST['__location']) &&
			self::$clean!==$_REQUEST['__location']) ||
			(self::$clean===false)) {
			self::$clean = $_REQUEST['__location'] ?? true;
			eval_js('shortcut.remove_all();');
		}

		$js = 'shortcut.add("'.implode('+',$keys).'",'.$func.',{';
		$js .= '\'type\':\''.($opts['type'] ?? 'keydown').'\',';
		$js .= '\'propagate\':'.($opts['propagate'] ?? 'false').',';
		$js .= '\'disable_in_input\':'.($opts['disable_in_input'] ?? 'false').',';
		$js .= '\'target\':'.($opts['target'] ?? 'document');
		$js .= '});';
		
		eval_js($js);
	}

}

load_js('modules/Utils/Shortcut/js/Shortcut.js');
?>
