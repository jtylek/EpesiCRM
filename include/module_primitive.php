<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @license SPL
 * @version 1.0
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

abstract class ModulePrimitive {
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
		return DATA_DIR.'/'.$this->type.'/';
	}

	/**
	 * Returns path to the module directory.  
	 * 
	 * @return string path to the module directory
	 */
	public final function get_module_dir() {
		return 'modules/'.str_replace('_','/',$this->type).'/';
	}
	
	/**
	 * Creates default data directory for module. Typical usage: in module installation
	 * 
	 * @param string module name
	 * @return bool true if directory was created or already exists, false otherwise
	 */
	public final function create_data_dir() {
		return ModuleManager::create_data_dir($this->type);
	}

	/**
	 * Removes default data directory of a module. Typical usage: in module uninstallation
	 * 
	 * @param string module name
	 * @return bool true if directory was removed or did not exist, false otherwise
	 */
	public final function remove_data_dir() {
		return ModuleManager::remove_data_dir($this->type);
	}

	/**
	 * Checks access to function which name is passed as first parameter.
	 * 
	 * If you want to restric access to a function just make function named
	 * 'functionname_access' returning false if user should not access this function.
	 * 
	 * This function is called automatically with each pack_module call.
	 * 
	 * @param string function name
	 * @return bool true if access is granted, false otherwise
	 */
	public final function check_access($m) {
		return ModuleManager::check_access($this->type,$m);
	}
	
	public final static function get_type_with_bt($i=0) {
		if (version_compare(PHP_VERSION, '5.2.5') === 1) {
			$call_dir=debug_backtrace(true);
		} else {
			$call_dir=debug_backtrace();
		}
		
		for($j=0; $j<count($call_dir); $j++)
			if(isset($call_dir[$j]['object']) && $call_dir[$j]['object'] instanceof Module) {
				if($i==0)
					return get_class($call_dir[$j]['object']);
				$i--;
			}
		if($i<=0)
			trigger_error('get_type_with_bt - execution outside epesi module');
	}
}

?>
