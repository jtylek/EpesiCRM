<?php
/**
 * Fancy statusbar.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license EPL
 * @package epesi-base-extra
 * @subpackage statusbar
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_StatusBarCommon extends ModuleCommon {
	public static $messages = array();
	public static function message($text,$type=null) {
		if($type=='error')
			self::$messages[] = '<div id="Base_StatusBar__error_message">'.$text.'</div>';
		elseif($type=='warning')
			self::$messages[] = '<div id="Base_StatusBar__warning_message">'.$text.'</div>';
		else
			self::$messages[] = $text;
	}
}
?>
