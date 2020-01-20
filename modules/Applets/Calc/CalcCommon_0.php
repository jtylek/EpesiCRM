<?php
/**
 * @author j@epe.si
 * @copyright 2008 Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-applets
 * @subpackage calc
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_CalcCommon extends ModuleCommon {
	public static function applet_caption() {
		return __('Calc');
	}

	public static function applet_info() {
		return __('Simple calculator applet');
	}
}

?>
