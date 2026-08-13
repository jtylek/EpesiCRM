<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.5
 * @license MIT
 * @package epesi-develop
 * @subpackage tablebrowsercreator
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Develop_TableBrowserCreatorCommon extends ModuleCommon {
	public static function menu(){
		if (Base_AclCommon::i_am_admin()) return array(_M('Development')=>array('__submenu__'=>1,_M('Create Table Browser')=>array('action'=>'new')));
		return array();		
	}
}
?>
