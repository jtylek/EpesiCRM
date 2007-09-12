<?php
/**
 * @author Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package apps-gallery
 * @license SPL
 */
 defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_GalleryInstall extends ModuleInstall {
	public function install() {
		Base_ThemeCommon::install_default_theme('Apps/Gallery');
		$ret = DB::CreateTable('gallery_shared_media',"user_id I, media C(255) NOTNULL");
		if($ret === false) {
			print('Invalid SQL query - Gallery module install');
			return false;
		}
		$this->create_data_dir();
		mkdir($this->get_data_dir().'-1');
		return true;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('Apps/Gallery');
		return DB::DropTable('gallery_shared_media');
	}

	public static function info() {
		return array('Author'=>'<a href="mailto:kslawinski@telaxus.com">Kuba Slawinski</a> (<a href="http://www.telaxus.com">Telaxus LLC</a>)', 'License'=>'TL', 'Description'=>'Simple gallery module');
	}
	
	public static function simple_setup() {
		return true;
	}
	
	public function version() {
		return array('0.8.6');
	}
	
	public function requires($v) {
		return array(
			array('name'=>'Utils/TabbedBrowser', 'version'=>0), 
			array('name'=>'Utils/Path', 'version'=>0), 
			array('name'=>'Utils/Tree', 'version'=>0), 
			array('name'=>'Utils/Gallery', 'version'=>0)
		);
	}

}

?>
