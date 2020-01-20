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

class Base_StatusBarCommon extends ModuleCommon {
	public static $message = '';
	public static function message($text,$type='normal') {
		self::$message = '<div class="message '.$type.'">'.$text.'</div>';
	}
}
?>
