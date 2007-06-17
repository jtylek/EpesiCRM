<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_ForumInstall extends ModuleInstall {

	public static function install() {
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
		$ret &= DB::CreateTable('apps_forum_mod','
			user_login_id I4 NOTNULL,
			apps_forum_board_id I4 NOTNULL',
			array('constraints'=>', FOREIGN KEY (apps_forum_board_id) REFERENCES apps_forum_board(id), FOREIGN KEY (user_login_id) REFERENCES user_login(id)'));
		if(!$ret){
			print('Unable to create table apps_forum_mod.<br>');
			return false;
		}
		Base_ThemeCommon::install_default_theme('Apps/Forum');
		return $ret;
	}
	
	public static function uninstall() {
		$ret = true;
		$ret &= DB::DropTable('apps_forum_board');
		$ret &= DB::DropTable('apps_forum_thread');
		$ret &= DB::DropTable('apps_forum_mod');
		Base_ThemeCommon::uninstall_default_theme('Apps/Forum');
		return $ret;
	}

	public static function info() {
		return array('Author'=>'<a href="mailto:abisaga@telaxus.com">Arkadiusz Bisaga</a> (<a href="http://www.telaxus.com">Telaxus LLC</a>)', 'Licence'=>'TL', 'Description'=>'Simple forum');
	}
	
	public static function simple_setup() {
		return true;
	}
	
}

?>