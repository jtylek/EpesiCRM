<?php
/**
 * Theme class.
 * 
 * Provides module templating.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @license SPL
 * @package epesi-base-extra
 * @subpackage theme
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_ThemeCommon extends ModuleCommon {
	/**
	 * Performs installation of default theme files for a module.
	 * 
	 * Notice: the path should not contain / on the beginning nor on the end of string
	 * 
	 * @param string module name
	 * @param string directory in which default theme data for the module is hold (path relative to specified module)
	 */
	public static function install_default_theme($mod_name,$version=0) {
		$directory = 'modules/'.str_replace('_','/',$mod_name).'/theme_'.$version;
		if (!is_dir($directory)) $directory = 'modules/'.str_replace('_','/',$mod_name).'/theme';
		$mod_name = str_replace('/','_',$mod_name);
		$data_dir = 'data/Base_Theme/templates/default/';
		$content = scandir($directory);
		foreach ($content as $name){
			if($name == '.' || $name == '..' || ereg('^[\.~]',$name)) continue;
			recursive_copy($directory.'/'.$name,$data_dir.$mod_name.'__'.$name);
		}
	}
	
	/**
	 * Removes default theme files for a module.
	 * 
	 * @param string module name
	 */
	public static function uninstall_default_theme($mod_name) {
		$directory = str_replace('_','/',$mod_name);
		$mod_name = str_replace('/','_',$mod_name);
		$data_dir = 'data/Base_Theme/templates/default/';

		$content = scandir($data_dir);
		foreach ($content as $name){
			if($name == '.' || $name == '..' || ereg('^'.$mod_name,$name)===false) continue;
			$name = $data_dir.'/'.$name;
			recursive_rmdir($name);
//			if (!is_dir($name))
//				unlink($name);
		}
	}	
	
	/**
	 * Returns path to currently selected theme.
	 * 
	 * @return string directory in which currently selected theme is placed 
	 */
	public static function get_template_dir() {
		static $theme = null;
		static $themes_dir = 'data/Base_Theme/templates/';
		if(!isset($theme)) {
			$theme = Variable::get('default_theme');
		
			if(!is_dir($themes_dir.$theme))
				$theme = 'default';
		}
		
		return $themes_dir.$theme.'/';
	}

	/**
	 * Returns path and filename of a template file.
	 * 
	 * Use this method if you want to pass full path and filename of a template file 
	 * to another method which specifically accepts such data.
	 * 
	 * @param string module name
	 * @param string path and filename (path relative to specified module)
	 * @return string path and name of a file
	 */
	public static function get_template_filename($modulename,$filename) {
		return str_replace("/", "_", $modulename).'__'.str_replace("/", "_", $filename);
	}

	/**
	 * Returns path and filename of a template file using path to currently selected theme.
	 * 
	 * Use this method if you want to get access to a template file of currently installed theme.
	 * Files retreived this way are accessible via common file operation functions.
	 * 
	 * @param string module name
	 * @param string path and filename (path relative to specified module)
	 * @return mixed path and name of a file, false if no such file was found
	 */
	public static function get_template_file($modulename,$filename) {
		$filename = self::get_template_filename($modulename,$filename);
		$f = self::get_template_dir().$filename;
		if(!is_readable($f)) {
			$f = 'data/Base_Theme/templates/default/'.$filename;
			if(!is_readable($f))
				return false;
		}
		return $f;
	}

	/**
	 * Loads css file.
	 * 
	 * @param string module name
	 * @param string css file name, 'default' by default
	 * @param bool sets whether there should be an error displayed if css is not present, true by default
	 * @return bool true on success, false otherwise
	 */
	public static function load_css($module_name,$css_name = 'default',$trig_error=true) {
		if(!isset($module_name)) 
			trigger_error('Invalid argument for load_css, no module was specified.',E_USER_ERROR);
		
		$css = self::get_template_file($module_name,$css_name.'.css');
		if(!$css) {
			if($trig_error) trigger_error('Invalid css specified: '.$module_name.'__'.$css_name.'.css',E_USER_ERROR);
			return false;
		} else {
			load_css($css);
			return true;
		}
	}
	
	private static function create_css_cache() {
		$themes_dir = 'data/Base_Theme/templates/';
		$def_theme = Variable::get('default_theme');
		$tdir = $themes_dir.$def_theme.'/';
		$arr = glob($themes_dir.'default/*.css');
		$css_def_out = '';
		$css_cur_out = '';
		$files_def_out = '';
		$files_cur_out = '';
		foreach($arr as $f) {
			$name = basename($f);
			if($name=='__cache.css') continue;
			if(is_readable($tdir.$name) && $def_theme!='default') {
				$css_cur_out .= file_get_contents($tdir.$name)."\n";
				$files_cur_out .= $tdir.$name."\n";
			} else {
				$css_def_out .= file_get_contents($f)."\n";
				$files_def_out .= $f."\n";
			}
		}
		
		if(function_exists('gzopen')) {
			$zp = gzopen($themes_dir.'default/__cache.css.gz', 'w9');
			gzwrite($zp, $css_def_out);
			gzclose($zp);
		}

		file_put_contents($themes_dir.'default/__cache.css',$css_def_out);
		file_put_contents($themes_dir.'default/__cache.files',$files_def_out);
		if($def_theme!='default') {
			if(function_exists('gzopen')) {
				$zp = gzopen($tdir.'/__cache.css.gz', 'w9');
				gzwrite($zp, $css_cur_out);
				gzclose($zp);
			}
			
			file_put_contents($tdir.'/__cache.css',$css_cur_out);
			file_put_contents($tdir.'/__cache.files',$files_cur_out);
		}
	}

	private static function get_images($dir) {
		$content = scandir($dir);
		$ret = array();
		foreach ($content as $name){
			if ($name == '.' || $name == '..') continue;
			$file_name = $dir.'/'.$name;
			if (is_dir($file_name)) {
				$ret = array_merge($ret,self::get_images($file_name));
			} else {
				$ext = strtolower(substr(strrchr($file_name,'.'),1));
				if ($ext === 'jpg' ||
					$ext === 'jpeg' ||
					$ext === 'gif' ||
					$ext === 'png')
				$ret[] = $file_name;
			}
		}
		return $ret;
	}

	private static function create_images_cache() {
		$theme_dir = 'data/Base_Theme/templates/';
		$default = self::get_images($theme_dir.'default');
		file_put_contents($theme_dir.'default/__cache.images',implode("\n",$default));
		$def_theme = Variable::get('default_theme');
		if($def_theme!='default') {
			$tdir = $theme_dir.$def_theme;
			$theme = self::get_images($tdir);
			file_put_contents($tdir.'/__cache.images',implode("\n",$theme));
		}		
	}

	/**
	 * For internal use only.
	 */
	public static function create_cache() {
		self::create_css_cache();
		self::create_images_cache();
	}
}
?>
