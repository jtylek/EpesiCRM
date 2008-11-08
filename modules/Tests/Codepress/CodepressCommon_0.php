<?php
/**
 * 
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license EPL
 * @version 0.1
 * @package tests-codepress
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_CodepressCommon extends ModuleCommon {
	public static function menu() {
		return array('Tests'=>array('__submenu__'=>1,'Codepress'=>array()));
	}
}

?>