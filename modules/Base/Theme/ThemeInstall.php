<?php
/**
 * TestInstall class.
 * 
 * This class provides initialization data for Test module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @licence SPL
 * @package epesi-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides initialization data for Test module.
 * @package epesi-base-extra
 * @subpackage theme
 */
class Base_ThemeInstall extends ModuleInstall {
	public static function install() {
		mkdir('data/Base/Theme/templates');
		mkdir('data/Base/Theme/templates/default');
		mkdir('data/Base/Theme/compiled');
		mkdir('data/Base/Theme/cache');
		mkdir('data/Base/Theme/config');
		self::install_default_theme_common_files('modules/Base/Theme/','images');
		return Variable::set('default_theme','default');
	}
	
	public static function uninstall() {
		recursive_rmdir('data/Base/Theme/templates/default/images');
		return Variable::delete('default_theme');
	}
	
	public static function version() {
		return array('1.0.0');
	}
	
	public static function install_default_theme_common_files($dir,$f) {
		if(class_exists('ZipArchive')) {
			$zip = new ZipArchive;
			if ($zip->open($dir.$f.'.zip') === TRUE)
    			$zip->extractTo('data/Base/Theme/templates/default/');
		} else {
			mkdir('data/Base/Theme/templates/default/'.$f);
			$content = scandir($dir.$f);
			foreach ($content as $name){
				if ($name == '.' || $name == '..') continue;
				$path = $dir.$f.'/'.$name;
				if (is_dir($path))
					self::install_default_theme_common_files($dir,$f.'/'.$name);
				else
					copy($path,'data/Base/Theme/templates/default/'.$f.'/'.$name);
			}
		}
	}
}

?>
