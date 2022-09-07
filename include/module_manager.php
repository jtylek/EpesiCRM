<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @license MIT
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

require_once 'autoloader.php';

/**
 * This class provides methods for module manipulations
 * @package epesi-base
 * @subpackage module
 */
class ModuleManager {
	const MODULE_ENABLED = 0;
	const MODULE_DISABLED = 1;
	const MODULE_NOT_FOUND = 2;
	public static $modules = array();
	public static $modules_install = array();
	public static $modules_common = array();
	public static $root = array();
	private static $processing = array();
	private static $processed_modules = array('install'=>array(),'downgrade'=>array(),'upgrade'=>array(),'uninstall'=>array());

	/**
	 * Returns DI container
	 * @todo Move services definitions to separate providers
	 * @return \Pimple\Container
     */
	public static function get_container()
	{
		static $container;
		if (!$container) {
			$container = new Pimple\Container();
			$container['twig'] = function ($c) {
				$loader = new Twig_Loader_Filesystem(EPESI_LOCAL_DIR);
				$twig = new Twig_Environment($loader, array('translation_domain' => false));
				return $twig;
			};
		}
		return $container;
	}

	/**
	 * Includes file with module installation class.
	 *
	 * Do not use directly.
	 *
	 * @param string $module_class_name module class name - underscore separated
	 */
	public static final function include_install($module_class_name) {
		if(isset(self::$modules_install[$module_class_name])) return true;
		$path = self::get_module_dir_path($module_class_name);
		$file = self::get_module_file_name($module_class_name);
		$full_path = EPESI_LOCAL_DIR . '/modules/' . $path . '/' . $file . 'Install.php';
		if (!file_exists($full_path)) {
            self::create_module_mock($module_class_name . 'Install');
			self::check_is_module_available($module_class_name);
			return false;
		}
		ob_start();
		$ret = require_once($full_path);
		ob_end_clean();
		$x = $module_class_name.'Install';
		if(!(class_exists($x, false)) || !array_key_exists('ModuleInstall',class_parents($x)))
			trigger_error('Module '.$module_class_name.': Invalid install file '.$path,E_USER_ERROR);
		self::$modules_install[$module_class_name] = new $x($module_class_name);
		return true;
	}

	/**
	 * Includes file with module common class.
	 *
	 * Do not use directly.
	 *
	 * @param string module name
	 */
	public static final function include_common($class_name,$version) {
        // here was function calls:
        //     self::get_module_dir_path($class_name);
        //     self::get_module_file_name($class_name);
        // but function calls slows down too much
        $path = str_replace('_', '/',$class_name);
        $pos = strrpos($class_name, '_');
		$file = ($pos !== false) ? substr($class_name, $pos+1):$class_name;
		$file_url = EPESI_LOCAL_DIR . '/modules/' . $path . '/' . $file . 'Common_'.$version.'.php';
        //
		if(file_exists($file_url)) {
			ob_start();
			require_once ($file_url);
			ob_end_clean();
			$x = $class_name.'Common';
			if(class_exists($x, false)) {
				if(!array_key_exists('ModuleCommon',class_parents($x)))
					trigger_error('Module '.$path.': Common class should extend ModuleCommon class.',E_USER_ERROR);
				call_user_func(array($x, 'Instance'), $class_name);
    			return true;
			}
		} else {
			self::check_is_module_available($class_name);
		}
		return false;
	}

	/**
	 * Includes file with module main class.
	 *
	 * Do not use directly.
	 *
	 * @param string module name
	 */
	public static final function include_main($class_name, $version) {
		if(class_exists($class_name, false)) return;
        // here was function calls:
        //     self::get_module_dir_path($class_name);
        //     self::get_module_file_name($class_name);
        // but function calls slows down too much
		$path = str_replace('_', '/',$class_name);
        $pos = strrpos($class_name, '_');
		$file = ($pos !== false) ? substr($class_name, $pos+1):$class_name;
		$file_url = EPESI_LOCAL_DIR . '/modules/' . $path . '/' . $file . '_'.$version.'.php';
        //
		if (file_exists($file_url) ) {
			ob_start();
			require_once ($file_url);
			ob_end_clean();
			if(class_exists($class_name, false)) {
                if (!array_key_exists('Module',class_parents($class_name)))
                    trigger_error('Module '.$path.': Invalid main file',E_USER_ERROR);
                return true;
            }
		} else {
			self::check_is_module_available($class_name);
		}
		return false;
	}

	/**
	 * Creates array of installed modules indexed by priority of loading, based on dependencies.
	 *
	 * Do not use directly.
	 *
	 * @return array array containing information about modules priorities
	 */
	public static final function create_load_priority_array() {
		$queue = array();
		$virtual_modules = array(); //virtually loaded modules
		$priority = array();
		$installed_modules = DB::Execute('SELECT name,version FROM modules ORDER BY priority');

		foreach($installed_modules as $v) {
			$module_to_load = $v['name'];
			$version = $v['version'];
			$deps = self :: check_dependencies($module_to_load, $version, $virtual_modules);

			if(!empty($deps)) {
				$queue[] = array('name'=>$module_to_load,'version'=>$version, 'deps'=>$deps);
				continue;
			}

			$priority[] = $module_to_load;
			self :: register($module_to_load, $version, $virtual_modules);

			//queue
			$registered = true;
			while($registered) {
				$registered = false;
				foreach($queue as $k=>$m) {
					$deps = self :: check_dependencies($m['name'], $m['version'], $virtual_modules);
					if(empty($deps)) {
						$priority[] = $m['name'];
						unset($queue[$k]);
						self :: register($m['name'], $m['version'], $virtual_modules);
						$registered = true;
					}
				}
			}
		}
		if(!empty($queue)) foreach($queue as $k=>$m) { // quickfix - with some modules distributed through Store we can't enfroce deps as easily now
			$priority[] = $m['name'];
			self :: register($m['name'], $m['version'], $virtual_modules);
		}
		foreach($priority as $k=>$v)
			DB::Execute('UPDATE modules SET priority=%d WHERE name=%s',array($k,$v));
/*		if(!empty($queue)) {
			$x = 'Modules deps not satisfied: ';
			foreach($queue as $k=>$m) {
				$deps = $m['deps'];
				$yyy = array();
				foreach($deps as $xxx) {
					$yyy[] = $xxx['name'].' ['.$xxx['version'].']';
				}
				$x .= $m['name'].' ['.$m['version'].'] ('.implode(',',$yyy).'), ';
			}
			$x .= '<br>';
			trigger_error($x,E_USER_ERROR);
		}*/
	}

	/**
	 * Check dependencies and return array of unsatisfied dependencies.
	 *
	 * This function is called when installing modules.
	 * Should not be used directly.
	 *
	 * @param string module to check if all requirements are satisifed
	 * @param integer module version to check
	 * @param array table with loaded modules
	 * @return array
	 */
	private static final function check_dependencies($module_to_check, $version, & $module_table) {
		$req_mod = self::get_required_modules($module_to_check, $version);

		$ret = array();

		foreach ($req_mod as $m) {
			$m['name'] = str_replace('/','_',$m['name']);
			if (!array_key_exists($m['name'], $module_table) || $module_table[$m['name']]<$m['version'])
				$ret[] = $m;
		}

		return $ret;
	}

	public static function create_module_mock($class)
	{
        if (!class_exists($class, false)) {
            eval ("class $class extends ModulePrimitive {} ");
        }
	}

	private static function satisfy_dependencies($module_to_install,$version,$check=null) {
		self::$processing[$module_to_install] = $version;
		try {
			$deps = self :: check_dependencies($module_to_install, $version, self::$modules);
			while(!empty($deps)) {
				$m = $deps[0];
				if(isset(self::$processing[$m['name']]))
					throw new Exception('Cross dependencies: '.$module_to_install);

				if (!self :: exists($m['name'],$m['version']))
					throw new Exception('Module not found: ' . '<b>' . $m['name'] . '</b>' . ' version='.$m['version']);

				print('Inst/Up required module: ' . '<b>' . $m['name'] . '</b>' . ' version='.$m['version'].' by ' . '<b>' . $module_to_install . '</b>' . '<br>');
				if(self :: is_installed($m['name'])<0){
					if (!self :: install($m['name'], $m['version'],$check)) {
                        throw new Exception('Cannot install module: ' . $m['name']);
                    }
				} else {
					if (!self :: upgrade($m['name'], $m['version'])) return false;
				}
				$deps = self :: check_dependencies($module_to_install, $version, self::$modules);
			}
		} catch (Exception $e) {
			print ($e->getMessage().'<br>');
			return false;
		}
		unset(self::$processing[$module_to_install]);
		return true;
	}

	/**
	 * Returns directory path of the module. Also this is module name.
	 *
	 * @param string $module module name or class name - both / and _ separated
	 * @return string directory path of the module without modules/ prefix
	 */
	public static final function get_module_dir_path($module) {
		return str_replace('_', '/',$module);
	}

	/**
	 * Returns main filename part of the module.
	 *
	 * Module class named Base_Box
	 * get_module_file_name returns 'Box'
	 * @param string $module module name or class name. 
	 * @return string final portion of module name
	 */
	public static final function get_module_file_name($module) {
        $module_class_name = self::get_module_class_name($module);
		$pos = strrpos($module_class_name, '_');
		return ($pos !== false)? substr($module_class_name, $pos+1):$module_class_name;
	}

	/**
	 * Creates list of modules currently available to install along with list of available versions.
	 *
	 * @return array array built as follows: array('Box'=>array(0,1)...)
	 */
	public static final function list_modules() {
		$dirs = dir_tree('modules',array('theme','lang','help'));
		$ret = array();
		foreach($dirs as $d) {
			$module = str_replace('/','_',substr($d,8,-1));
			$file = self::get_module_file_name($module);

			if(!file_exists($d . $file . 'Install.php'))
				continue;

			self::include_install($module);
			$version_f = array(self::$modules_install[$module],'version');
			if(is_callable($version_f))
				$version_ret = call_user_func($version_f);
			else
				$version_ret = 0;
			$version_arr = array();
			if(is_array($version_ret)) {
				$version_arr = $version_ret;
				$version = count($version_ret);
			} else {
				$version = intval($version_ret);
				for($i=0; $i<=$version; $i++)
					$version_arr[] = $i;
			}

			$ret[$module] = $version_arr;
		}
		return $ret;
	}

	/**
	 * Check if module install file exists
	 * @param string $module module name or module class name - both / and _ separated.
	 * @return bool true if file was found, false otherwise
	 */
	public static final function exists($module) {
		$path = self::get_module_dir_path($module);
		$file = self::get_module_file_name($module);
		return file_exists('modules/' . $path . '/' . $file . 'Install.php');
	}

	/**
	 * Registers module passed as first parameter in array passed as third parameter.
	 * It is used to mark in an array that module is loaded and provides some external modules.
	 *
	 * Do not use directly.
	 *
	 * @param string $module_class_name module class name - underscore separated.
	 * @param integer $version module version
	 * @param array modules list
	 */
	public static final function register($module_class_name, $version, & $module_table) {
		$module_table[$module_class_name] = $version;
	}

	/**
	 * Unregisters module passed as first parameter from array passed as third parameter.
	 * It is used to mark in an array that module is not loaded and doesn't provide some external modules anymore.
	 *
	 * Do not use directly.
	 *
	 * @param string $module_class_name module class name - underscore separated.
	 * @param integer module version
	 * @param array modules list
	 */
	public static final function unregister($module_class_name, & $module_table) {
		unset($module_table[$module_class_name]);
	}

	/**
	 * Checks if module is installed.
	 *
	 * @param string $module module name or module class name - both / and _ separated.
	 * @return integer version of installed module or -1 when it's not installed
	 */
	public static final function is_installed($module) {
        $module_class_name = self::get_module_class_name($module);
        if (isset(self::$modules) && array_key_exists($module_class_name, self::$modules))
            return self::$modules[$module_class_name];
        return -1;
    }

	/**
	 * This function performs upgrade process when it is requested by Setup module.
	 *
	 * Do not use directly.
	 *
	 * @param string module name
	 * @param int version to which module should be upgraded
	 * @return bool true is module was upgraded sucesfully, false otherwise
	 */
	public static final function upgrade($module,$to_version) {

		$installed_version = self::is_installed($module);
		if($installed_version>=$to_version) {
			print('Upgrading module \''.$module.'\' to version '.$to_version.': There is already installed version '.$installed_version.'<br>');
			return false;
		}
		if($installed_version==-1) {
			print('Upgrading module \''.$module.'\' to version '.$to_version.': module is not installed, please install it first.<br>');
			return false;
		}
		if (!self :: exists($module,$to_version)) {
			print('Upgrading module \''.$module.'\' to version '.$to_version.': specified version of module is missing, please download it first.<br>');
			return false;
		}

		self::include_install($module);

		for($i=$installed_version+1; $i<=$to_version; $i++) {
			$up_func = array(self::$modules_install[$module], 'upgrade_'.$i);
			if(!self::satisfy_dependencies($module,$i) || (is_callable($up_func)
				&& !call_user_func($up_func))) {
				print('Upgrading module \''.$module.'\' to version '.$to_version.': upgrade to version '.$i.' failed, calling downgrade to revert changes<br>');
				call_user_func(array(self::$modules_install[$module], 'downgrade_'.$i));
				print('Upgrading module \''.$module.'\' to version '.$to_version.': downgraded to version '.($i-1).'<br>');
				break;
			}
		}

		$i--;

		if(!DB::Execute('UPDATE modules SET version=%d WHERE name=%s',array($i,$module))) {
			print('Upgrading module \''.$module.'\' to version '.$to_version.': unable to update database<br>');
			return false;
		}

		self::register($module,$to_version,self::$modules);

		self::create_load_priority_array();
		self::create_common_cache();

		self::$processed_modules['upgrade'][$module] = $to_version;
		if($i==$to_version)	{
			if(DEBUG)
				print('Module '.$module.' succesfully upgraded to version '.$to_version.'<br>');
			return true;
		}
		return false;
	}

	/**
	 * This function performs downgrade process when it is requested by Setup module.
	 *
	 * Do not use directly.
	 *
	 * @param string module name
	 * @param int version to which module should be downgraded
	 * @return bool true if module was downgraded sucesfully, false otherwise
	 */
	public static final function downgrade($module,$to_version) {
		$installed_version = self::is_installed($module);
		if($installed_version<$to_version) {
			print('Downgrading module \''.$module.'\' to version '.$to_version.': There is already installed version '.$installed_version.'<br>');
			return false;
		}
		if($installed_version==-1) {
			print('Downgrading module \''.$module.'\' to version '.$to_version.': module not installed.<br>');
			return false;
		}
		if (!self :: exists($module,$to_version)) {
			print('Downgrading module \''.$module.'\' to version '.$to_version.': specified version of module is missing, please download it first.<br>');
			return false;
		}

		self::include_install($module);

		//check if any other module requires this one....
		foreach(self::$modules as $k=>$k_version) {
			if($k==$module) continue;

			$func = array (
				self::$modules_install[$k],
				'requires'
			);
			if(!is_callable($func)) continue;
			$req_mod = call_user_func($func,$k_version);

			foreach($req_mod as $req)
				if($req['name']==$module && $req['version']>$to_version) {
					print('Downgrading module \''.$module.'\' to version '.$to_version.': module '.$k.' requires this module at least in version '.$req['version'].' !<br>');
					return false;
				}
		}

		//go
		for($i=$installed_version; $i>$to_version; $i--) {
			$down_func = array(self::$modules_install[$module], 'downgrade_'.$i);
			if(!self::satisfy_dependencies($module,$i) || (is_callable($down_func)
				&& !call_user_func($down_func))) {
				print('Downgrading module \''.$module.'\' to version '.$to_version.' from '.$i.' failed.<br>');
				break;
			}
		}

		if(!DB::Execute('UPDATE modules SET version=%d WHERE name=%s',array($i,$module))) {
			print('Downgrading module \''.$module.'\' to version '.$to_version.' failed: unable to update database<br>');
			return false;
		}

		self::create_load_priority_array();
		self::create_common_cache();

		print('Module '.$module.' succesfully downgraded to version '.$to_version.'<br>');
		self::$processed_modules['downgrade'][$module] = $to_version;
		return true;
	}
    
    public static final function get_module_class_name($module) {
		$submodule_delimiter = strpos($module, '#');
		if ($submodule_delimiter) {
			$module = substr($module, 0, $submodule_delimiter);
		}
        return str_replace('/', '_', $module);
    }

	/**
	 * Installs module given as first parameter.
	 * Additionally, this function calls upgrade to version given as second parameter.
	 *
	 * @param string $module module name or module class name - both / and _ separated.
	 * @param integer $version module version
	 * @return bool true if installation success, false otherwise
	 */
	public static final function install($module, $version=null, $check=null, $include_common=true) {
		if($check===null) {
			if(defined('UPDATING_EPESI'))
				$check=false;
			else
				$check=true;
		}

        $module_class_name = self::get_module_class_name($module);
        
		self::include_install($module_class_name);
		
		if (!isset(self::$modules_install[$module_class_name])) return false;

		$func_version = array(self::$modules_install[$module_class_name], 'version');
		if(is_callable($func_version))
			$inst_ver = call_user_func($func_version);
		else
			$inst_ver = 0;
		if(is_array($inst_ver)) $inst_ver = count($inst_ver);
			else $inst_ver = intval($inst_ver);

		if(!isset($version)) {
			$version = $inst_ver-1;
		} else {
			if($inst_ver<$version) {
				print('Module ' . '<b>' . $module_class_name . '</b>' .' is too old. Please download newer version<br>');
				return false;
			}
		}

		if(self::is_installed($module_class_name)>=$version)
			return true;

		if (!self :: exists($module_class_name,$version))
			return false;

		//check dependecies
		if(!self::satisfy_dependencies($module_class_name,$version,$check)) {
			print('<b>' . $module_class_name . '</b>' . ': dependencies not satisfied.<br>');
			return false;
		}

        if(DB::is_mysql())
            DB::Execute('SET FOREIGN_KEY_CHECKS = 0');
		//call install script and fill database
		if(!call_user_func(array (
			self::$modules_install[$module_class_name],
			'install'
		))) {
			call_user_func(array (
				self::$modules_install[$module_class_name],
				'uninstall'
			));
			self::remove_data_dir($module_class_name);
			print('<b>' . $module_class_name . '</b>' . ': uninstalled<br>');
            if(DB::is_mysql())
                DB::Execute('SET FOREIGN_KEY_CHECKS = 1');
			return false;
		}
        if(DB::is_mysql())
            DB::Execute('SET FOREIGN_KEY_CHECKS = 1');

		$ret = DB::Execute('insert into modules(name, version) values(%s,0)', $module_class_name);
		if (!$ret) {
			print ('<b>' . $module_class_name . '</b>' . ' module installation failed: database<br>');
			return false;
		}

		self::register($module_class_name, $version, self::$modules);

        PatchUtil::mark_applied($module_class_name);

		if ($check) {
			self::create_load_priority_array();
		}

		print ('<b>' . $module_class_name . '</b>' . ' module installed!<br>');

		if($version!=0) {
			$up = self::upgrade($module_class_name, $version);
			if(!$up) {
				return false;
			}
		}

		if($include_common) {
            self::include_common($module_class_name,$version);
//    		self::create_common_cache();
        }
        if(file_exists(DATA_DIR.'/cache/common.php')) unlink(DATA_DIR.'/cache/common.php');
		Cache::clear();

		self::$processed_modules['install'][$module_class_name] = $version;
		return true;

	}

	public static function get_required_modules($name,$version)
	{
		static $cache = array();
		if (isset($cache[$name])) {
			return $cache[$name];
		}

		$callable = self::include_install($name);
		if ($callable) {
			$required = call_user_func(array (
				self::$modules_install[$name],
				'requires'
			),$version);
        } else {
            $required = array();
        }
		$cache[$name] = $required;
		return $required;
	}

	/**
	 * Uninstalls module.
	 *
	 * @param string module name
	 * @return bool true if uninstallation success, false otherwise
	 */
	public static final function uninstall($module) {
		$module_to_uninstall = self::get_module_class_name($module);
		$installed_version = self::is_installed($module_to_uninstall);
		if ($installed_version<0) {
			print($module_to_uninstall . ' module not installed<br>');
			return false;
		}

		self::include_install($module_to_uninstall);

		foreach (self::$modules as $name => $version) { //for each module
			if ($name == $module_to_uninstall)
				continue;

			$required = self::get_required_modules($name,$version);

			foreach ($required as $req_mod) { //for each dependency of that module
				$req_mod['name'] = str_replace('/','_',$req_mod['name']);
				if ($req_mod['name'] == $module_to_uninstall) {
					print ($module_to_uninstall . ' module is required by ' . $name . ' module! You have to uninstall ' . $name . ' first.<br>');
					return false;
				}
			}
		}

		if($installed_version>0 && !self::downgrade($module_to_uninstall, 0))
			return false;

		if(!call_user_func(array (
			self::$modules_install[$module_to_uninstall],
			'uninstall'
		))) return false;

		$ret = DB::Execute('DELETE FROM modules WHERE name=%s', $module_to_uninstall);
		if(!$ret) {
			print ($module_to_uninstall . " module uninstallation failed: database<br>");
			return false;
		}

		self::unregister($module_to_uninstall,self::$modules);

		if (!self::remove_data_dir($module_to_uninstall)){
			print ($module_to_uninstall . " module uninstallation failed: data directory remove<br>");
			return false;
		}

		self::create_load_priority_array();
		Cache::clear();
//		self::create_common_cache();
        if(file_exists(DATA_DIR.'/cache/common.php')) unlink(DATA_DIR.'/cache/common.php');

		print ($module_to_uninstall . " module uninstalled! You can safely remove module directory.<br>");
		self::$processed_modules['uninstall'][$module_to_uninstall] = -1;
		return true;
	}

	public static function get_processed_modules() {
		return self::$processed_modules;
	}

	/**
	 * Returns an array of installed modules indexed by priority of loading, based on dependencies.
	 *
	 * Do not use directly.
	 *
	 * @return array array containing information with modules priorities
	 */
	public static final function get_load_priority_array($force=false) {
		static $load_prior_array=null;
		if($load_prior_array===null || $force) {
			$priorities = array();
			$installed_modules = DB::Execute('SELECT * FROM modules ORDER BY priority');
			if ($installed_modules!==false) {
				$load_prior_array = array();
				while (($row = $installed_modules->FetchRow())) {
					if (isset($priorities[$row['priority']])) {
						ModuleManager::create_load_priority_array();
						return self::get_load_priority_array($force);
					}
					$priorities[$row['priority']] = true;
					if (!isset($row['state']) || $row['state'] == self::MODULE_ENABLED) {
						$load_prior_array[] = $row;
					}
				}
			}
		}
		return $load_prior_array;
	}

	/**
	 * Creates new module instance.
	 *
	 * Do not use directly.
	 * Use pack_module instead.
	 *
	 * @param module name
	 * @return Module Return newly created subclass of module object
	 */
	public static final function new_instance($mod,$parent,$name,$clear_vars=false) {
		$class = str_replace('#', '_', $mod);
		if (!in_array('Module', class_parents($class))) {
			trigger_error("Class $mod is not a subclass of Module", E_USER_ERROR);
		}
		$m = new $class($mod,$parent,$name,$clear_vars, self::get_container());
		return $m;
	}

	/**
	 * Returns instance of module.
	 *
	 * @param string module name
	 * @return bool null if module instance was not found, requested module object otherwise
	 */
	public static final function get_instance($path) {
		$xx = explode('/',$path);
		$curr = self::$root;
		if(is_object($curr) && $curr->get_node_id() != $xx[1]) {
			$x = null;
			return $x;
		}
		$xx_count = count($xx);
		if($xx_count>2) {
			$curr = $curr->get_child($xx[2]);
			if(!$curr) return $curr;
			$xx_count--;
			for($i=2; $i<$xx_count; $i++) {
				if($curr->get_node_id() == $xx[$i]) {
					$curr = $curr->get_child($xx[$i+1]);
				} else {
					$x = null;
					return $x;
				}
				if(!$curr) return $curr;
			}
		}
		return $curr;
	}

	/**
	 * Creates default data directory for module.
	 *
	 * Do not use directly.
	 *
	 * @param string module name
	 * @return bool true if directory was created or already exists, false otherwise
	 */
	public static final function create_data_dir($name) {
		$name = str_replace('/','_',$name);
		$dir = DATA_DIR.'/'.$name;
		if (is_dir($dir) && is_writable($dir))
			return true;
		$x = mkdir($dir);
		file_put_contents($dir.'/index.html','');
		return $x;
	}

	/**
	 * Removes default data directory of a module.
	 *
	 * Do not use directly.
	 *
	 * @param string module name
	 * @return bool true if directory was removed or did not exist, false otherwise
	 */
	public static final function remove_data_dir($name) {
		$name = str_replace('/','_',$name);
		$dir = DATA_DIR.'/'.$name.'/';
		if(is_dir($dir))
			recursive_rmdir($dir);
		return true;
	}

	public static final function get_data_dir($name) {
		$name = str_replace('/','_',$name);
		return DATA_DIR.'/'.$name.'/';
	}

	/**
	 * Loads all installed classes definitions.
	 *
	 * Do not use directly.
	 */
	public static final function load_modules() {
		self::$modules = array();
		$installed_modules = ModuleManager::get_load_priority_array(true);

		$composer_autoloads = Cache::get('composer_autoloads');
		if ($composer_autoloads === null) {
			$composer_autoloads = array();
			foreach ($installed_modules as $row) {
				$module = $row['name'];
				$filename = '/modules/' . self::get_module_dir_path($module) . '/vendor/autoload.php';
				if (file_exists(EPESI_LOCAL_DIR . $filename)) {
					$composer_autoloads[] = $filename;
				}
			}
			Cache::set('composer_autoloads', array_unique($composer_autoloads));
		}
		foreach ($composer_autoloads as $autoload_file) {
			if (file_exists(EPESI_LOCAL_DIR . $autoload_file)) {
				require_once EPESI_LOCAL_DIR . $autoload_file;
			}
		}

		$cached = false;
		if(FORCE_CACHE_COMMON_FILES) {
			$cache_file = DATA_DIR.'/cache/common.php';
			if(!file_exists($cache_file))
				self::create_common_cache();
			ob_start();
			require_once($cache_file);
			ob_end_clean();
			$cached = true;
		}

		foreach ($installed_modules as $row) {
			$module = $row['name'];
			$version = $row['version'];
			ModuleManager::register($module, $version, self::$modules);
		}

		// all commons already loaded by FORCE_CACHE_COMMON_FILES
		if ($cached) return;

		if (!$commons_with_code = Cache::get('commons_with_code')) {
			$commons_with_code = array();
			foreach ($installed_modules as $row) {
				$module = $row['name'];
				$version = $row['version'];
				if (self::common_has_code($module, $version)) {
					$commons_with_code[$module] = $version;
				}
			}
			Cache::set('commons_with_code', $commons_with_code);
			// this code includes all Common files to check for the code
			// because there is a return
			return;
		}

		foreach ($commons_with_code as $module => $version) {
			if (isset(self::$modules[$module])) {
				self::include_common($module, $version);
			}
		}
	}

	public static final function common_has_code($module_name, $version)
	{
		$class_name = $module_name . 'Common';
		$file = 'modules/' . self::get_module_dir_path($module_name) . '/' . self::get_module_file_name($module_name) . 'Common_' . $version . '.php';
		if (!class_exists($class_name, false)) {
			self::include_common($module_name, $version);
		}
		if (!file_exists($file)) {
			return false;
		}
		$start = $end = -1;
		if (class_exists($class_name, false)) {
			$rc = new ReflectionClass($class_name);
			$start = $rc->getStartLine()-1;
			$end = $rc->getEndLine();
		}
		$file_content = '';
		$file_lines = file($file);

		$VA_regex = '/Direct access forbidden/i';
        $use_keyword_regex = '/^\s*use/i';

		foreach ($file_lines as $i => $line) {
			if ($i >= $start && $i < $end) continue;
			if (preg_match($VA_regex, $line)) continue;
			if (preg_match($use_keyword_regex, $line)) continue;
			$file_content .= $line;
		}
        $tmp_file = tmpfile();
        fwrite($tmp_file, $file_content);
        $info = stream_get_meta_data($tmp_file);
		$stripped_file = php_strip_whitespace($info['uri']);
        fclose($tmp_file);
		// heuristic to get info about code. Some very short code can be ommited.
		if (strlen($stripped_file) > 20) {
			return true;
		}
		return false;
	}
	
	public static final function create_common_cache() {
        if(!FORCE_CACHE_COMMON_FILES) return;

        $installed_modules = ModuleManager::get_load_priority_array(true);
		$ret = '';
		foreach($installed_modules as $row) {
			$module = $row['name'];
			$version = $row['version'];
			$path = self::get_module_dir_path($module);
			$file = self::get_module_file_name($module);
			$file_url = 'modules/' . $path . '/' . $file . 'Common_'.$version.'.php';
			if(file_exists($file_url)) {
				$ret .= file_get_contents ($file_url);
				$ret .= '<?php $x = \''.$module.'Common\';'.
					'if(class_exists($x, false)){ '.
						'if(!array_key_exists(\'ModuleCommon\',class_parents($x)))'.
							'trigger_error(\'Module '.$path.': Common class should extend ModuleCommon class.\',E_USER_ERROR);'.
							'call_user_func(array($x,\'Instance\'),\''.$module.'\');'.
					'} ?>';
			}
		}
		$cache_dir = DATA_DIR.'/cache/';
		if(!file_exists($cache_dir))
			mkdir($cache_dir,0777,true);

		file_put_contents($cache_dir.'common.php',$ret);
	}

	/**
	 * Creates root(first) module instance.
	 *
	 * Do not use directly.
	 */
	public static function create_root() {
		ob_start();
		try {
			$default_module = Variable::get('default_module');
			self::$root = ModuleManager :: new_instance($default_module,null,'0');
		} catch (Exception $e) {
			self::$root = ModuleManager :: new_instance(FIRST_RUN,null,'0');
		}
		$ret = trim(ob_get_contents());
		if(strlen($ret)>0 || self::$root==null) trigger_error($ret,E_USER_ERROR);
		ob_end_clean();
		return self::$root;
	}

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
	public static final function check_access($mod, $m) {
		$comm = $mod.'Common';
		if(class_exists($comm)) {
			$sing = call_user_func(array($comm,'Instance'));
			if (method_exists($sing, $m . '_access') &&
				!call_user_func(array($sing, $m . '_access')))
				return false;
		}
		return true;
	}

	public static final function call_common_methods($method,$cached=true,$args=array()) {
		static $cache;
		$modules_with_method = self::check_common_methods($method);
		$cache_id = $method.md5(serialize($args));
		if(!isset($cache[$cache_id]) || !$cached) {
			$ret = array();
			ob_start();
			foreach($modules_with_method as $name) {
				$common_class = $name . 'Common';
				if (class_exists($common_class) && is_callable(array($common_class, $method))) {
					$ret[$name] = call_user_func_array(array($common_class, $method), $args);
				}
			}
			ob_end_clean();
			$cache[$cache_id]=$ret;
		}
		return $cache[$cache_id];
	}

	public static final function check_common_methods($method) {
		$cache_key = "common_method_" . $method;
		$modules_with_method = Cache::get($cache_key);
		if ($modules_with_method === null) {
			$modules_with_method = array();
			foreach(self::$modules as $name=>$version) {
				if (class_exists($name . 'Common') && method_exists($name . 'Common', $method)) {
					$modules_with_method[] = $name;
				}
			}
			Cache::set($cache_key, $modules_with_method);
		}
		return $modules_with_method;
	}

    /**
     * Returns array containing required modules.
     *
     * @param bool $verbose If true then function returns associative array containing names of modules that require specific module
     * @return array If module is required then arr['module_name'] is equal to number of modules that require specific module.
     */
    public static function required_modules($verbose = false) {
        $ret = array();
        foreach (self::$modules as $name => $version) {
			$required = self::get_required_modules($name,$version);
			foreach ($required as $req_mod) {
                $req_name = str_replace('/','_',$req_mod['name']);
                if($verbose) {
                    $ret[$req_name][] = $name;
                } else {
                    if(!isset($ret[$req_name])) $ret[$req_name] = 1;
                    else $ret[ $req_name ]++;
                }
			}
		}
        return $ret;
    }

    /**
     * Queues cron method to be run asap.
     *
     * @param string $module Module class name - usually $this->get_type()
     * @param string $method cron method name
     * @return boolean true - queued, false - already running
     */
    public static function reset_cron($module,$method) {
        $func = $module.'Common::'.$method;
        if(!is_callable(array($module.'Common',$method))) trigger_error('Invalid cron method: '.$func);
        $func_md5 = md5($func);
        $running = DB::GetOne('SELECT running FROM cron WHERE func=%s',array($func_md5));
        if($running) return false;
        DB::Execute('UPDATE cron SET last=0 WHERE func=%s',array($func_md5));
        return true;
    }

	public static function check_is_module_available($module)
	{
		if (!self::module_dir_exists($module)) {
			self::set_module_state($module, self::MODULE_NOT_FOUND);
			self::unregister($module, self::$modules);
		}
	}

	public static function module_dir_exists($module) {
		$dir = 'modules/' . self::get_module_dir_path($module);
		return file_exists($dir);
	}

	public static function set_module_state($module, $state) {
        static $column_present;
        if ($column_present === null) {
            $column_names = DB::MetaColumnNames('modules');
            $column_present = isset($column_names['STATE']);
        }
        if (!$column_present) return;
		DB::Execute('UPDATE modules SET state=%d WHERE name=%s', array($state, $module));
		Cache::clear();
	}

    public static function enable_modules($state = null)
    {
        $sql = 'UPDATE modules SET state=%d';
        $args = array(ModuleManager::MODULE_ENABLED);
        if ($state) {
            $sql .= ' WHERE state=%d';
            $args[] = $state;
        }
        DB::Execute($sql, $args);
        $enabled_modules = DB::Affected_Rows();
        Cache::clear();
        return $enabled_modules;
    }
}
