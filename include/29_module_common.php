<?php

class ModuleCommon {
		/**
	 * Checks access to method which name is passed as second parameter.
	 * First parameter is a module object 
	 * 
	 * If you want to restric access to a method just make another method called
	 * 'methodname_access' returning false if user should not access the 'methodname' method.
	 * 
	 * static_check_access is run automatically with each pack_module call.
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

