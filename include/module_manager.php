<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @license SPL
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
	public static $not_loaded_modules = null;
	public static $loaded_modules = array();
	public static $modules = array();
	public static $modules_install = array();
	public static $modules_common = array();
	public static $root = array();
	private static $processing = array();
	private static $processed_modules = array('install'=>array(),'downgrade'=>array(),'upgrade'=>array(),'uninstall'=>array());

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
		$full_path = 'modules/' . $path . '/' . $file . 'Install.php';
		if (!file_exists($full_path)) return false;
		ob_start();
		$ret = require_once($full_path);
		ob_end_clean();
		$x = $module_class_name.'Install';
		if(!(class_exists($x, false)) || !array_key_exists('ModuleInstall',class_parents($x)))
			trigger_error('Module '.$path.': Invalid install file',E_USER_ERROR);
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
		$file_url = 'modules/' . $path . '/' . $file . 'Common_'.$version.'.php';
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
		$file_url = 'modules/' . $path . '/' . $file . '_'.$version.'.php';
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
		$ret = self::include_install($module_to_check);
		if (!$ret) return array();

		$func = array (
			self::$modules_install[$module_to_check],
			'requires'
		);
		if(!is_callable($func)) return array();
		$req_mod = call_user_func($func,$version);

		$ret = array();

		foreach ($req_mod as $m) {
			$m['name'] = str_replace('/','_',$m['name']);
			if (!array_key_exists($m['name'], $module_table) || $module_table[$m['name']]<$m['version'])
				$ret[] = $m;
		}

		return $ret;
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
					if (!self :: install($m['name'], $m['version'],$check))
						return false;
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
		$debug = '<div class="green" style="text-align: left;">';

        $module_class_name = self::get_module_class_name($module);
        
		//already installed?
		$debug .= '<b>' . $module_class_name . '</b>' .': is installed?<br>';

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
				print($debug.'Module ' . '<b>' . $module_class_name . '</b>' .' is too old. Please download newer version<br>');
				return false;
			}
		}

		if(self::is_installed($module_class_name)>=$version)
			return true;

		if (!self :: exists($module_class_name,$version))
			return false;

		//check dependecies
		if(!self::satisfy_dependencies($module_class_name,$version,$check)) {
			print($debug.'<b>' . $module_class_name . '</b>' . ': dependencies not satisfied.<br>');
			return false;
		}

		$debug .= '<b>' . $module_class_name . '</b>' . ': calling install method<br>';
        if(preg_match('/^mysql/i',DATABASE_DRIVER))
            DB::Execute('SET FOREIGN_KEY_CHECKS = 0');
		//call install script and fill database
		if(!call_user_func(array (
			self::$modules_install[$module_class_name],
			'install'
		))) {
			$debug .= '<b>' . $module_class_name . '</b>' . ': failed install, calling uninstall<br>';
			call_user_func(array (
				self::$modules_install[$module_class_name],
				'uninstall'
			));
			self::remove_data_dir($module_class_name);
			print($debug.'<b>' . $module_class_name . '</b>' . ': uninstalled<br>');
            if(preg_match('/^mysql/i',DATABASE_DRIVER))
                DB::Execute('SET FOREIGN_KEY_CHECKS = 1');
			return false;
		}
        if(preg_match('/^mysql/i',DATABASE_DRIVER))
            DB::Execute('SET FOREIGN_KEY_CHECKS = 1');

		$debug .= '<b>' . $module_class_name . '</b>' . ': registering<br>';
		$ret = DB::Execute('insert into modules(name, version) values(%s,0)', $module_class_name);
		if (!$ret) {
			print ($debug.'<b>' . $module_class_name . '</b>' . ' module installation failed: database<br>');
			return false;
		}

		self::register($module_class_name, $version, self::$modules);

        PatchUtil::mark_applied($module_class_name);

		if ($check) {
			$debug .= '<b>' . $module_class_name . '</b>' . ': rewriting priorities<br>';
			self::create_load_priority_array();
		}

		print ('<b>' . $module_class_name . '</b>' . ' module installed!<br>');

		if($version!=0) {
			$debug .= '<b>' . $module_class_name . '</b>' . ': upgrades...<br>';
			$up = self::upgrade($module_class_name, $version);
			if(!$up) {
				print($debug);
				return false;
			}
		}
		self::$not_loaded_modules[] = array('name'=>$module_class_name,'version'=>$version);

		//$debug .= '<b>' . $module_to_install . '</b>' . ': deps ok, including common class<br>';
		if($include_common) {
            self::include_common($module_class_name,$version);
//    		self::create_common_cache();
        }
        if(file_exists(DATA_DIR.'/cache/common.php')) unlink(DATA_DIR.'/cache/common.php');

		//$debug .= '</div>';
		self::$processed_modules['install'][$module_class_name] = $version;
		return true;

	}

	/**
	 * Uninstalls module.
	 *
	 * @param string module name
	 * @return bool true if uninstallation success, false otherwise
	 */
	public static final function uninstall($module_to_uninstall) {
		$installed_version = self::is_installed($module_to_uninstall);
		if ($installed_version<0) {
			print($module_to_uninstall . ' module not installed<br>');
			return false;
		}

		self::include_install($module_to_uninstall);

		foreach (self::$modules as $name => $version) { //for each module
			if ($name == $module_to_uninstall)
				continue;

			$callable = self::include_install($name);
			if ($callable)
				$required = call_user_func(array (
					self::$modules_install[$name],
					'requires'
					),$version);
			else
				$required = array();

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
			$installed_modules = DB::Execute('SELECT name,version,priority FROM modules ORDER BY priority');
			if ($installed_modules!==false) {
				$load_prior_array = array();
				while (($row = $installed_modules->FetchRow())) {
					if (isset($priorities[$row['priority']])) {
						ModuleManager::create_load_priority_array();
						return self::get_load_priority_array($force);
					}
					$priorities[$row['priority']] = true;
					$load_prior_array[] = $row;
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
	 * @return object newly created module object
	 * @throws exception 'module not loaded' if the module is not registered
	 */
	public static final function & new_instance($mod,$parent,$name,$clear_vars=false) {
		if(!array_key_exists($mod, self::$loaded_modules)) {
			$loaded = false;
			foreach(self::$not_loaded_modules as $i=>$v) {
				$version = $v['version'];
				$module = $v['name'];
				ModuleManager :: include_main($module, $version);
				unset(self::$not_loaded_modules[$i]);
				self::$loaded_modules[$module] = true;
				if($module==$mod) {
					$loaded=true;
					break;
				}
			}
			if (!$loaded)
				throw new Exception('module '.$mod.' not loaded');
		}
		if(!class_exists($mod, false))
			trigger_error('Class not exists: '.$mod,E_USER_ERROR);
		$m = new $mod($mod,$parent,$name,$clear_vars);
		return $m;
	}

	/**
	 * Returns instance of module.
	 *
	 * @param string module name
	 * @return bool null if module instance was not found, requested module object otherwise
	 */
	public static final function & get_instance($path) {
		$xx = explode('/',$path);
		$curr = & self::$root;
		if($curr->get_node_id() != $xx[1]) {
			$x = null;
			return $x;
		}
		$xx_count = count($xx);
		if($xx_count>2) {
			$curr = & $curr->get_child($xx[2]);
			if(!$curr) return $curr;
			$xx_count--;
			for($i=2; $i<$xx_count; $i++) {
				if($curr->get_node_id() == $xx[$i]) {
					$curr = & $curr->get_child($xx[$i+1]);
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
		self::$not_loaded_modules = $installed_modules;
		self::$loaded_modules = array();
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
		foreach($installed_modules as $row) {
			$module = $row['name'];
			$version = $row['version'];
			if(!$cached)
				ModuleManager :: include_common($module, $version);
			ModuleManager :: register($module, $version, self::$modules);
		}
        
        ModulesAutoloader::enable(false);
	}
	
	public static final function create_common_cache() {
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
	public static function & create_root() {
		ob_start();
		try {
			$default_module = Variable::get('default_module');
			self::$root = & ModuleManager :: new_instance($default_module,null,'0');
		} catch (Exception $e) {
			self::$root = & ModuleManager :: new_instance(FIRST_RUN,null,'0');
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
		$cache_id = $method.md5(serialize($args));
		if(!isset($cache[$cache_id]) || !$cached) {
			$ret = array();
			ob_start();
			foreach(self::$modules as $name=>$version)
				if(class_exists($name.'Common') && method_exists($name.'Common', $method)) {
					$ret[$name] = call_user_func_array(array($name.'Common',$method),$args);
				}
			ob_end_clean();
			$cache[$cache_id]=$ret;
		}
		return $cache[$cache_id];
	}

	public static final function check_common_methods($method,$cached=true) {
		static $cache;
		$cache_id = $method;
		if(!isset($cache[$cache_id]) || !$cached) {
			$ret = array();
			foreach(self::$modules as $name=>$version)
				if(class_exists($name.'Common') && method_exists($name.'Common', $method)) {
					$ret[] = $name;
				}
			$cache[$cache_id]=&$ret;
		}
		return $cache[$cache_id];
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
			if (!self::include_install($name)) return array();
			$required = call_user_func(array (
				self::$modules_install[$name],
				'requires'
				),$version);

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
}
?>