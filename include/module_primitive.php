<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @license MIT
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
	 * Returns path to the module template directory
	 *
	 * @return string path to the module template directory
     */
	public final function get_module_template_dir()
	{
		return $this->get_module_dir() . 'theme/';
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

    public final static function is_installed()
    {
        $module_name = static::module_name();
        return ModuleManager::is_installed($module_name) >= 0;
    }

	public final static function module_name()
	{
        $class_name = get_called_class();
        if (substr($class_name, -6) == 'Common') {
            $class_name = substr($class_name, 0, -6);
        }

        if (substr($class_name, -7) == 'Install') {
            $class_name = substr($class_name, 0, -7);
        }

		return str_replace('_', '/', $class_name);
	}
}
