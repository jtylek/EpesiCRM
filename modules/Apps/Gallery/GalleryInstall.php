<?php
/**
 * Base_ImageInstall class.
 * 
 * @author Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-utils
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
		return true;
	}
	
	public static function uninstall() {
		DB::DropTable('gallery_shared_media');
		return true;
	}
}

?>
