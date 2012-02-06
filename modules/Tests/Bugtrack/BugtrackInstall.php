<?php
/**
 * Software Development - Bug Tracking
 *
 * @author Janusz Tylek <jtylek@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-tests
 * @subpackage bugtrack
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_BugtrackInstall extends ModuleInstall {

	public function install() {
		Base_LangCommon::install_translations($this->get_type());
		
		$fields = array(
			array('name'=>'Project Name', 'type'=>'text', 'required'=>true, 'param'=>'64', 'extra'=>false, 'visible'=>true,'display_callback'=>array('Tests_BugtrackCommon', 'display_bugtrack')),	
			array('name'=>'Company Name', 'type'=>'select', 'required'=>true, 'param'=>array('company'=>'Company Name'), 'extra'=>false, 'visible'=>true),
			array('name'=>'Due Date', 'type'=>'date', 'required'=>true, 'param'=>64, 'extra'=>false, 'visible'=>true),
			array('name'=>'Status', 'type'=>'commondata', 'required'=>true, 'param'=>'Bugtrack_Status', 'extra'=>false,'visible'=>true),
			array('name'=>'Description', 'type'=>'long text', 'required'=>false, 'param'=>'64', 'extra'=>false)
		);
		Utils_RecordBrowserCommon::install_new_recordset('bugtrack', $fields);
		Utils_RecordBrowserCommon::new_filter('bugtrack', 'Company Name');
		Utils_RecordBrowserCommon::set_quickjump('bugtrack', 'Project Name');
		Utils_RecordBrowserCommon::set_favorites('bugtrack', true);
		Utils_RecordBrowserCommon::set_recent('bugtrack', 15);
		Utils_RecordBrowserCommon::set_caption('bugtrack', 'Bugtrack');
		Utils_RecordBrowserCommon::set_icon('bugtrack', Base_ThemeCommon::get_template_filename('Tests/Bugtrack', 'icon.png'));
		
// ************ addons ************** //
		Utils_AttachmentCommon::new_addon('bugtrack');
		Utils_RecordBrowserCommon::new_addon('company', 'Tests/Bugtrack', 'company_bugtrack_addon', 'Bugtrack');

// ************ other ************** //	
		Utils_CommonDataCommon::new_array('Bugtrack_Status',array('new'=>'New','inprog'=>'In Progress','cl'=>'Closed'),true,true);

		Utils_RecordBrowserCommon::add_access('bugtrack', 'view', 'EMPLOYEE');
		Utils_RecordBrowserCommon::add_access('bugtrack', 'add', 'EMPLOYEE');
		Utils_RecordBrowserCommon::add_access('bugtrack', 'edit', 'EMPLOYEE');
		Utils_RecordBrowserCommon::add_access('bugtrack', 'delete', array('EMPLOYEE', 'ACCESS:manager'));

		return true;
	}
	
	public function uninstall() {
		Utils_RecordBrowserCommon::delete_addon('company', 'Tests/Bugtrack', 'company_bugtrack_addon');
		Utils_AttachmentCommon::delete_addon('bugtrack');
		Utils_RecordBrowserCommon::uninstall_recordset('bugtrack');
		Utils_CommonDataCommon::remove('Bugtrack_Status');
		return true;
	}
	
	public function version() {
		return array("0.1");
	}
	
	public function requires($v) {
		return array(
			array('name'=>'Base','version'=>0),
			array('name'=>'CRM/Contacts','version'=>0));
	}
	
	public static function info() {
		return array(
			'Description'=>' Software Development - Bug Tracking',
			'Author'=>'jtylek@telaxus.com',
			'License'=>'MIT');
	}
	
	public static function simple_setup() {
		return false;
	}
	
}

?>