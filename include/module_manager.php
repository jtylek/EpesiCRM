<?php
/*
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @licence SPL
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class ModuleManager {
	public static $not_loaded_modules = null;
	public static $loaded_modules = array();
	public static $modules = array();
	public static $root = array();

	/**
	 * Include file with module initialization class.
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

	public static final function include_init($class_name, $version) {
		$path = self::get_module_dir_path($class_name);
		$file = self::get_module_file_name($class_name);
		ob_start();
		require_once ('modules/' . $path . '/' . $file . 'Init_'.$version.'.php');
		ob_end_clean();
		if(!class_exists($class_name.'Init_'.$version))
			trigger_error('Module '.$path.': Invalid init file',E_USER_ERROR);
	}

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
	 * Include file with module main class.
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
	 * Gets array of loaded modules indexed by priority of loading, based on dependencies.
	 * 
	 * Do not use directly.
	 * 
	 * @return array array containing information with modules priorities
	 */
	public static final function & create_load_priority_array() {
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

		return $priority;
	}
	
	/**
	 * Check dependencies and return array of unsatisfied dependencies.
	 * 
	 * This function is called when installing modules.
	 * Should not be used directly.
	 * 
	 * @param string module to check if all requirements are satisifed
	 * @param array table with loaded modules
	 * @return array 
	 */
	private static final function check_dependencies($module_to_check, $version, & $module_table) {
		$req_mod = call_user_func(array (
			$module_to_check . 'Init_'.$version,
			'requires'
		));
		
		$ret = array();

		foreach ($req_mod as $m) {
			$m['name'] = str_replace('/','_',$m['name']);
			if (!array_key_exists($m['name'], $module_table) || $module_table[$m['name']]['version']<$m['version'])
				$ret[] = $m;
		}
		
		return $ret;
	}

	
	/**
	 * Returns directories path to the module including module main directory.
	 * 
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
			$version_ret = call_user_func(array($module.'Install','version'));
			$version_arr = array();
			if(is_array($version_ret)) {
				$version_arr = $version_ret;
				$version = count($version_ret);
			} else {
				$version = intval($version_ret);
				for($i=0; $i<=$version; $i++)
					$version_arr[] = $i;
			}

			$files = scandir($d);
			$versions_available = array();
			foreach($files as $vfile) {
				if(ereg('^'.$file.'Init_([0-9]+)\.php$', $vfile, $v)) {
					if($v[1]<=$version) $versions_available[$v[1]]=$version_arr[$v[1]];
				}
			}
			$ret[$module] = $versions_available;
		}
		return $ret;
	}

	/**
	 * Check if module passed as first parameter with version passed as second parameter can be found in Modules' directory.
	 * 
	 * @return bool true if module was found, false otherwise
	 */
	public static final function exists($mod, $version) {
		$path = self::get_module_dir_path($mod);
		$file = self::get_module_file_name($mod);
		//print_r($file);
		return file_exists('modules/' . $path . '/' . $file . 'Init_'.$version.'.php') && file_exists('modules/' . $path . '/' . $file . 'Install.php');
	}

	/**
	 * Registers module passed as first parameter in array passed as third parameter.
	 * It is used to mark in an array that module is loaded and provides some external modules.
	 * 
	 * Do not use directly.
	 * 
	 * @param string module name
	 * @param integer module version
	 * @param array
	 */
	public static final function register($mod, $version, & $module_table) {
		$module_table[$mod] = array('name'=>$mod, 'version'=>$version);
		$prov = call_user_func(array (
			$mod . 'Init_'.$version,
			'provides'
		));
		//print('registered'.$mod.'<br>');
		foreach ($prov as $p) {
			$p['name'] = str_replace('/','_',$p['name']);
			if (!array_key_exists($p['name'], $module_table) || $module_table[$p['name']]['name']==$mod) //priviliged original modules, not alternatives
				$module_table[$p['name']] = array('name'=>$mod, 'version'=>$p['version']);
		}
	}
	
	public static final function unregister($mod, $version, & $module_table) {
		unset($module_table[$mod]);
		$prov = call_user_func(array (
			$mod . 'Init_'.$version,
			'provides'
		));
		foreach ($prov as $p) {
			$p['name'] = str_replace('/','_',$p['name']);
			if ($module_table[$p['name']]['name']==$mod)
				unset($module_table[$p['name']]);
			//TODO: here should be reregistering of module if any other provides this one
		}
	}
	
	/**
	 * Creates required virtual classes.
	 * If one module provides other module features, this function will create virtual class to redirect all function calls.
	 *  
	 * Do not use directly.
	 * 
	 * @param string class name
	 */
	public static final function create_virtual_classes($mod, $version) {
		//check if any of required 'virtual modules' doesn't have its own provided class, and create it if needed
		$req = call_user_func(array (
			$mod . 'Init_'.$version,
			'requires'
		)); 
		foreach($req as $r) {
			$r = str_replace('/','_',$r['name']);
			$real = self::$modules[$r]['name'];
			if(!$real)
				trigger_error('Module '.$r.' required by '.$mod.' not installed.',E_USER_ERROR);
			if($real!=$r && !class_exists($r)) 
				eval("class $r extends $real {}");
		}
	}

	public static final function create_common_virtual_classes($mod, $version) {
		//check if any of required 'virtual modules' doesn't have its own provided class, and create it if needed
		$req = call_user_func(array (
			$mod . 'Init_'.$version,
			'requires'
		)); 
		foreach($req as $r) {
			$r = str_replace('/','_',$r['name']);
			$real = self::$modules[$r]['name'];
			if(!$real)
				trigger_error('Module '.$r.' required by '.$mod.' not installed.',E_USER_ERROR);
			if($real!=$r && !class_exists($r.'Common') && class_exists($real.'Common')) 
				eval('class '.$r.'Common extends '.$real.'Common {}');
		}
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
			print('Upgrading module \''.$module.'\' to version '.$to_version.': module is not installed, please install it first.');
			return false;
		}
		if (!self :: exists($module,$to_version)) {
			print('Upgrading module \''.$module.'\' to version '.$to_version.': specified version of module is missing, please download it first.');
			return false;
		}
		
		self::include_install($module);
		
		for($i=$installed_version+1; $i<=$to_version; $i++) {
			self :: include_init($module, $i);
			$deps = self::check_dependencies($module,$i,self::$modules);
			if(!empty($deps)) {
				foreach($deps as $d)
					print('Upgrading module \''.$module.'\' to version '.$to_version.': upgrade to version '.$i.' failed. Module '.$d['name'].' version='.$d['version'].' required!<br>');
				break;
			}
			if(is_callable(array($module.'Install', 'upgrade_'.$i)) 
			    && !call_user_func(array($module.'Install', 'upgrade_'.$i))) {
				print('Upgrading module \''.$module.'\' to version '.$to_version.': upgrade to version '.$i.' failed.');
				break;
			}
		}
		
		$i--;
		
		if(!DB::Execute('UPDATE modules SET version=%d WHERE name=%s',array($i,$module))) {
			print('Upgrading module \''.$module.'\' to version '.$to_version.': unable to update database');
			return false;
		}
		
		self::register($module,$to_version,self::$modules);
		
		$arr = & self::create_load_priority_array();
		foreach($arr as $k=>$v)
			DB::Execute('UPDATE modules SET priority=%d WHERE name=%s',array($k,$v));
		
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
			print('Downgrading module \''.$module.'\' to version '.$to_version.': module not installed.');
			return false;
		}
		if (!self :: exists($module,$to_version)) {
			print('Downgrading module \''.$module.'\' to version '.$to_version.': specified version of module is missing, please download it first.');
			return false;
		}
		
		self::include_install($module);

		//check if any other module requires this one....
		foreach(self::$modules as $k=>$o) {
			if($k!=$o['name'] || $k=$module) continue;
			$k_version = self::is_installed($k);
			include_init($k,$k_version);
			$req_mod = call_user_func(array (
				$k . 'Init_'.$k_version,
				'requires'
			));
			foreach($req_mod as $req)
				if($req['name']==$module && $req['version']>$to_version) {
					print('Downgrading module \''.$module.'\' to version '.$to_version.': module '.$k.' requires this module at least in version '.$req['version'].' !');
					return false;
				}
		}
		
		//go
		for($i=$installed_version; $i>$to_version; $i--) {
			self :: include_init($module,$i-1);
			$deps = self::check_dependencies($module,$i-1,self::$modules);
			if(!empty($deps)) {
				foreach($deps as $d)
					print('Downgrading module \''.$module.'\' to version '.$to_version.' from '.$i.' failed: module '.$d['name'].' version='.$d['version'].' required!<br>');
				break;
			}
			if(is_callable(array($module.'Install', 'downgrade_'.$i)) 
			    && !call_user_func(array($module.'Install', 'downgrade_'.$i))) {
				print('Downgrading module \''.$module.'\' to version '.$to_version.' from '.$i.' failed.');
				break;
			}
		}
		
		if(!DB::Execute('UPDATE modules SET version=%d WHERE name=%s',array($i,$module))) {
			print('Downgrading module \''.$module.'\' to version '.$to_version.' failed: unable to update database');
			return false;
		}
		
		$arr = & self::create_load_priority_array();
		foreach($arr as $k=>$v)
			DB::Execute('UPDATE modules SET priority=%d WHERE name=%s',array($k,$v));
		
		if(defined('DEBUG'))
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
	public static final function install($module_to_install, $version) {
		//already installed?
		if(defined('DEBUG')) print($module_to_install.': is installed?<br>');

		self :: include_install($module_to_install);
		if(!class_exists($module_to_install.'Install')) {
			print('Invalid module<br>');
			return false;
		}
		
		$inst_ver = call_user_func(array($module_to_install.'Install', 'version'));
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
		try {
			self :: include_init($module_to_install, $version);
			$deps = self :: check_dependencies($module_to_install, $version, self::$modules);
			while(!empty($deps)) {
				$m = $deps[0];
				if (!self :: exists($m['name'],$m['version']))
					throw new Exception('Module not found: ' . $m['name'].' version='.$m['version']);
				if(defined('DEBUG')) 
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
			print ($e->getMessage());
			return false;
		}

		if(defined('DEBUG')) print($module_to_install.': deps ok, including main class<br>');
		self :: include_common($module_to_install,$version);

		if(defined('DEBUG')) print($module_to_install.': creating data dir<br>');
		if (!self::create_data_dir($module_to_install)) {
			print($module_to_install.': unable to create data directory.');
			return false;
		}

		if(defined('DEBUG')) print($module_to_install.': calling install method<br>');
		//call install script and fill database
		if(!call_user_func(array (
			$module_to_install . 'Install',
			'install'
		))) return false;

		if(defined('DEBUG')) print($module_to_install.': registering<br>');
		$ret = DB::Execute('insert into modules(name, version) values(%s,0)', $module_to_install);
		if (!$ret) {
			print ($module_to_install . ' module installation failed: database<br>');
			return false;
		}

		self :: register($module_to_install, 0, self::$modules);

		if(defined('DEBUG')) print($module_to_install.': rewriting priorities<br>');
		$arr = & self::create_load_priority_array();
		foreach($arr as $k=>$v)
			DB::Execute('UPDATE modules SET priority=%d WHERE name=%s',array($k,$v));

		print ($module_to_install . ' module installed!<br>');
		
		if($version!=0) {
			if(defined('DEBUG')) print($module_to_install.': upgrades...<br>');
			$up = self::upgrade($module_to_install, $version);
			if(!$up) return false;
		}
		self::$not_loaded_modules[] = array('name'=>$module_to_install,'version'=>$version);
		
		return true;

	}

	public static final function restore($module,$date,$delete_old_data=true) {
		$module = str_replace('/','_',$module);
		$version = self::is_installed($module);
		if($version<0) {
			print('Module '.$module.' not installed.<br>');
			return false;
		}
		if(!is_callable(array($module.'Init_'.$version,'backup'))) {
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
		self::restore_files('backup/'.$pkg_name.'/data/','data/'.self::get_module_dir_path($module).'/');
		
		//restore tables
		$backup_tables = call_user_func(array (
				$module . 'Init_'.$version,
				'backup'
			));
		
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
	}
	
	private static final function restore_files($src, $dest) {
		$content = scandir($src);
		mkdir($dest);
		foreach ($content as $name){
			if($name == '.' || $name == '..') continue;
			$tmp_src = $src.$name;
			$tmp_dest = $dest.$name;
			if (is_dir($tmp_src)) {
				if (self::is_installed(substr($tmp_dest,5))==-1)
					self::backup_files($tmp_src.'/',$tmp_dest.'/');
			} else copy($tmp_src, $tmp_dest);
		}		
	}
	
	public static final function backup($module) {
		$module = str_replace('/','_',$module);
		$installed_version = self::is_installed($module);
		if ($installed_version<0) {
			print('Module '.$module.' not installed.<br>');
			return false;
		}
		if(!is_callable(array($module.'Init_'.$installed_version,'backup'))) {
			print('Module '.$module.' doesn\'t support backup.<br>');
			return false;
		}
		
		require_once('adodb/toexport.inc.php');
		
		
		if(!is_dir('backup') || !is_writable('backup'))
			return false;
			
		$pkg_name = $module.'__'.$installed_version.'__'.time();
		
		mkdir('backup/'.$pkg_name);
		
		//backup data
		$src = 'data/'.self::get_module_dir_path($module).'/';
		$dest = 'backup/'.$pkg_name.'/data/';
		self::backup_files($src,$dest);
		
		//backup tables
		$backup_tables = call_user_func(array (
				$module . 'Init_'.$installed_version,
				'backup'
			));
		
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
	
	private static final function backup_files($src, $dest) {
		$content = scandir($src);
		mkdir($dest);
		foreach ($content as $name){
			if($name == '.' || $name == '..') continue;
			$tmp_src = $src.$name;
			$tmp_dest = $dest.$name;
			if (is_dir($tmp_src)) {
				if (self::is_installed(substr($tmp_src,5))==-1)
					self::backup_files($tmp_src.'/',$tmp_dest.'/');
			} else copy($tmp_src, $tmp_dest);
		}
	}
	
	public static final function list_backups() {
		$backup_ls = scandir('backup');
		$backup = array();
		foreach($backup_ls as $b) {
			if(!ereg('([^-]+)__([0-9]+)__([0-9]+)',$b,$reqs)) continue;
			$backup[] = array('name'=>$reqs[1],'version'=>$reqs[2],'date'=>$reqs[3]);
		}
		return $backup;
	}

	/**
	 * Deinstalls module.
	 * 
	 * @param string module name
	 * @return bool true if uninstallation success, false otherwise
	 */
	public static final function uninstall($module_to_uninstall) {
		$installed_version = self::is_installed($module_to_uninstall);
		if ($installed_version<0)
			return false;
		
		self::include_install($module_to_uninstall);
		
		$provided_by_other = false;
		//if other installed module provides this module
		foreach (self::$modules as $name => $obj) {
			if ($name != $obj['name'] || $name == $module_to_uninstall)
				continue; //skip provided modules and uninstalled module
			$prov = call_user_func(array (
				$name . 'Init_'.$obj['version'],
				'provides'
			));
			foreach ($prov as $p)
				if ($p['name'] == $module_to_uninstall && $p['version']>=$installed_version) {
					$provided_by_other = true;
					break;
				}
		}

		if (!$provided_by_other)
			foreach (self::$modules as $name => $obj) { //for each module
				if ($name != $obj['name'] || $name == $module_to_uninstall)
					continue;
				
				$required = call_user_func(array (
							$obj['name'] . 'Init_'.$obj['version'],
							'requires'
					));
				
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
		
		self::unregister($module_to_uninstall,$installed_version,self::$modules);
		
		if (!self::remove_data_dir($module_to_uninstall)){
			print ($module_to_uninstall . " module uninstallation failed: data directory remove<br>");
			return false;
		}
		
		$arr = & self::create_load_priority_array();
		foreach($arr as $k=>$v)
			DB::Execute('UPDATE modules SET priority=%d WHERE name=%s',array($k,$v));
		
		print ($module_to_uninstall . " module uninstalled! You can safely remove module directory.<br>");
		return true;
	}
	
		
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
				if(ModuleManager :: include_main($module, $version))
					ModuleManager :: create_virtual_classes($module, $version);
				unset(self::$not_loaded_modules[$i]);
				self::$loaded_modules[$module] = true;
				if($module==$mod) break;
			}
		}
		$m = new self::$modules[$mod]['name']($mod,$parent,$name);
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
		if($curr->get_node_id() != $xx[1]) return false;
		if(count($xx)>2) {
			$curr = & $curr->get_child($xx[2]);
			if(!$curr) return $curr;
			for($i=2; $i<count($xx)-1; $i++) {
				if($curr->get_node_id() == $xx[$i]) {
					$curr = & $curr->get_child($xx[$i+1]);
				} else
					return false;
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
		$dirs = explode('_','data_'.$name);
		$path = '';
		foreach($dirs as $dir){
			if (is_dir($path.$dir) && is_writable($path.$dir)) {
				$path .= $dir.'/';
				continue;
			}
			if(defined('DEBUG'))
				print('Creating data directory '.$path.$dir.'<br>');
			if (!mkdir($path.$dir,0777)) return false;
			$path .= $dir.'/';
		}
		return true;
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
		$dir = 'data/'.str_replace('_','/',$name).'/';
		self::remove_data_files($dir);
		$dirs = explode('_',$name);
		$dir = substr($dir,0,strlen($dir)-strlen($dirs[count($dirs)-1])-1);
		for ($i=count($dirs)-2;$i>=0;$i--){
			if (self::is_installed(substr($dir,5))==-1) rmdir($dir);
			else break;
			$dir = substr($dir,0,strlen($dir)-strlen($dirs[$i])-1);
		}
		return true;
	}
	
	private static final function remove_data_files($path) {
		$path = rtrim($path,'/');
		$content = scandir($path);
		foreach ($content as $name){
			if($name == '.' || $name == '..') continue;
			$name = $path.'/'.$name;
			if (is_dir($name)) {
				if (self::is_installed(substr($name,5))==-1)
					self::remove_data_files($name);
			} else unlink($name);
		}
		rmdir($path);
	}
	
	public static final function load_modules() {
		self::$modules = array();
		$installed_modules = ModuleManager::get_load_priority_array(true);
		self::$not_loaded_modules = $installed_modules;
		self::$loaded_modules = array();
		foreach($installed_modules as $row) {
			$module = $row['name'];
			$version = $row['version'];
			ModuleManager :: include_init($module, $version);
			if(ModuleManager :: include_common($module, $version))
				ModuleManager :: create_common_virtual_classes($module, $version);
			ModuleManager :: register($module, $version, self::$modules);
		}
	}
	
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
