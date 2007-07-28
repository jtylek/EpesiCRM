<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @licence SPL
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides interface for module common.
 * @package epesi-base
 * @subpackage module
 */
class ModuleCommon {
	/**
	 * Checks access to a method.
	 * First parameter is a module object and second is a method in this module.
	 * 
	 * If you want to restric access to a method just create a method called
	 * 'methodname_access' returning false if you want restrict user from accessing 
	 * 'methodname' method.
	 * 
	 * check_access is called automatically with each pack_module call.
	 * 
	 * @param object module
	 * @param string function name
	 * @return bool true if access is granted, false otherwise
	 */
	public static function check_access($mod, $m) {
		if (method_exists($mod.'Common', $m . '_access') && !call_user_func(array (
				$mod.'Common',
				$m . '_access'
			)))
			return false;
		return true;
	}

}
?>