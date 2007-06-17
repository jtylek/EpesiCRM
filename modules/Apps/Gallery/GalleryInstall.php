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
		Base_ThemeCommon::install_default_theme('Utils/Path');
		$ret = DB::CreateTable('gallery_shared_media',"user_id I, media C(255) NOTNULL");
		if($ret === false) {
			print('Invalid SQL query - Gallery module install');
			return false;
		}
		return true;
	}
	
	public static function uninstall() {
		return true;
	}

	public static function info() {
		return array('Author'=>'<a href="mailto:kslawinski@telaxus.com">Kuba Slawinski</a> (<a href="http://www.telaxus.com">Telaxus LLC</a>)', 'Licence'=>'TL', 'Description'=>'Simple gallery module');
	}
	
	public static function simple_setup() {
		return true;
	}
}

?>
