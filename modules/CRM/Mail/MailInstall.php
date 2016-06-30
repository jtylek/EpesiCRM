<?php
/**
 * Core mail support - accounts, archive applet.
 * @author pbukowski@telaxus.com
 * @copyright Telaxus LLC
 * @license MIT
 * @version 0.1
 * @package epesi-CRM
 * @subpackage Mail
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_MailInstall extends ModuleInstall {

	public function install() {
		Base_ThemeCommon::install_default_theme($this -> get_type());
		$this->create_data_dir();
		$htaccess = $this->get_data_dir() . '.htaccess';
		$f = fopen($htaccess, 'w');
		if ($f === false) {
			print("Cannot create .htaccess file ($htaccess). "
					. "Your mail attachments may be available on the internet!");
		} else {
			fwrite($f, "deny from all\n");
			fclose($f);
		}

		Utils_CommonDataCommon::new_array('CRM/Mail/Security', array('tls'=>_M('TLS'),'ssl'=>_M('SSL')),true,true);
        //addons table
        $fields = array(
            array(
                'name'  => _M('Recordset'),
                'type'  => 'text',
                'param' => 64,
                'display_callback' => array(
                    $this->get_type() . 'Common',
                    'display_recordset',
                ),
                'QFfield_callback' => array(
                    $this->get_type() . 'Common',
                    'QFfield_recordset',
                ),
                'required' => true,
                'extra'    => false,
                'visible'  => true,
            ),
        );
        Utils_RecordBrowserCommon::install_new_recordset('rc_related', $fields);
        Utils_RecordBrowserCommon::set_caption('rc_related', _M('Mail Related Recordsets'));
        Utils_RecordBrowserCommon::register_processing_callback('rc_related', array('CRM_MailCommon', 'processing_related'));
        Utils_RecordBrowserCommon::add_access('rc_related', 'view', 'ACCESS:employee');
        Utils_RecordBrowserCommon::add_access('rc_related', 'add', 'ADMIN');
        Utils_RecordBrowserCommon::add_access('rc_related', 'edit', 'SUPERADMIN');
        Utils_RecordBrowserCommon::add_access('rc_related', 'delete', 'SUPERADMIN');
        Utils_RecordBrowserCommon::new_record('rc_related',array('recordset'=>'company'));
        Utils_RecordBrowserCommon::new_record('rc_related',array('recordset'=>'contact'));
        //accounts table
        $fields = array(
            array('name' => _M('EPESI User'),             'type'=>'integer', 'extra'=>false, 'visible'=>true, 'required'=>true, 'display_callback'=>array('CRM_MailCommon', 'display_epesi_user'), 'QFfield_callback'=>array('CRM_MailCommon', 'QFfield_epesi_user')),
            array('name' => _M('Email'),             'type'=>'text', 'extra'=>false, 'visible'=>true, 'required'=>true, 'param'=>128),
        	array('name' => _M('Account Name'),             'type'=>'text', 'extra'=>false, 'visible'=>true, 'required'=>true, 'param'=>32,'QFfield_callback'=>array('CRM_MailCommon','QFfield_account_name')),
        	array('name' => _M('Server'),             'type'=>'text', 'extra'=>false, 'visible'=>true, 'param'=>'255', 'required'=>true),
            array('name' => _M('Login'),              'type'=>'text', 'required'=>true, 'param'=>'255', 'extra'=>false, 'visible'=>true),
            array('name' => _M('Password'),           'type'=>'text', 'required'=>true,'extra'=>false, 'param'=>'255', 'visible'=>false, 'QFfield_callback'=>array('CRM_MailCommon','QFfield_password'), 'display_callback'=>array('CRM_MailCommon','display_password')),
            array('name' => _M('Security'),           'type'=>'commondata', 'param'=>array('CRM/Mail/Security'), 'extra'=>false, 'visible'=>false, 'QFfield_callback'=>array('CRM_MailCommon','QFfield_security')),

            array('name' => _M('SMTP Server'),             'type'=>'text', 'extra'=>false, 'visible'=>false, 'param'=>'255', 'required'=>true),
            array('name' => _M('SMTP Auth'),             'type'=>'checkbox', 'extra'=>false, 'visible'=>false, 'QFfield_callback'=>array('CRM_MailCommon','QFfield_smtp_auth')),
            array('name' => _M('SMTP Login'),              'type'=>'text', 'required'=>false, 'param'=>'255', 'extra'=>false, 'visible'=>false, 'QFfield_callback'=>array('CRM_MailCommon','QFfield_smtp_login')),
            array('name' => _M('SMTP Password'),           'type'=>'text', 'extra'=>false, 'param'=>'255', 'visible'=>false, 'QFfield_callback'=>array('CRM_MailCommon','QFfield_smtp_password'), 'display_callback'=>array('CRM_MailCommon','display_password')),
            array('name' => _M('SMTP Security'),           'type'=>'commondata', 'param'=>array('CRM/Mail/Security'), 'extra'=>false, 'visible'=>false, 'QFfield_callback'=>array('CRM_MailCommon','QFfield_smtp_security')),

            array('name' => _M('Default Account'),             'type'=>'checkbox', 'extra'=>false, 'visible'=>true, 'QFfield_callback'=>array('CRM_MailCommon','QFfield_default_account')),

            array('name' => _M('Advanced'), 'type'=>'page_split'),
            array('name' => _M('Archive on sending'), 'type'=>'checkbox', 'extra'=>true, 'visible'=>false),
            array('name' => _M('Use EPESI Archive directories'), 'type'=>'checkbox', 'extra'=>true, 'visible'=>false),
            array('name' => _M('IMAP Root'), 'type'=>'text', 'param'=>32, 'extra'=>true, 'visible'=>false),
            array('name' => _M('IMAP Delimiter'), 'type'=>'text', 'param'=>8, 'extra'=>true, 'visible'=>false)
        );
        Utils_RecordBrowserCommon::install_new_recordset('rc_accounts', $fields);
        Utils_RecordBrowserCommon::set_caption('rc_accounts', _M('Mail accounts'));
		Utils_RecordBrowserCommon::register_processing_callback('rc_accounts', array('CRM_MailCommon', 'submit_account'));

        $fields = array(
            array(
                'name' => _M('Subject'),
                'type'=>'text',
                'param'=>'256',
                'extra'=>false,
                'visible'=>true,
                'required'=>false,
                'display_callback'=>array('CRM_MailCommon','display_subject')
            ),
            array(
                'name' => _M('Count'),
                'type'=>'calculated',
                'extra'=>false,
                'visible'=>true,
                'required'=>false,
                'display_callback'=>array('CRM_MailCommon','display_thread_count'),
                'QFfield_callback'=>array('CRM_MailCommon','QFfield_thread_count')
            ),
            array(
                'name' => _M('Contacts'),
                'type'=>'crm_company_contact',
                'param'=>array('field_type'=>'multiselect'),
                'required'=>false,
                'extra'=>false,
                'visible'=>true
            ),
            array(
                'name' => _M('First Date'),
                'type'=>'timestamp',
                'extra'=>false,
                'visible'=>true,
                'required'=>false
            ),
            array(
                'name' => _M('Last Date'),
                'type'=>'timestamp',
                'extra'=>false,
                'visible'=>true,
                'required'=>false
            ),
            array(
                'name' => _M('Attachments'),
                'type'=>'calculated',
                'extra'=>false,
                'visible'=>true,
                'display_callback'=>array('CRM_MailCommon','display_thread_attachments'),
                'QFfield_callback'=>array('CRM_MailCommon','QFfield_thread_attachments')
            )
        );
        Utils_RecordBrowserCommon::install_new_recordset('rc_mail_threads', $fields);
        Utils_RecordBrowserCommon::set_caption('rc_mail_threads', _M('Mail Thread'));
        Utils_RecordBrowserCommon::new_addon('rc_mail_threads', CRM_MailInstall::module_name(), 'thread_addon', _M('E-mails'));
        Utils_RecordBrowserCommon::set_search('rc_mail_threads',2,-1);

        $fields = array(
            array(
                'name' => _M('Subject'),
                'type'=>'text',
                'param'=>'256',
                'extra'=>false,
                'visible'=>true,
                'required'=>false,
                'display_callback'=>array('CRM_MailCommon','display_subject')
            ),
            array(
                'name' => _M('Contacts'),
                'type'=>'crm_company_contact',
                'param'=>array('field_type'=>'multiselect'),
                'required'=>false,
                'extra'=>false,
                'visible'=>true
            ),
            array(
                'name' => _M('Employee'),
                'type'=>'crm_contact',
                'param'=>array('field_type'=>'select'),
                'extra'=>false,
                'visible'=>true,
                'required'=>false
            ),
            array(
                'name'     => _M('Related'),
                'type'     => 'multiselect',
                'QFfield_callback' => array(
                    'CRM_MailCommon',
                    'QFfield_related',
                ),
                'param'    => '__RECORDSETS__::;CRM_MailCommon::related_crits',
                'extra'    => false,
                'required' => false,
                'visible'  => true,
            ),
            array(
                'name' => _M('Date'),
                'type'=>'timestamp',
                'extra'=>false,
                'visible'=>true,
                'required'=>false
            ),
            array(
                'name' => _M('Attachments'),
                'type'=>'calculated',
                'extra'=>false,
                'visible'=>true,
                'display_callback'=>array('CRM_MailCommon','display_attachments'),
                'QFfield_callback'=>array('CRM_MailCommon','QFfield_attachments')
            ),
            array(
                'name' => _M('Headers Data'),
                'type'=>'long text',
                'extra'=>false,
                'visible'=>false,
                'required'=>false
            ),
            array(
                'name' => _M('Body'),
                'type'=>'long text',
                'extra'=>false,
                'visible'=>false,
                'required'=>false,
                'QFfield_callback'=>array('CRM_MailCommon','QFfield_body')
            ),
            array(
                'name' => _M('From'),
                'type'=>'text',
                'param'=>128,
                'extra'=>false,
                'visible'=>false,
                'required'=>false
            ),
            array(
                'name' => _M('To'),
                'type'=>'text',
                'param'=>4096,
                'extra'=>false,
                'visible'=>false,
                'required'=>false
            ),
            array(
                'name' => _M('Thread'),
                'type'=>'select',
                'param'=>'rc_mail_threads::Count',
                'extra'=>false,
                'visible'=>false,
                'required'=>false,
                'display_callback'=>array('CRM_MailCommon','display_mail_thread'),
                'QFfield_callback'=>array('CRM_MailCommon','QFfield_mail_thread')
            ),
            array(
                'name' => _M('Message ID'),
                'type'=>'text',
                'param'=>128,
                'extra'=>false,
                'visible'=>false,
                'required'=>false,
                'QFfield_callback'=>array('CRM_MailCommon','QFfield_hidden')
            ),
            array(
                'name' => _M('References'),
                'type'=>'text',
                'param'=>4096*4,
                'extra'=>false,
                'visible'=>false,
                'required'=>false,
                'QFfield_callback'=>array('CRM_MailCommon','QFfield_hidden')
            ),
        );
        Utils_RecordBrowserCommon::install_new_recordset('rc_mails', $fields);
        Utils_RecordBrowserCommon::set_caption('rc_mails', _M('Mails'));
		Utils_RecordBrowserCommon::set_tpl('rc_mails', Base_ThemeCommon::get_template_filename(CRM_MailInstall::module_name(), 'mails'));
    	Utils_RecordBrowserCommon::register_processing_callback('rc_mails', array('CRM_MailCommon', 'submit_mail'));
        Utils_RecordBrowserCommon::set_search('rc_mails',2,-1);
        Utils_RecordBrowserCommon::enable_watchdog('rc_mails', array('CRM_MailCommon', 'watchdog_label'));

        DB::CreateIndex('rc_mails_thread_idx', 'rc_mails_data_1', 'f_thread');
        DB::CreateIndex('rc_mails_msgid_idx', 'rc_mails_data_1', 'f_message_id');

		Utils_RecordBrowserCommon::new_addon('rc_mails', CRM_MailInstall::module_name(), 'mail_body_addon', _M('Body'));
        Utils_RecordBrowserCommon::new_addon('rc_mails', CRM_MailInstall::module_name(), 'attachments_addon', _M('Attachments'));
		Utils_RecordBrowserCommon::new_addon('rc_mails', CRM_MailInstall::module_name(), 'mail_headers_addon', _M('Headers'));

        @DB::DropTable('rc_mails_attachments');
        DB::CreateTable('rc_mails_attachments','
            mail_id I4 NOTNULL,
            type C(32),
            name C(255),
            mime_id C(32),
            attachment I1 DEFAULT 1',
            array('constraints'=>', FOREIGN KEY (mail_id) REFERENCES rc_mails_data_1(ID)'));
        DB::CreateTable('rc_mails_attachments_download','
            mail_id I4 NOTNULL,
            hash C(32),
            created_on T DEFTIMESTAMP',
            array('constraints'=>', FOREIGN KEY (mail_id) REFERENCES rc_mails_data_1(ID)'));

        Utils_RecordBrowserCommon::new_addon('contact', CRM_MailInstall::module_name(), 'addon', _M('E-mails'));
        Utils_RecordBrowserCommon::new_addon('company', CRM_MailInstall::module_name(), 'addon', _M('E-mails'));

		$fields = array(
			array('name' => _M('Record Type'), 	'type'=>'hidden', 'param'=>Utils_RecordBrowserCommon::actual_db_type('text',64), 'required'=>false, 'visible'=>false, 'filter'=>true, 'extra'=>false),
			array('name' => _M('Record ID'), 		'type'=>'hidden', 'param'=>Utils_RecordBrowserCommon::actual_db_type('integer'), 'filter'=>false, 'required'=>false, 'extra'=>false, 'visible'=>false),
			array('name' => _M('Nickname'), 		'type'=>'text', 'required'=>true, 'param'=>'64', 'extra'=>false, 'visible'=>true, 'QFfield_callback'=>array('CRM_MailCommon','QFfield_nickname')),
			array('name' => _M('Email'), 			'type'=>'email', 'required'=>true, 'param'=>array('unique'=>true), 'extra'=>false, 'visible'=>true)
		);

		Utils_RecordBrowserCommon::install_new_recordset('rc_multiple_emails', $fields);

		Utils_RecordBrowserCommon::set_favorites('rc_multiple_emails', true);
		Utils_RecordBrowserCommon::set_caption('rc_multiple_emails', _M('Mail addresses'));
		Utils_RecordBrowserCommon::set_icon('rc_multiple_emails', Base_ThemeCommon::get_template_filename(CRM_MailInstall::module_name(), 'icon.png'));
        Utils_RecordBrowserCommon::set_search('rc_multiple_emails',2,0);

		Utils_RecordBrowserCommon::new_addon('contact', CRM_MailInstall::module_name(), 'mail_addresses_addon', _M('E-mail addresses'));
		Utils_RecordBrowserCommon::new_addon('company', CRM_MailInstall::module_name(), 'mail_addresses_addon', _M('E-mail addresses'));

        Variable::set('crm_mail_global_signature',"Message sent with EPESI - managing business your way!<br /><a href=\"http://epe.si\">http://epe.si</a>");

		Utils_RecordBrowserCommon::add_access('rc_accounts', 'view', 'ACCESS:employee', array('epesi_user'=>'USER_ID'));
		Utils_RecordBrowserCommon::add_access('rc_accounts', 'add', 'ACCESS:employee');
		Utils_RecordBrowserCommon::add_access('rc_accounts', 'edit', 'ACCESS:employee', array(), array('epesi_user'));
		Utils_RecordBrowserCommon::add_access('rc_accounts', 'delete', 'ACCESS:employee', array('epesi_user'=>'USER_ID'));

		Utils_RecordBrowserCommon::add_access('rc_mails', 'view', 'ACCESS:employee', array(), array('headers_data'));
		Utils_RecordBrowserCommon::add_access('rc_mails', 'delete', 'ACCESS:employee');
        	Utils_RecordBrowserCommon::add_access('rc_mails', 'edit', 'ACCESS:employee',array(),array('subject','employee','date','headers_data','body','from','to','thread','message_id','references'));

        Utils_RecordBrowserCommon::add_access('rc_mail_threads', 'view', 'ACCESS:employee');
        Utils_RecordBrowserCommon::add_access('rc_mail_threads', 'delete', 'ACCESS:employee');

		Utils_RecordBrowserCommon::add_access('rc_multiple_emails', 'view', 'ACCESS:employee');
		Utils_RecordBrowserCommon::add_access('rc_multiple_emails', 'add', 'ACCESS:employee');
		Utils_RecordBrowserCommon::add_access('rc_multiple_emails', 'edit', 'ACCESS:employee');
		Utils_RecordBrowserCommon::add_access('rc_multiple_emails', 'delete', 'ACCESS:employee');

		Variable::set('crm_mail_default_client','');

		return true;
	}

	public function uninstall() {
		Utils_RecordBrowserCommon::delete_addon('rc_mails', CRM_MailInstall::module_name(), 'attachments_addon');
        Utils_RecordBrowserCommon::delete_addon('contact', CRM_MailInstall::module_name(), 'addon');
        Utils_RecordBrowserCommon::delete_addon('company', CRM_MailInstall::module_name(), 'addon');
        DB::DropTable('rc_mails_attachments');
        DB::DropTable('rc_mails_attachments_download');
        Utils_RecordBrowserCommon::uninstall_recordset('rc_mails');
        Utils_RecordBrowserCommon::uninstall_recordset('rc_accounts');
        Utils_RecordBrowserCommon::uninstall_recordset('rc_multiple_emails');
        Utils_CommonDataCommon::remove('CRM/Mail/Security');
		Utils_RecordBrowserCommon::unregister_processing_callback('rc_related', array('CRM_MailCommon', 'processing_related'));
		Utils_RecordBrowserCommon::unregister_processing_callback('rc_accounts', array('CRM_MailCommon', 'submit_account'));
		Utils_RecordBrowserCommon::unregister_processing_callback('rc_mails', array('CRM_MailCommon', 'submit_mail'));

		Variable::delete('crm_mail_default_client');

        Base_ThemeCommon::uninstall_default_theme($this -> get_type());
        Variable::delete('crm_mail_global_signature');
		return true;
	}

	public function version() {
		return array("0.1");
	}

	public function requires($v) {
		return array(
			array('name'=>Utils_RecordBrowserInstall::module_name(),'version'=>0),
			array('name'=>CRM_ContactsInstall::module_name(),'version'=>0),
			array('name'=>Utils_WatchdogInstall::module_name(),'version'=>0)
		);
	}

	public static function info() {
		return array(
			'Description'=>'Core mail support - accounts, archive applet.',
			'Author'=>'pbukowski@telaxus.com',
			'License'=>'MIT');
	}

	public static function simple_setup() {
        return 'CRM';
    }

}

?>
