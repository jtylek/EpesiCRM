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

class Base_StatusBarCommon extends ModuleCommon {
	public static $message = '';
	public static function message($text,$type='normal') {
		self::$message = '<div class="message '.$type.'">'.$text.'</div>';
	}
}
?>
