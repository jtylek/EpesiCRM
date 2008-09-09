<?php
/**
 *
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package crm-Filters
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_FiltersInstall extends ModuleInstall {

	public function install() {
		Base_LangCommon::install_translations($this->get_type());
		$ret = true;
		$ret &= DB::CreateTable('crm_filters_group','
			id I4 AUTO KEY,
			name C(128) NOTNULL,
			description C(255),
			user_login_id I4 NOTNULL',
			array('constraints'=>', UNIQUE(name,description), FOREIGN KEY (user_login_id) REFERENCES user_login(ID)'));
		if(!$ret){
			print('Unable to create table crm_filters_group.<br>');
			return false;
		}
		$ret &= DB::CreateTable('crm_filters_contacts','
			group_id I4 NOTNULL,
			contact_id I4',
			array('constraints'=>', FOREIGN KEY (group_id) REFERENCES crm_filters_group(id)'));
		if(!$ret){
			print('Unable to create table crm_filters_contacts.<br>');
			return false;
		}
		Base_ThemeCommon::install_default_theme($this -> get_type());
		$this->add_aco('manage','Employee');
		return $ret;
	}

	public function uninstall() {
		$ret = true;
		$ret &= DB::DropTable('crm_filters_contacts');
		$ret &= DB::DropTable('crm_filters_group');
		Base_ThemeCommon::uninstall_default_theme($this -> get_type());
		return $ret;
	}

	public function version() {
		return array("0.8");
	}

	public function requires($v) {
		return array(
			array('name'=>'Base/ActionBar','version'=>0),
			array('name'=>'Base/Theme','version'=>0),
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Base/User/Settings','version'=>0),
			array('name'=>'Utils/RecordBrowser/RecordPicker','version'=>0),
			array('name'=>'Utils/GenericBrowser','version'=>0),
			array('name'=>'CRM/Contacts','version'=>0),
			array('name'=>'Libs/QuickForm','version'=>0));
	}

	public static function info() {
		return array(
			'Description'=>'',
			'Author'=>'pbukowski@telaxus.com',
			'License'=>'SPL');
	}

	public static function simple_setup() {
		return true;
	}

}

?>
