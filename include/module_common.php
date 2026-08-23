<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @license MIT
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
		// §36 ROOT FIX: PHP 8.x makes a `static` local in an INHERITED method SHARED across
		// all subclasses (PHP 7.4 gave each subclass its own copy). A single `static $obj`
		// therefore returns "whichever module was seeded last" (module_manager.php:100),
		// breaking SomeModuleCommon::Instance() — root cause of §20, §33, and others.
		// Key the storage per-class via late static binding (static::class) to restore the
		// exact PHP 7.4 per-class-singleton behavior.
		static $objs = array();
		$cls = static::class;
		if(isset($arg)) $objs[$cls] = $arg;
		elseif(isset($objs[$cls]) && is_string($objs[$cls])) {
			$cl = $objs[$cls].'Common';
			$objs[$cls] = new $cl($objs[$cls]);
		}
		return $objs[$cls] ?? null;
	}
}
