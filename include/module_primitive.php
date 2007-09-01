<?php
/**
 * Module file
 * 
 * This file defines abstract class Module whose provides basic modules functionality.
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @licence SPL
 * @version 1.0
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

abstract class ModulePrimitive extends ModuleAcl {
	private $type;
	
	public function __construct($type) {
		$this->type = $type;
	}

	/**
	 * Returns name(type) of module that called this function.
	 * 
	 * @return string 
	 */
	public final function get_type() {
		return $this->type;
	}
	
	/**
	 * Returns path to the default data directory for module calling this method.
	 * Use this directory if your module requires to create or operate on a file.  
	 * 
	 * @return string path to the data directory
	 */
	public final function get_data_dir() {
		return 'data/'.$this->type.'/';
	}

	/**
	 * Returns path to the module directory.  
	 * 
	 * @return string path to the module directory
	 */
	public final function get_module_dir() {
		return 'modules/'.str_replace('_','/',$this->type).'/';
	}
}

?>