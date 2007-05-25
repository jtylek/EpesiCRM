<?php
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
//			if (!is_dir($directory.'/'.$name))
//				copy($directory.'/'.$name,$data_dir.$mod_name.'__'.$name);
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

	public static function get_template_file($modulename,$filename) {
		$f = self::get_template_dir().$modulename.'__'.$filename;
		if(!is_readable($f)) {
			$f = 'data/Base/Theme/templates/default/'.$modulename.'__'.$filename;
			if(!is_readable($f))
				return false;
		}
		return $f;
	}

	public static function load_css($module_name,$css_name = 'default',$trig_error=true) {
		if(!isset($module_name)) 
			trigger_error('Invalid argument for load_css, no module was specified.',E_USER_ERROR);
		
		$module_name = str_replace("/", "_", $module_name).'__'.str_replace("/", "_", $css_name);

		$css = self::get_template_file($module_name.'.css');
		if(!$css) {
			if($trig_error) trigger_error('Invalid css specified: '.$module_name.'.css',E_USER_ERROR);
			return false;
		} else {
			load_css($css);
			return true;
		}
	}
	

}
?>
