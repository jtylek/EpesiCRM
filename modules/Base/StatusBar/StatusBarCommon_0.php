<?php
/**
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * @package tcms-base-extra
 * @subpackage statusbar
 */
class Base_StatusBarCommon {
	public static $messages = array();
	public static function message($text,$type) {
		if($type=='error')
			self::$messages[] = '<div id="statusbar_error_message">'.$text.'</div>';
		elseif($type=='warning')
			self::$messages[] = '<div id="statusbar_warning_message">'.$text.'</div>';
		else
			self::$messages[] = $text;
	}
}
?>
