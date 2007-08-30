<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @licence SPL
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides methods for module manipulations
 * @package epesi-base
 * @subpackage module
 */
class ModuleManager {
	public static $not_loaded_modules = null;
	public static $loaded_modules = array();
	public static $modules = array();
	public static $root = array();
	private static $processing = array(); 

	/**
	 * Includes file with module installation class.
	 * 
	 * Do not use directly.
	 * 
	 * @param string module name
	 */
	public static final function include_install($class_name) {
		$path = self::get_module_dir_path($class_name);
		$file = self::get_module_file_name($class_name);
		ob_start();
		require_once ('modules/' . $path . '/' . $file . 'Install.php');
		ob_end_clean();
		if(!class_exists($class_name.'Install'))
			trigger_error('Module '.$path.': Invalid install file',E_USER_ERROR);
	}

	/**
	 * Includes file with module common class.
	 * 
	 * Do not use directly.
	 * 
	 * @param string module name
	 */
	public static final function include_common($class_name,$version) {
		$path = self::get_module_dir_path($class_name);
		$file = self::get_module_file_name($class_name);
		$file_url = 'modules/' . $path . '/' . $file . 'Common_'.$version.'.php';
		if(@file_exists($file_url) ) {
			ob_start();
    			require_once ($file_url);
			ob_end_clean();
			return true;
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
		if(class_exists($class_name)) return;
		$path = self::get_module_dir_path($class_name);
		$file = self::get_module_file_name($class_name);
		$file_url = 'modules/' . $path . '/' . $file . '_'.$version.'.php';
		if(@file_exists($file_url) ) {
			ob_start();
			require_once ($file_url);
			ob_end_clean();
			return true;
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
		
		foreach(self::$modules as $module_to_load=>$v) {		
			if ($v['name']!=$module_to_load) //provided packages
				continue;

			$deps = self :: check_dependencies($v['name'], $v['version'], $virtual_modules);
		
			if(!empty($deps)) {
				$queue[] = $v;
				continue;
			}
		
			$priority[] = $v['name'];
			self :: register($v['name'], $v['version'], $virtual_modules);
		
			//queue
			foreach($queue as $k=>$m) {
				$deps = self :: check_dependencies($m['name'], $m['version'], $virtual_modules);
				if(empty($deps)) {
					$priority[] = $m['name'];
					unset($queue[$k]);
					self :: register($m['name'], $m['version'], $virtual_modules);
				}
			}
		}
		if(!empty($queue)) {
			$x = 'Modules deps not satisfied: ';
			foreach($queue as $k=>$m) { 
				$deps = self :: check_dependencies($m['name'], $m['version'], $virtual_modules);
				$yyy = array();
				foreach($deps as $xxx) {
					$yyy[] = $xxx['name'].' ['.$xxx['version'].']';
				}
				$x .= $m['name'].' ['.$m['version'].'] ('.implode(',',$yyy).'), ';
			}
			$x .= '<br>';
			trigger_error($x,E_USER_ERROR);
		}
		foreach($priority as $k=>$v)
			DB::Execute('UPDATE modules SET priority=%d WHERE name=%s',array($k,$v));
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
		self::include_install($module_to_check);
		
		$func = array (
			$module_to_check . 'Install',
			'requires'
		);
		if(!is_callable($func)) return array();
		$req_mod = call_user_func($func,$version);
		
		$ret = array();

		foreach ($req_mod as $m) {
			$m['name'] = str_replace('/','_',$m['name']);
			if (!array_key_exists($m['name'], $module_table) || $module_table[$m['name']]['version']<$m['version'])
				$ret[] = $m;
		}
		
		return $ret;
	}
	
	private static function satisfy_dependencies($module_to_install,$version) {
		self::$processing[$module_to_install] = $version;  
		try {
			$deps = self :: check_dependencies($module_to_install, $version, self::$modules);
			while(!empty($deps)) {
				$m = $deps[0];
				if(isset(self::$processing[$m['name']])) 
					throw new Exception('Cross dependencies: '.$module_to_install);
				
				if (!self :: exists($m['name'],$m['version']))
					throw new Exception('Module not found: ' . $m['name'].' version='.$m['version']);
				
				print('Inst/Up required module: '.$m['name'].' version='.$m['version'].' by '.$module_to_install.'<br>');
				if(self :: is_installed($m['name'])<0){
					if (!self :: install($m['name'], $m['version'])) 
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
	 * Returns directory path to the module including module main directory.
	 * 
	 * @param string module name
	 * @return string directory path to the module
	 */
	public static final function get_module_dir_path($name) {
		return str_replace('_', '/',$name);
	}
	
	/**
	 * Returns main filename part of the module.
	 * 
	 * Module named Box, instance 1:
	 * get_module_file_name returns 'Box'
	 * 
	 * Note that file names always contain some other parts like 'Box_0.php'
	 * 
	 * @return string directory path to the module
	 */
	public static final function get_module_file_name($name) {
		$ret = strrchr($name,'_');
		return ($ret)? substr($ret,1):$name;
	}
	
	/**
	 * Creates list of modules currently available to install along with list of available versions.
	 * 
	 * @return array array built as follows: array('Box'=>array(0,1)...)
	 */
	public static final function list_modules() {
		$dirs = dir_tree('modules');
		$ret = array();
		foreach($dirs as $d) {
			$module = str_replace('/','_',substr($d,8,-1));
			$file = self::get_module_file_name($module);

			if(!file_exists($d . $file . 'Install.php'))
				continue;

			self::include_install($module);
			$version_f = array($module.'Install','version');
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
	 * Check if module passed as first parameter with version passed as second parameter can be found in Modules' directory.
	 * 
	 * @return bool true if module was found, false otherwise
	 */
	public static final function exists($mod) {
		$path = self::get_module_dir_path($mod);
		$file = self::get_module_file_name($mod);
		//print_r($file);
		return file_exists('modules/' . $path . '/' . $file . 'Install.php');
	}

	/**
	 * Registers module passed as first parameter in array passed as third parameter.
	 * It is used to mark in an array that module is loaded and provides some external modules.
	 * 
	 * Do not use directly.
	 * 
	 * @param string module name
	 * @param integer module version
	 * @param array modules list
	 */
	public static final function register($mod, $version, & $module_table) {
		$module_table[$mod] = array('name'=>$mod, 'version'=>$version);
	}
	
	/**
	 * Unregisters module passed as first parameter from array passed as third parameter.
	 * It is used to mark in an array that module is not loaded and doesn't provide some external modules anymore.
	 * 
	 * Do not use directly.
	 * 
	 * @param string module name
	 * @param integer module version
	 * @param array modules list
	 */
	public static final function unregister($mod, & $module_table) {
		unset($module_table[$mod]);
	}
	
	/**
	 * Checks if module is installed.
	 * 
	 * @param string module name
	 * @return integer version of installed module or -1 when it's not installed
	 */
	public static final function is_installed($module_to_install) {
		$module_to_install = str_replace('/','_',$module_to_install);
		if (isset (self::$modules) && array_key_exists($module_to_install, self::$modules) && self::$modules[$module_to_install]['name']==$module_to_install)
			return self::$modules[$module_to_install]['version'];
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
			/*$deps = self::check_dependencies($module,$i,self::$modules);
			if(!empty($deps)) {
				foreach($deps as $d)
					print('Upgrading module \''.$module.'\' to version '.$to_version.': upgrade to version '.$i.' failed. Module '.$d['name'].' version='.$d['version'].' required!<br>');
				break;
			}*/
			if(!self::satisfy_dependencies($module,$i) || (is_callable(array($module.'Install', 'upgrade_'.$i)) 
				&& !call_user_func(array($module.'Install', 'upgrade_'.$i)))) {
				print('Upgrading module \''.$module.'\' to version '.$to_version.': upgrade to version '.$i.' failed.<br>');
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
		foreach(self::$modules as $k=>$o) {
			if($k!=$o['name'] || $k=$module) continue;
			$k_version = self::is_installed($k);
			
			$func = array (
				$k . 'Install',
				'requires_'
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
/*			$deps = self::check_dependencies($module,$i-1,self::$modules);
			if(!empty($deps)) {
				foreach($deps as $d)
					print('Downgrading module \''.$module.'\' to version '.$to_version.' from '.$i.' failed: module '.$d['name'].' version='.$d['version'].' required!<br>');
				break;
			}*/
			if(!self::satisfy_dependencies($module,$i) || (is_callable(array($module.'Install', 'downgrade_'.$i)) 
				&& !call_user_func(array($module.'Install', 'downgrade_'.$i)))) {					print('Downgrading module \''.$module.'\' to version '.$to_version.' from '.$i.' failed.<br>');
				break;
			}
		}
		
		if(!DB::Execute('UPDATE modules SET version=%d WHERE name=%s',array($i,$module))) {
			print('Downgrading module \''.$module.'\' to version '.$to_version.' failed: unable to update database<br>');
			return false;
		}
		
		self::create_load_priority_array();
		
		print('Module '.$module.' succesfully downgraded to version '.$to_version.'<br>');
		return true;
	}
	
	/**
	 * Installs module given as first parameter.
	 * Additionally, this function calls upgrade to version given as second parameter.
	 * 
	 * @param string module name
	 * @param integer module version
	 * @return bool true if installation success, false otherwise
	 */
	public static final function install($module_to_install, $version=null) {
		//already installed?
		print($module_to_install.': is installed?<br>');

		self :: include_install($module_to_install);
		if(!class_exists($module_to_install.'Install')) {
			print('Invalid module<br>');
			return false;
		}
		
		$func_version = array($module_to_install.'Install', 'version');
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
				print('Module '.$module_to_install.' is too old. Please download newer version<br>');
				return false;
			}		
		}

		if(self::is_installed($module_to_install)>=$version)
			return true;
			
		if (!self :: exists($module_to_install,$version))
			return false;

		//check dependecies
		if(!self::satisfy_dependencies($module_to_install,$version)) {
			print($module_to_install.': dependencies not satisfied.<br>');
			return false;
		}

		print($module_to_install.': creating data dir<br>');
		if (!self::create_data_dir($module_to_install)) {
			print($module_to_install.': unable to create data directory.<br>');
			return false;
		}

		print($module_to_install.': calling install method<br>');
		//call install script and fill database
		if(!call_user_func(array (
			$module_to_install . 'Install',
			'install'
		))) return false;

		print($module_to_install.': registering<br>');
		$ret = DB::Execute('insert into modules(name, version) values(%s,0)', $module_to_install);
		if (!$ret) {
			print ($module_to_install . ' module installation failed: database<br>');
			return false;
		}

		self :: register($module_to_install, 0, self::$modules);

		print($module_to_install.': rewriting priorities<br>');
		self::create_load_priority_array();
		
		print ($module_to_install . ' module installed!<br>');
		
		if($version!=0) {
			print($module_to_install.': upgrades...<br>');
			$up = self::upgrade($module_to_install, $version);
			if(!$up) return false;
		}
		self::$not_loaded_modules[] = array('name'=>$module_to_install,'version'=>$version);
		
		print($module_to_install.': deps ok, including common class<br>');
		self :: include_common($module_to_install,$version);

		return true;

	}

	/**
	 * Restores module data based from backup.
	 * 
	 * @param string module name
	 * @param string date on which desired backup was made
	 * @param bool if set to true it will delete all current module data
	 * @return bool true on success, false otherwise
	 */
	public static final function restore($module,$date,$delete_old_data=true) {
		$module = str_replace('/','_',$module);
		$version = self::is_installed($module);
		if($version<0) {
			print('Module '.$module.' not installed.<br>');
			return false;
		}
		self::include_install($module);
		if(!is_callable(array($module.'Install','backup'))) {
			print('Module '.$module.' doesn\'t support restore.<br>');
			return false;
		}
		
		if(is_numeric($date))
			$pkg_name = $module.'__'.$version.'__'.$date;
		else {
			print('Invalid restore timestamp.');
			return false;
		}
		
		if($delete_old_data)
			self::remove_data_dir($module);
		recursive_copy('backup/'.$pkg_name.'/data/','data/'.$module.'/');
		
		//restore tables
		$backup_tables = call_user_func(array (
				$module . 'Install',
				'backup'
			),$version);
		
		$ado = & DB::$ado;
		$ado->StartTrans();
		foreach($backup_tables as $table) {
			$fp = fopen('backup/'.$pkg_name.'/sql/'.$table, "r");
			if ($fp) {
				if($delete_old_data)
					DB::Execute('DELETE FROM '.$table);
				$columns = fgetcsv($fp);
				while($r = fgetcsv($fp)) {
					$arr = array();
					foreach($columns as $i=>$c) {
						$arr[$c] = $r[$i];
					}
					if(!$ado->AutoExecute($table,$arr,'INSERT'))
						print('Unable to insert '.var_dump($arr).' to table '.$table.'<br>');
				}
			} else {
				print('Unable to restore database dump.<br>');
				return false;
			}
			fclose($fp);
		}
		$ado->CompleteTrans();
		return true;
	}
	
	/**
	 * Creates module backup point.
	 * 
	 * @param string module name
	 * @return bool true on success, false otherwise
	 */
	public static final function backup($module) {
		$module = str_replace('/','_',$module);
		$installed_version = self::is_installed($module);
		if ($installed_version<0) {
			print('Module '.$module.' not installed.<br>');
			return false;
		}
		self::include_install($module);
		if(!is_callable(array($module.'Install','backup'))) {
			print('Module '.$module.' doesn\'t support backup.<br>');
			return false;
		}
		
		require_once('adodb/toexport.inc.php');
		
		
		if(!is_dir('backup') || !is_writable('backup'))
			return false;
			
		$pkg_name = $module.'__'.$installed_version.'__'.time();
		
		mkdir('backup/'.$pkg_name);
		
		//backup data
		$src = 'data/'.$module.'/';
		$dest = 'backup/'.$pkg_name.'/data/';
		recursive_copy($src,$dest);
		
		//backup tables
		$backup_tables = call_user_func(array (
				$module . 'Install',
				'backup'
			),$installed_version);
		
		mkdir('backup/'.$pkg_name.'/sql');
		foreach($backup_tables as $table) {
			$fp = fopen('backup/'.$pkg_name.'/sql/'.$table, "w");
			if ($fp) {
				$rs = DB::Execute('SELECT * FROM '.$table);
  				rs2csvfile($rs, $fp);
			} else {
				print('Unable to create database dump.');
				return false;
			}
			fclose($fp);
		}
		
		return true;
	}
	
	/**
	 * Returns list of available backups.
	 * 
	 * @return array list of backups, each backup point is described as an array with fields 'name', 'version', 'date'
	 */
	public static final function list_backups() {
		$backup_ls = scandir('backup');
		$backup = array();
		$reqs = array();
		foreach($backup_ls as $b) {
			if(!ereg('([^-]+)__([0-9]+)__([0-9]+)',$b,$reqs)) continue;
			$backup[] = array('name'=>$reqs[1],'version'=>$reqs[2],'date'=>$reqs[3]);
		}
		return $backup;
	}

	/**
	 * Uninstalls module.
	 * 
	 * @param string module name
	 * @return bool true if uninstallation success, false otherwise
	 */
	public static final function uninstall($module_to_uninstall) {
		$installed_version = self::is_installed($module_to_uninstall);
		if ($installed_version<0)
			return false;
		
		self::include_install($module_to_uninstall);
		
		foreach (self::$modules as $name => $obj) { //for each module
			if ($name != $obj['name'] || $name == $module_to_uninstall)
				continue;
			
			$required = call_user_func(array (
				$obj['name'] . 'Install',
				'requires'
				),$obj['version']);
			
			foreach ($required as $req_mod) { //for each dependency of that module
				$req_mod['name'] = str_replace('/','_',$req_mod['name']);
				if (self::$modules[$req_mod['name']]['name'] == $module_to_uninstall) {
					print ($module_to_uninstall . ' module is required by ' . $obj['name'] . ' module! You have to uninstall ' . $obj['name'] . ' first.<br>');
					return false;
				}
			}
		}
		self::backup($module_to_uninstall);
		
		if($installed_version>0 && !self::downgrade($module_to_uninstall, 0))
			return false;
		
		if(!call_user_func(array (
			$module_to_uninstall . 'Install',
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
		
		print ($module_to_uninstall . " module uninstalled! You can safely remove module directory.<br>");
		return true;
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
			$installed_modules = DB::Execute('SELECT name,version FROM modules ORDER BY priority');
			if ($installed_modules!==false) {
				$load_prior_array = array();
				while (($row = $installed_modules->FetchRow()))
					$load_prior_array[] = $row;
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
	public static final function & new_instance($mod,$parent,$name) {
		if (!array_key_exists($mod, self::$modules))
			throw new Exception('module not loaded');
		if(!array_key_exists($mod, self::$loaded_modules)) {
			foreach(self::$not_loaded_modules as $i=>$v) {
				$version = $v['version'];
				$module = $v['name'];
				ModuleManager :: include_main($module, $version);
				unset(self::$not_loaded_modules[$i]);
				self::$loaded_modules[$module] = true;
				if($module==$mod) break;
			}
		}
		$c = self::$modules[$mod]['name'];
		$m = new $c($mod,$parent,$name);
		return $m;
	}

	/**
	 * Returns instance of module.
	 * 
	 * @param string module name
	 * @param integer instance id
	 * @return bool false if module instance was not found, requested module object otherwise
	 */
	public static final function & get_instance($path) {
		$xx = explode('/',$path);
		$curr = & self::$root;
		if($curr->get_node_id() != $xx[1]) {
			$x = null;
			return $x;
		}
		if(count($xx)>2) {
			$curr = & $curr->get_child($xx[2]);
			if(!$curr) return $curr;
			for($i=2; $i<count($xx)-1; $i++) {
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
	private static final function create_data_dir($name) {
		$dir = 'data/'.$name;
		if (is_dir($dir) && is_writable($dir))
			return true;
		print('Creating data directory '.$dir.'<br>');
		return mkdir($dir,0777);
	}

	/**
	 * Removes default data directory of a module.
	 * 
	 * Do not use directly.
	 * 
	 * @param string module name
	 * @return bool true if directory was removed or did not exist, false otherwise
	 */
	protected static final function remove_data_dir($name) {
		$name = str_replace('/','_',$name);
		$dir = 'data/'.$name.'/';
		recursive_rmdir($dir);
		return true;
	}
	
	public static final function get_data_dir($name) {
		$name = str_replace('/','_',$name);
		return 'data/'.$name.'/';
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
		foreach($installed_modules as $row) {
			$module = $row['name'];
			$version = $row['version'];
			ModuleManager :: include_common($module, $version);
			ModuleManager :: register($module, $version, self::$modules);
		}
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
			self::$root = & ModuleManager :: new_instance('FirstRun',null,'0');
		}
		$ret = trim(ob_get_contents());
		if(strlen($ret)>0 || self::$root==null) trigger_error($ret,E_USER_ERROR);
		ob_end_clean();
		return self::$root;
	}
}
?>
