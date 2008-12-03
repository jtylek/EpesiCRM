<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-apps
 * @subpackage forum
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_ForumInstall extends ModuleInstall {

	public function install() {
		Base_LangCommon::install_translations($this->get_type());
		$ret = true;
		$ret &= DB::CreateTable('apps_forum_board','
			id I4 AUTO KEY,
			name C(64) NOTNULL,
			descr C(255)',
			array('constraints'=>''));
		if(!$ret){
			print('Unable to create table apps_forum_board.<br>');
			return false;
		}
		$ret &= DB::CreateTable('apps_forum_thread','
			id I4 AUTO KEY,
			topic C(128) NOTNULL,
			apps_forum_board_id I4 NOTNULL',
			array('constraints'=>', FOREIGN KEY (apps_forum_board_id) REFERENCES apps_forum_board(id)'));
		if(!$ret){
			print('Unable to create table apps_forum_thread.<br>');
			return false;
		}
		Base_ThemeCommon::install_default_theme('Apps/Forum');
		return $ret;
	}
	
	public function uninstall() {
		$ret = true;
		$ret &= DB::DropTable('apps_forum_board');
		$ret &= DB::DropTable('apps_forum_thread');
		Base_ThemeCommon::uninstall_default_theme('Apps/Forum');
		return $ret;
	}

	public static function info() {
		return array('Author'=>'<a href="mailto:abisaga@telaxus.com">Arkadiusz Bisaga</a> (<a href="http://www.telaxus.com">Telaxus LLC</a>)', 'License'=>'MIT', 'Description'=>'Simple forum');
	}
	
	public static function simple_setup() {
		return true;
	}
	
	public function version() {
		return array('1.0');
	}

	public function requires($v) {
		return array(
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Base/Theme','version'=>0),
			array('name'=>'Base/User','version'=>0),
			array('name'=>'Libs/QuickForm','version'=>0),
			array('name'=>'Utils/Comment','version'=>0));
	}	
}

?>