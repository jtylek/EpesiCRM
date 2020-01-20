<?php
/**
 * @author Arkadiusz Bisaga, Janusz Tylek
 * @copyright Copyright &copy; 2006-2020 Janusz TylekTylek
 * @version 1.9.0
 * @license MIT
 * @package epesi-tests
 * @subpackage Attachment
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_AttachmentCommon extends ModuleCommon {
	public static function menu(){
		return array(_M('Tests')=>array('__submenu__'=>1,'__weight__'=>-10, _M('Attachment page')=>array()));
	}
}

?>
