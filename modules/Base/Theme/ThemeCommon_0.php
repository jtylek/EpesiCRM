<?php
/**
 * Theme class.
 * 
 * Provides module templating.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @licence SPL
 * @package epesi-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_ThemeCommon {
	public static function install_default_theme($mod_name,$theme_dir='theme') {
		$directory = 'modules/'.str_replace('_','/',$mod_name).'/'.$theme_dir;
		$mod_name = str_replace('/','_',$mod_name);
		$data_dir = 'data/Base/Theme/templates/default/';
		$content = scandir($directory);
		foreach ($content as $name){
			if($name == '.' || $name == '..' || ereg('^[\.~]',$name)) continue;
			recursive_copy($directory.'/'.$name,$data_dir.$mod_name.'__'.$name);
		}
	}
	
	public static function uninstall_default_theme($mod_name) {
		$directory = str_replace('_','/',$mod_name);
		$mod_name = str_replace('/','_',$mod_name);
		$data_dir = 'data/Base/Theme/templates/default/';

		$content = scandir($data_dir);
		foreach ($content as $name){
			if($name == '.' || $name == '..' || ereg('^'.$mod_name,$name)===false) continue;
			$name = $data_dir.'/'.$name;
			recursive_rmdir($name);
//			if (!is_dir($name))
//				unlink($name);
		}
	}	
	
	public static function get_template_dir() {
		static $theme = null;
		static $themes_dir = 'data/Base/Theme/templates/';
		if(!isset($theme)) {
			$theme = Variable::get('default_theme');
		
			if(!is_dir($themes_dir.$theme))
				$theme = 'default';
		}
		
		return $themes_dir.$theme.'/';
	}

	public static function get_template_file_name($modulename,$filename) {
		return str_replace("/", "_", $modulename).'__'.str_replace("/", "_", $filename);
	}

	public static function get_template_file($modulename,$filename) {
		$filename = self::get_template_file_name($modulename,$filename);
		$f = self::get_template_dir().$filename;
		if(!is_readable($f)) {
			$f = 'data/Base/Theme/templates/default/'.$filename;
			if(!is_readable($f))
				return false;
		}
		return $f;
	}

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
		$themes_dir = 'data/Base/Theme/templates/';
		$def_theme = Variable::get('default_theme');
		$tdir = $theme_dir.$def_theme;
		$arr = glob($themes_dir.'default/*.css',GLOB_NOSORT);
		$css_def_out = '';
		$css_cur_out = '';
		$files_def_out = '';
		$files_cur_out = '';
		foreach($arr as $f) {
			$name = basename($f);
			if(is_readable($tdir.$name)) {
				$css_cur_out .= file_get_contents($tdir.$name)."\n";
				$files_cur_out .= $tdir.$name."\n";
			} else {
				$css_def_out .= file_get_contents($f)."\n";
				$files_def_out .= $f."\n";
			}
		}		
		file_put_contents($themes_dir.'default/__cache.css',$css_def_out);
		file_put_contents($themes_dir.'default/__cache.files',$files_def_out);
		if($def_theme!='default') {
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
		$theme_dir = 'data/Base/Theme/templates/';
		$default = self::get_images($theme_dir.'default');
		file_put_contents($theme_dir.'default/__cache.images',implode("\n",$default));
		$def_theme = Variable::get('default_theme');
		if($def_theme!='default') {
			$tdir = $theme_dir.$def_theme;
			$theme = self::get_images($tdir);
			file_put_contents($tdir.'/__cache.images',implode("\n",$theme));
		}		
	}

	public static function create_cache() {
		self::create_css_cache();
		self::create_images_cache();
	}
}
?>
