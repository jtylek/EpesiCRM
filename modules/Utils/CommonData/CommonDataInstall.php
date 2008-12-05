<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-utils
 * @subpackage CommonData
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_CommonDataInstall extends ModuleInstall {

	public function install() {
		Base_LangCommon::install_translations($this->get_type());
		$ret = true;
		$ret &= DB::CreateTable('utils_commondata_tree','
			id I4 AUTO KEY,
			parent_id I4 DEFAULT -1,
			akey C(64) NOTNULL,
			value X,
			readonly I1 DEFAULT 0',
			array('constraints'=>', UNIQUE(parent_id,akey)'));
		if(!$ret){
			print('Unable to create table utils_commondata_tree.<br>');
			return false;
		}
		$this->add_aco('manage','Super administrator');
		Base_ThemeCommon::install_default_theme($this->get_type());
		return $ret;
	}
	
	public function uninstall() {
		global $database;
		$ret = true;
		$ret &= DB::DropTable('utils_commondata_tree');
		Base_ThemeCommon::uninstall_default_theme($this->get_type());
		return $ret;
	}
	public function requires($v) {
		return array(
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Base/Theme','version'=>0),
			array('name'=>'Base/ActionBar','version'=>0),
			array('name'=>'Base/Admin','version'=>0),
			array('name'=>'Utils/GenericBrowser','version'=>0));
	}
}

?>