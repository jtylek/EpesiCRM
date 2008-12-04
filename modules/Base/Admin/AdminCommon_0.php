<?php
/**
 * Admin class.
 * 
 * This class provides administration module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage admin
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * Interface which you must implement if you would like to have module administration entry.
 */
interface Base_AdminModuleCommonInterface {
	public static function admin_access();
	public static function admin_caption();
}

class Base_AdminCommon extends ModuleCommon {
	public static function body_access() {
		return Base_AclCommon::i_am_admin();
	}
	
	public static function menu() {
		if(!Base_AclCommon::i_am_admin()) return array();
		return array('__split__'=>array('__weight__'=>2000),'Administrator'=>array('__weight__'=>2001));
	}
}

/**
 * Default abstract class for AdminInterface...
 * You can use it for default admin_access and admin_caption functions.
 * Access: Module administrator
 * Caption: <module_name> module 
 */
abstract class Base_AdminModuleCommon extends ModuleCommon implements Base_AdminModuleCommonInterface {
    public static function admin_access() {
	return Base_AclCommon::i_am_admin();
    }
		
    public static function admin_caption() {
    }
}
?>
