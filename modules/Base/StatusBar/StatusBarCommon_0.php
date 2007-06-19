<?php
/**
 * Fancy statusbar.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * @package epesi-base-extra
 * @subpackage statusbar
 */
class Base_StatusBarCommon {
	public static $messages = array();
	public static function message($text,$type) {
		if($type=='error')
			self::$messages[] = '<div id="Base_StatusBar__error_message">'.$text.'</div>';
		elseif($type=='warning')
			self::$messages[] = '<div id="Base_StatusBar__warning_message">'.$text.'</div>';
		else
			self::$messages[] = $text;
	}
}
?>
