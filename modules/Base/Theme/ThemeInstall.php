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
 * @subpackage theme
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_ThemeInstall extends ModuleInstall {
	public function install() {
		mkdir('data/Base_Theme/templates');
		mkdir('data/Base_Theme/templates/default');
		mkdir('data/Base_Theme/compiled');
		mkdir('data/Base_Theme/cache');
		mkdir('data/Base_Theme/config');
		self::install_default_theme_common_files('modules/Base/Theme/','images');
		return Variable::set('default_theme','default');
	}
	
	public function uninstall() {
		recursive_rmdir('data/Base_Theme/templates/default/images');
		return Variable::delete('default_theme');
	}
	
	public function version() {
		return array('1.0.0');
	}
	
	public function install_default_theme_common_files($dir,$f) {
		if(class_exists('ZipArchive')) {
			$zip = new ZipArchive;
			if ($zip->open($dir.$f.'.zip') == 1) {
    			$zip->extractTo('data/Base_Theme/templates/default/');
    			return;
			}
		}
		mkdir('data/Base_Theme/templates/default/'.$f);
		$content = scandir($dir.$f);
		foreach ($content as $name){
			if ($name == '.' || $name == '..') continue;
			$path = $dir.$f.'/'.$name;
			if (is_dir($path))
				self::install_default_theme_common_files($dir,$f.'/'.$name);
			else
				copy($path,'data/Base_Theme/templates/default/'.$f.'/'.$name);
		}
	}
	public function requires($v) {
		return array();
	}
}

?>
