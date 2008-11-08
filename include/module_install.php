<?php
/**
 * Module file
 * 
 * This file defines abstract class Module whose provides basic modules functionality.
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @license SPL
 * @version 1.0
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides interface for module install.
 * @package epesi-base
 * @subpackage module
 */
abstract class ModuleInstall extends ModulePrimitive{
	
	/**
	 * Module installation function.
	 * @return true if installation success, false otherwise
	 */
	abstract public function install();

	/**
	 * Module uninstallation function.
	 * @return true if installation success, false otherwise
	 */
	abstract public function uninstall();

	/**
	 * Returns array that contains information about modules required by this module.
	 * The array should be determined by the version number that is given as parameter.
	 * 
	 * @return array Array constructed as following: array(array('name'=>$ModuleName,'version'=>$ModuleVersion),...)  
	 */
	abstract public function requires($v);

//	abstract public static function backup($v);
}
?>
