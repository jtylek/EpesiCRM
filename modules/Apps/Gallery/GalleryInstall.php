<?php
/**
 * @author Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package apps-gallery
 * @licence SPL
 */
 defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_GalleryInstall extends ModuleInstall {
	public static function install() {
		Base_ThemeCommon::install_default_theme('Apps/Gallery');
		$ret = DB::CreateTable('gallery_shared_media',"user_id I, media C(255) NOTNULL");
		if($ret === false) {
			print('Invalid SQL query - Gallery module install');
			return false;
		}
		mkdir('data/Apps_Gallery/-1');
		return true;
	}
	
	public static function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('Apps/Gallery');
		return DB::DropTable('gallery_shared_media');
	}

	public static function info() {
		return array('Author'=>'<a href="mailto:kslawinski@telaxus.com">Kuba Slawinski</a> (<a href="http://www.telaxus.com">Telaxus LLC</a>)', 'Licence'=>'TL', 'Description'=>'Simple gallery module');
	}
	
	public static function simple_setup() {
		return true;
	}
	
	public static function version() {
		return array('0.8.6');
	}
	
	public static function requires_0() {
		return array(
			array('name'=>'Utils/TabbedBrowser', 'version'=>0), 
			array('name'=>'Utils/Path', 'version'=>0), 
			array('name'=>'Utils/Tree', 'version'=>0), 
			array('name'=>'Utils/Gallery', 'version'=>0)
		);
	}

}

?>
