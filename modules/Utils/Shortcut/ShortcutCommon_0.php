<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC 
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
			self::$clean = isset($_REQUEST['__location'])?$_REQUEST['__location']:true;
			eval_js('shortcut.remove_all();');
		}

		$js = 'shortcut.add("'.implode('+',$keys).'",'.$func.',{';
		$js .= '\'type\':\''.(isset($opts['type'])?$opts['type']:'keydown').'\',';
		$js .= '\'propagate\':'.(isset($opts['propagate'])?$opts['propagate']:'false').',';
		$js .= '\'disable_in_input\':'.(isset($opts['disable_in_input'])?$opts['disable_in_input']:'false').',';
		$js .= '\'target\':'.(isset($opts['target'])?$opts['target']:'document');
		$js .= '});';
		
		eval_js($js);
	}

}

load_js('modules/Utils/Shortcut/js/Shortcut.js');
?>
