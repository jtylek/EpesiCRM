<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-utils
 * @subpackage comment
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_CommentInstall extends ModuleInstall{
	public function install(){
		$ret = DB::CreateTable('comment',"id I AUTO KEY, text X(4000) NOTNULL, user_login_id I NOTNULL, parent I DEFAULT -1 NOTNULL, topic C(255) NOTNULL, created_on T NOTNULL");
		if($ret===false) {
			print('Invalid SQL query - Comment module install: '.DB::error());
			return false;
		}

		$ret = DB::CreateTable('comment_report',"id I KEY, user_login_id I NOTNULL");
		if($ret===false) {
			print('Invalid SQL query - Comment module install: '.DB::error());
			return false;
		}

		Base_ThemeCommon::install_default_theme('Utils/Comment');
		return true;
	}

	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('Utils/Comment');
		return DB::DropTable('comment_report')
			&& DB::DropTable('comment');
	}
	
	public function version() {
		return array('1.0.0');
	}
	public function requires($v) {
		return array(
			array('name'=>'Base/Theme','version'=>0),
			array('name'=>'Base/User','version'=>0));
	}
} 
?>
