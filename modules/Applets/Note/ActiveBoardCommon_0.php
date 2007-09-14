<?php
/**
 * 
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package tests-activeboard
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_ActiveBoardCommon extends ModuleCommon {
	public static function applet_caption() {
		return "Test";
	}

	public static function applet_info() {
		return "info"; //here can be associative array
	}
}

?>