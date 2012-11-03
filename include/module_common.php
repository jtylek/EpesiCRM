<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @license SPL
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides interface for module common.
 * @package epesi-base
 * @subpackage module
 */
class ModuleCommon extends ModulePrimitive {
	
	/* backward compatibility code */
	public static final function acl_check() {
		return false;
	}
	
	/**
	 * Singleton.
	 *
	 * @return object
	 */
	public static final function Instance($arg=null) {
		static $obj;
		if(isset($arg)) $obj = $arg;
		elseif(is_string($obj)) {
			$cl = $obj.'Common';
			$obj = new $cl($obj);
		}
		return $obj;
	}
}
?>