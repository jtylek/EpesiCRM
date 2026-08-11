<?php
/**
 * Use this module if you want to add attachments to some page.
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Janusz Tylek
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

        // 'Features Configuration' admin panel table - see AttachmentCommon_0.
        // php's admin_caption()/QFfield_recordset()/processing_related() -
        // same shape as CRM_Tasks' task_related / CRM_Meeting's
        // crm_meeting_related. Each row just wires the Attachment "Notes"
        // addon onto the picked recordset via new_addon()/delete_addon().
        $fields = array(
            array(
                'name'  => _M('Recordset'),
                'type'  => 'text',
                'param' => 64,
                'display_callback' => array('Utils_AttachmentCommon', 'display_recordset'),
                'QFfield_callback' => array('Utils_AttachmentCommon', 'QFfield_recordset'),
                'required' => true,
                'extra'    => false,
                'visible'  => true,
            ),
        );
        Utils_RecordBrowserCommon::install_new_recordset('utils_attachment_related', $fields);
        Utils_RecordBrowserCommon::set_caption('utils_attachment_related', _M('Attachments Related Recordsets'));
        Utils_RecordBrowserCommon::register_processing_callback('utils_attachment_related', array('Utils_AttachmentCommon', 'processing_related'));
        Utils_RecordBrowserCommon::add_access('utils_attachment_related', 'view', 'ACCESS:employee');
        Utils_RecordBrowserCommon::add_access('utils_attachment_related', 'add', 'ADMIN');
        Utils_RecordBrowserCommon::add_access('utils_attachment_related', 'edit', 'SUPERADMIN');
        Utils_RecordBrowserCommon::add_access('utils_attachment_related', 'delete', 'SUPERADMIN');

        $fields = array(
            array(
                'name' => _M('Edited on'),
                'type' => 'timestamp',
                'extra'=>false,
                'visible'=>true,
                'required' => false,
                // Rendered as 3 lines (date/time/user - see display_date()), so
                // it must never get the generic single-line "expandable" grid
                // collapse (RecordBrowser_0.php's $args['style']=='noexpand'
                // check) that clips any other cell taller than one line.
                'style' => 'noexpand',
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
                // display_note() renders title<br>body for the browse/mini-view
                // preview - the generic single-line "expandable" grid collapse
                // (18px = one line) clipped it after just the title. Unlike
                // 'Edited on' (always exactly 3 fixed lines, so fully exempted
                // via 'noexpand'), this field's body is open-ended user text,
                // so it should stay collapsible - just with a taller collapsed
                // height that fits title + a couple of body lines instead of
                // one. See RecordBrowser_0.php's $args['style']=='tall_preview'
                // check and the matching CSS in
                // GenericBrowser/theme_adminlte/default.css.
                'style' => 'tall_preview',
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
                'extra' => false,
                'QFfield_callback'=>array('Utils_AttachmentCommon','QFfield_sticky')),
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
        Utils_RecordBrowserCommon::set_icon('utils_attachment', Base_ThemeCommon::get_template_filename(Utils_AttachmentInstall::module_name(), 'icon.png'));
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

        Utils_RecordBrowserCommon::uninstall_recordset('utils_attachment_related');
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
			     array('name'=>Libs_QuillInstall::module_name(), 'version'=>0),
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
