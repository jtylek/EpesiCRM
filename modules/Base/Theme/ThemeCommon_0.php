<?php
/**
 * Theme class.
 * 
 * Provides module templating.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage theme
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * load Smarty library
 */
define('SMARTY_DIR', 'modules/Base/Theme/smarty/');

require_once(SMARTY_DIR.'Smarty.class.php');


class Base_ThemeCommon extends ModuleCommon {
	public static function init_smarty() {
		$smarty = new Smarty();
		
		$theme = self::get_default_template();

		$smarty->template_dir = DATA_DIR.'/Base_Theme/templates/'.$theme;
		$smarty->compile_dir = DATA_DIR.'/Base_Theme/compiled/';
		$smarty->compile_id = $theme;
		$smarty->config_dir = DATA_DIR.'/Base_Theme/config/';
		$smarty->cache_dir = DATA_DIR.'/Base_Theme/cache/';
		return $smarty;
	}
	
	public static function get_default_template() {
		static $theme;
		if(!isset($theme)) {
			$theme = Variable::get('default_theme');
			if(!is_dir(DATA_DIR.'/Base_Theme/templates/'.$theme))
				$theme = 'default';
		}
		return $theme;
	}
	
	public static function display_smarty($smarty, $module_name, $user_template=null, $fullname=false) {
		$module_name = str_replace('_','/',$module_name);
		if(isset($user_template)) {
			if (!$fullname)
				$module_name .= '/'.$user_template;
			else {
				if(preg_match("/.tpl$/i",$user_template)) {
					$tpl = $user_template;
					$css = str_replace('.tpl','.css',$tpl);
				} else
					$module_name = $user_template;
			}
		} else
			$module_name .= '/default';
		
		if(!isset($tpl)) {
			$tpl = $module_name.'.tpl';
			$css = $module_name.'.css';
		}
		

		if($smarty->template_exists($tpl)) {
			$smarty->assign('theme_dir',$smarty->template_dir);
			$smarty->display($tpl);
			if(isset($css)) {
				$cssf = $smarty->template_dir.'/'.$css;
				if(file_exists($cssf))
			    	load_css($cssf,$smarty->template_dir.'/__css.php');
			}
		} else {
			$smarty->template_dir = DATA_DIR.'/Base_Theme/templates/default';
			$smarty->compile_id = 'default';

			if(!$smarty->template_exists($tpl)) {
				trigger_error('Template not found: '.$tpl,E_USER_ERROR);
			}

			$smarty->assign('theme_dir',$smarty->template_dir);
			$smarty->display($tpl);
			if(isset($css)) {
				$cssf = $smarty->template_dir.'/'.$css;
				if(file_exists($cssf))
					load_css($cssf,$smarty->template_dir.'/__css.php');
			}

			$dt = self::get_default_template();
			$smarty->template_dir = DATA_DIR.'/Base_Theme/templates/'.$dt;
			$smarty->compile_id = $dt;
		}
	}


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
		$mod_name = str_replace('_','/',$mod_name);
		$data_dir = DATA_DIR.'/Base_Theme/templates/default';
		$content = scandir($directory);
		$mod_path = explode('/',$mod_name);
		$sum = '';
		foreach ($mod_path as $p) {
			$sum .= '/'.$p;
			@mkdir($data_dir.$sum);
		}
		foreach ($content as $name){
			if($name == '.' || $name == '..' || preg_match('/^[\.~]/i',$name)) continue;
			recursive_copy($directory.'/'.$name,$data_dir.'/'.$mod_name.'/'.$name);
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
		$data_dir = DATA_DIR.'/Base_Theme/templates/default/';

		$content = scandir($data_dir);
		foreach ($content as $name) {
			if($name == '.' || $name == '..' || preg_match('/^'.addcslashes($mod_name,'/').'/',$name)==0) continue;
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
		static $themes_dir;
		if(!isset($themes_dir))
			$themes_dir = DATA_DIR.'/Base_Theme/templates/';
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
		return str_replace("_", "/", $modulename).'/'.$filename;
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
	public static function get_template_file($modulename,$filename=null) {
		if(!isset($filename)) 
			$filename = $modulename;
		else
			$filename = self::get_template_filename($modulename,$filename);
		$f = self::get_template_dir().$filename;
		if(!is_readable($f)) {
			$f = DATA_DIR.'/Base_Theme/templates/default/'.$filename;
			if(!is_readable($f))
				throw new Exception('No such template file: '.$filename);
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
		
		try {
			$css = self::get_template_file($module_name,$css_name.'.css');
			load_css($css,self::get_template_dir().'__css.php');
			return true;
		} catch(Exception $e) {
			if($trig_error) trigger_error('Invalid css specified: '.$module_name.'/'.$css_name.'.css',E_USER_ERROR);
			return false;
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
		$theme_dir = DATA_DIR.'/Base_Theme/templates/';
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
		//css
		$themes_dir = DATA_DIR.'/Base_Theme/templates/';
		$def_theme = Variable::get('default_theme');
		$tdir = $themes_dir.$def_theme.'/';
		copy('modules/Base/Theme/css.php',$themes_dir.'default/__css.php');
		if($def_theme!='default')
			copy('modules/Base/Theme/css.php',$tdir.'/__css.php');
		//images
		self::create_images_cache();
	}
}

?>
