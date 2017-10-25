<?php
/**
 * Use this module if you want to add attachments to some page.
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage attachment
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_AttachmentInstall extends ModuleInstall {

	public function install() {
		$ret = true;
        Utils_RecordBrowserCommon::uninstall_recordset('utils_attachment');
        $fields = array(
            array(
                'name' => _M('Edited on'),
                'type' => 'timestamp',
                'extra'=>false,
                'visible'=>true,
                'required' => false,
                'display_callback'=>array('Utils_AttachmentCommon','display_date'),
                'QFfield_callback'=>array('Utils_AttachmentCommon','QFfield_date')
            ),
            array(
                'name' => _M('Title'),
                'type' => 'text',
                'param' => 255,
                'required' => false, 'extra' => false, 'visible' => false
            ),
            array('name' => _M('Note'),
                'type' => 'long text',
                'required' => false,
                'extra' => false,
                'visible'=>true,
                'display_callback'=>array('Utils_AttachmentCommon','display_note'),
                'QFfield_callback'=>array('Utils_AttachmentCommon','QFfield_note'),
            ),
        	array('name' => _M('Files'),
        		'type' => 'file',
        		'required' => false,
        		'extra' => false,
        		'visible'=>false,
        		'QFfield_callback'=>array('Utils_AttachmentCommon','QFfield_files'),
       		),
            array('name' => _M('Permission'),
                'type' => 'commondata',
                'required' => true,
                'param' => array('order_by_key' => true, 'CRM/Access'),
                'extra' => false),
            array('name' => _M('Sticky'),
                'type' => 'checkbox',
                'visible' => true,
                'extra' => false),
            array('name' => _M('Crypted'),
                'type' => 'checkbox',
                'extra' => false,
                'QFfield_callback'=>array('Utils_AttachmentCommon','QFfield_crypted')),
        	array('name' => _M('Attached to'),
        		'type' => 'multiselect',
        		'param' => '__RECORDSETS__::;',
        		'required' => false,
        		'extra' => false,
        		'visible'=>false,
        	),
        );
        Utils_RecordBrowserCommon::install_new_recordset('utils_attachment',$fields);
        Utils_RecordBrowserCommon::add_access('utils_attachment', 'view', 'ACCESS:employee', array('(!permission'=>2, '|:Created_by'=>'USER_ID'));
        Utils_RecordBrowserCommon::add_access('utils_attachment', 'delete', 'ACCESS:employee', array(':Created_by'=>'USER_ID'));
        Utils_RecordBrowserCommon::add_access('utils_attachment', 'delete', array('ACCESS:employee','ACCESS:manager'));
        Utils_RecordBrowserCommon::add_access('utils_attachment', 'add', 'ACCESS:employee',array(),array('edited_on'));
        Utils_RecordBrowserCommon::add_access('utils_attachment', 'edit', 'ACCESS:employee', array('(permission'=>0, '|:Created_by'=>'USER_ID'),array('edited_on'));
        Utils_RecordBrowserCommon::register_processing_callback('utils_attachment',array('Utils_AttachmentCommon','submit_attachment'));
		Utils_RecordBrowserCommon::register_custom_access_callback('utils_attachment', array('Utils_AttachmentCommon', 'rb_access'));
        Utils_RecordBrowserCommon::set_tpl('utils_attachment', Base_ThemeCommon::get_template_filename(Utils_AttachmentInstall::module_name(), 'View_entry'));
        Utils_RecordBrowserCommon::enable_watchdog('utils_attachment', array('Utils_AttachmentCommon','watchdog_label'));
        Utils_RecordBrowserCommon::set_caption('utils_attachment', _M('Note'));
        Utils_RecordBrowserCommon::set_description_callback('utils_attachment', array('Utils_AttachmentCommon','description_callback'));
        Utils_RecordBrowserCommon::set_jump_to_id('utils_attachment', false);
        Utils_RecordBrowserCommon::set_search('utils_attachment',1,0);

		$this->create_data_dir();
		file_put_contents($this->get_data_dir().'.htaccess','deny from all');
		Base_ThemeCommon::install_default_theme($this->get_type());
		
		Base_AclCommon::add_permission(_M('Attachments - view full download history'), array('ACCESS:employee'));
		return $ret;
	}

	public function uninstall() {
		Base_AclCommon::delete_permission('Attachments - view full download history');
		$ret = true;

        Utils_RecordBrowserCommon::uninstall_recordset('utils_attachment');
		Base_ThemeCommon::uninstall_default_theme($this->get_type());
		return $ret;
	}

	public function version() {
		return array("1.0");
	}

	public function requires($v) {
		return array(array('name'=>Utils_RecordBrowserInstall::module_name(),'version'=>0),
				array('name'=>Utils_GenericBrowserInstall::module_name(),'version'=>0),
			     array('name'=>Utils_FileUploadInstall::module_name(), 'version'=>0),
			     array('name'=>Utils_FileStorageInstall::module_name(), 'version'=>0),
			     array('name'=>Utils_BBCodeInstall::module_name(), 'version'=>0),
                 array('name'=>CRM_CommonInstall::module_name(), 'version'=>0),
			     array('name'=>Libs_QuickFormInstall::module_name(), 'version'=>0),
			     array('name'=>Libs_CKEditorInstall::module_name(), 'version'=>0),
			     array('name'=>Libs_LeightboxInstall::module_name(), 'version'=>0),
			     array('name'=>Utils_TooltipInstall::module_name(), 'version'=>0),
			     array('name'=>Utils_WatchdogInstall::module_name(), 'version'=>0),
			     array('name'=>Base_RegionalSettingsInstall::module_name(), 'version'=>0),
			     array('name'=>Base_LangInstall::module_name(),'version'=>0),
			     array('name'=>Base_BoxInstall::module_name(), 'version'=>0),
			     array('name'=>Base_ThemeInstall::module_name(), 'version'=>0),
			     array('name'=>Base_ActionBarInstall::module_name(), 'version'=>0));
	}

	public static function info() {
		return array(
			'Description'=>'Use this module if you want to add attachments to some page.',
			'Author'=>'pbukowski@telaxus.com',
			'License'=>'MIT');
	}

	public static function simple_setup() {
		return __('EPESI Core');
	}

}

?>
