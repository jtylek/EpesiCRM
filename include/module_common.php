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
	private static $singletons = [];
	
	/* backward compatibility code */
	public static final function acl_check() {
		return false;
	}
	
	/**
	 * Singleton.
	 *
	 * @return object
	 */
	public static function Instance($arg=null) {
		$class = static::class;
		$singleton = self::$singletons[$class] ?? null;
		
		if(isset($arg)) {
			self::$singletons[$class] = $arg;
		}
		elseif(is_string($singleton)) {			
			self::$singletons[$class] = new $class($singleton);
		}
		return self::$singletons[$class];
	}
}
