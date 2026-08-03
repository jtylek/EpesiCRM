<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * Reference module for AI-shared/Dev-Tutorial.md - exercises every
 * RecordBrowser field type in one real, installable table. See that file
 * for the full write-up of each type used below.
 */
class Custom_TutorialInstall extends ModuleInstall {

	public function install() {
		// Small lookup recordset - the target of this module's 'select'/
		// 'multiselect' fields (Category / Related Categories below), and the
		// table an addon tab is registered on further down.
		$category_fields = array(
			// display_callback makes the grid's Name value a link to the
			// record (create_linked_text) - without one, a text field just
			// renders as plain text in GenericBrowser listings.
			array('name'=>_M('Name'), 'type'=>'text', 'required'=>true, 'param'=>'64', 'visible'=>true, 'filter'=>true, 'display_callback'=>array('Custom_TutorialCommon', 'display_category_name')),
			array('name'=>_M('Description'), 'type'=>'text', 'param'=>'255', 'visible'=>true),
			// 'crm_contact' is a *registered datatype* (Utils_RecordBrowserCommon::
			// register_datatype()), not a core RecordBrowser type - CRM_Contacts
			// rewrites it into a 'select'/'multiselect' pointed at its own
			// 'contact' recordset (see ContactsCommon::crm_contact_datatype()).
			// 'crits' restricts the picker to this instance's own staff -
			// Custom_TutorialCommon::employees_crits() below mirrors the same
			// helper CRM_PhoneCall/CRM_Meeting/CRM_Tasks each define for
			// themselves (there is no shared central one to call instead).
			array('name'=>_M('Manager'), 'type'=>'crm_contact', 'param'=>array('field_type'=>'select', 'crits'=>array('Custom_TutorialCommon', 'employees_crits'), 'format'=>array('CRM_ContactsCommon', 'contact_format_no_company')), 'visible'=>true),
		);
		Utils_RecordBrowserCommon::install_new_recordset('tutorial_category', $category_fields);
		Utils_RecordBrowserCommon::set_caption('tutorial_category', _M('Tutorial Categories'));
		Utils_RecordBrowserCommon::add_access('tutorial_category', 'view', 'ACCESS:employee');
		Utils_RecordBrowserCommon::add_access('tutorial_category', 'add', 'ACCESS:employee');
		Utils_RecordBrowserCommon::add_access('tutorial_category', 'edit', 'ACCESS:employee');
		Utils_RecordBrowserCommon::add_access('tutorial_category', 'delete', 'ACCESS:employee');
		// Addon tab shown when viewing a category record: lists every
		// 'tutorial' record whose Category points back at it. Registered on
		// 'tutorial_category' (a table this module owns), so
		// uninstall_recordset('tutorial_category') below cleans this row up
		// automatically - no separate delete_addon() call needed (contrast
		// Tests_Bugtrack, whose addon lives on 'company', a table it does
		// NOT own, so its uninstall() must call delete_addon() explicitly).
		Utils_RecordBrowserCommon::new_addon('tutorial_category', 'Custom/Tutorial', 'category_records_addon', _M('Tutorial Records'));

		// Fixed dropdown list backing the 'commondata' field below.
		Utils_CommonDataCommon::new_array('Tutorial_Priority', array(
			'low'=>_M('Low'),
			'medium'=>_M('Medium'),
			'high'=>_M('High'),
		), true, true);

		$fields = array(
			// Every required field belongs on the main form (above the
			// tabs), never inside a page_split tab - a required field buried
			// in a tab a user hasn't opened yet makes "why won't this save"
			// errors much harder to notice. Priority leads (defaulted to
			// Medium in Custom_Tutorial::body()/category_records_addon()),
			// then Category (every record is grouped under one - see
			// category_records_addon()), then Title/Description.
			array('name'=>_M('Priority'), 'type'=>'commondata', 'required'=>true, 'param'=>'Tutorial_Priority', 'visible'=>true, 'filter'=>true),
			array('name'=>_M('Category'), 'type'=>'select', 'required'=>true, 'param'=>array('tutorial_category'=>'Name'), 'visible'=>true, 'filter'=>true),
			array('name'=>_M('Title'), 'type'=>'text', 'required'=>true, 'param'=>'128', 'visible'=>true, 'filter'=>true, 'display_callback'=>array('Custom_TutorialCommon', 'display_title')),
			array('name'=>_M('Description'), 'type'=>'long text', 'param'=>'500'),

			array('name'=>_M('Details'), 'type'=>'page_split', 'extra'=>false),
			array('name'=>_M('Quantity'), 'type'=>'integer'),
			array('name'=>_M('Weight'), 'type'=>'float'),
			array('name'=>_M('Budget'), 'type'=>'currency'),
			array('name'=>_M('Is Active'), 'type'=>'checkbox', 'visible'=>true),
			array('name'=>_M('Due Date'), 'type'=>'date', 'visible'=>true, 'filter'=>true),
			array('name'=>_M('Scheduled At'), 'type'=>'timestamp'),
			array('name'=>_M('Duration'), 'type'=>'time'),

			array('name'=>_M('Classification'), 'type'=>'page_split', 'extra'=>false),
			array('name'=>_M('Related Categories'), 'type'=>'multiselect', 'param'=>array('tutorial_category'=>'Name')),
			array('name'=>_M('Reference No'), 'type'=>'autonumber', 'param'=>Utils_RecordBrowserCommon::encode_autonumber_param('TUT-', 5, '0'), 'visible'=>true),

			array('name'=>_M('Attachments and System'), 'type'=>'page_split', 'extra'=>false),
			array('name'=>_M('Attachment'), 'type'=>'file', 'visible'=>true),
			// 'hidden': a real column the user never sees a form field for -
			// populated by Custom_TutorialCommon::submit_tutorial() below.
			// param must be a real, dialect-translated column type (not a bare
			// ADOdb type code) - actual_db_type() is how RecordBrowser core
			// itself computes that for text/integer/etc (see e.g.
			// CRM/Mail/MailInstall.php's own 'hidden' fields for the same call).
			array('name'=>_M('Internal Token'), 'type'=>'hidden', 'param'=>Utils_RecordBrowserCommon::actual_db_type('text', 36)),
			// 'calculated': needs a real (if otherwise unused) column so
			// get_val()'s array_key_exists() check passes; its displayed
			// value is entirely computed by the display_callback from other
			// fields already present in the record.
			array('name'=>_M('Summary'), 'type'=>'calculated', 'param'=>Utils_RecordBrowserCommon::actual_db_type('text', 1), 'visible'=>true, 'display_callback'=>array('Custom_TutorialCommon', 'display_summary')),
		);
		Utils_RecordBrowserCommon::install_new_recordset('tutorial', $fields);
		Utils_RecordBrowserCommon::set_caption('tutorial', _M('Tutorial'));
		Utils_RecordBrowserCommon::set_recent('tutorial', 15);
		Utils_RecordBrowserCommon::register_processing_callback('tutorial', array('Custom_TutorialCommon', 'submit_tutorial'));

		Utils_RecordBrowserCommon::add_access('tutorial', 'view', 'ACCESS:employee');
		Utils_RecordBrowserCommon::add_access('tutorial', 'add', 'ACCESS:employee');
		Utils_RecordBrowserCommon::add_access('tutorial', 'edit', 'ACCESS:employee');
		Utils_RecordBrowserCommon::add_access('tutorial', 'delete', 'ACCESS:employee');

		return true;
	}

	public function uninstall() {
		// Reverse install() completely - order matters: drop the table that
		// references tutorial_category before dropping tutorial_category
		// itself, and remove the CommonData array we created.
		Utils_RecordBrowserCommon::uninstall_recordset('tutorial');
		Utils_RecordBrowserCommon::uninstall_recordset('tutorial_category');
		Utils_CommonDataCommon::remove('Tutorial_Priority');
		return true;
	}

	public function requires($v) {
		return array(
			array('name'=>Utils_RecordBrowserInstall::module_name(), 'version'=>0),
			array('name'=>Utils_CommonDataInstall::module_name(), 'version'=>0),
			array('name'=>Utils_CurrencyFieldInstall::module_name(), 'version'=>0),
			array('name'=>Utils_FileUploadInstall::module_name(), 'version'=>0),
			array('name'=>Utils_FileStorageInstall::module_name(), 'version'=>0),
			// Manager (crm_contact -> 'contact' recordset + contact-format helpers).
			array('name'=>CRM_ContactsInstall::module_name(), 'version'=>0),
		);
	}

	public function version() {
		return array('1.0');
	}

	public static function info() {
		return array(
			'Description' => 'Reference/tutorial module exercising every Epesi RecordBrowser field type in one table.',
			'Author' => 'EPESI Dev Tutorial',
			'License' => 'MIT');
	}

	// Shown in Epesi Store's "Simple view" as its own installable package.
	public static function simple_setup() {
		return array('package'=>_M('Tutorial'));
	}
}
?>
