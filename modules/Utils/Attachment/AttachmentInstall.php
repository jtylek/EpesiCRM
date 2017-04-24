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
                'type' => 'calculated',
                'extra' => false,
                'display_callback'=>array('Utils_AttachmentCommon','display_attached_to')),
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

        $ret &= DB::CreateTable('utils_attachment_local','
			local C(255) NOTNULL,
			attachment I4 NOTNULL,
			func C(255),
			args C(255)',
            array('constraints'=>', FOREIGN KEY (attachment) REFERENCES utils_attachment_data_1(ID)'));
        if(!$ret){
            print('Unable to create table utils_attachment_local.<br>');
            return false;
        }
        DB::CreateIndex('utils_attachment_local__idx', 'utils_attachment_local', 'local');

		$ret &= DB::CreateTable('utils_attachment_file','
			id I4 AUTO KEY NOTNULL,
			attach_id I4 NOTNULL,
			filestorage_id I8 NOTNULL,
			original C(255) NOTNULL,
			created_by I4,
			created_on T DEFTIMESTAMP,
			deleted I1 NOTNULL DEFAULT 0',
			array('constraints'=>', FOREIGN KEY (created_by) REFERENCES user_login(ID), FOREIGN KEY (attach_id) REFERENCES utils_attachment_data_1(id), FOREIGN KEY (filestorage_id) REFERENCES utils_filestorage(id)'));
		if(!$ret){
			print('Unable to create table utils_attachment_file.<br>');
			return false;
		}
        DB::CreateIndex('attach_id_idx','utils_attachment_file','attach_id');
		$ret &= DB::CreateTable('utils_attachment_download','
			id I4 AUTO KEY NOTNULL,
			attach_file_id I4 NOTNULL,
			created_by I4,
			created_on T,
			expires_on T,
			remote I1 DEFAULT 0,
			download_on T DEFTIMESTAMP,
			ip_address C(32),
			host_name C(64),
			description C(128),
			token C(32)',
			array('constraints'=>', FOREIGN KEY (created_by) REFERENCES user_login(ID), FOREIGN KEY (attach_file_id) REFERENCES utils_attachment_file(id)'));
		if(!$ret){
			print('Unable to create table utils_attachment_download.<br>');
			return false;
		}
		$ret &= DB::CreateTable('utils_attachment_clipboard','
			id I4 AUTO KEY NOTNULL,
			filename C(255),
			created_by I4,
			created_on T DEFTIMESTAMP',
			array('constraints'=>''));

		$this->create_data_dir();
		file_put_contents($this->get_data_dir().'.htaccess','deny from all');
		Base_ThemeCommon::install_default_theme($this->get_type());
		
		Base_AclCommon::add_permission(_M('Attachments - view full download history'), array('ACCESS:employee'));
		return $ret;
	}

	public function uninstall() {
		Base_AclCommon::delete_permission('Attachments - view full download history');
		$ret = true;

		$ret &= DB::DropTable('utils_attachment_download');
		$ret &= DB::DropTable('utils_attachment_file');
		$ret &= DB::DropTable('utils_attachment_local');
        Utils_RecordBrowserCommon::uninstall_recordset('utils_attachment');
		Base_ThemeCommon::uninstall_default_theme($this->get_type());
		return $ret;
	}

	public function version() {
		return array("1.0");
	}

	public function requires($v) {
		return array(array('name'=>Utils_GenericBrowserInstall::module_name(),'version'=>0),
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
