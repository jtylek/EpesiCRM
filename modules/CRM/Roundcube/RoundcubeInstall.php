<?php
/**
 * Roundcube bindings
 * @author pbukowski@telaxus.com
 * @copyright Telaxus LLC
 * @license GPL
 * @version 0.1
 * @package epesi-CRM
 * @subpackage Roundcube
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_RoundcubeInstall extends ModuleInstall {

    public function install() {
        $this->create_data_dir();
        Base_ThemeCommon::install_default_theme($this -> get_type());

		@DB::DropSequence('rc_user_ids');
        @DB::DropTable('rc_users');
		@DB::DropSequence('rc_identity_ids');
        @DB::DropTable('rc_identities');
		@DB::DropSequence('rc_contact_ids');
        @DB::DropTable('rc_contacts');
		@DB::DropSequence('rc_contactgroups_ids');
        @DB::DropTable('rc_contactgroups');
        @DB::DropTable('rc_contactgroupmembers');
        @DB::DropTable('rc_session');
		@DB::DropSequence('rc_cache_ids');
        @DB::DropTable('rc_cache');
		@DB::DropSequence('rc_message_ids');
        @DB::DropTable('rc_messages');

        if(DATABASE_DRIVER=='mysqlt')
            $f = file_get_contents('modules/CRM/Roundcube/RC/SQL/mysql.initial.sql');
        else
            $f = file_get_contents('modules/CRM/Roundcube/RC/SQL/postgres.initial.sql');
        foreach(explode(';',$f) as $q) {
            $q = trim($q);
            if(!$q) continue;
            DB::Execute($q);
        }

        Utils_CommonDataCommon::new_array('CRM/Roundcube/Security', array('tls'=>_M('TLS'),'ssl'=>_M('SSL')),true,true);

        $fields = array(
            array('name' => _M('Epesi User'),             'type'=>'integer', 'extra'=>false, 'visible'=>true, 'required'=>true, 'display_callback'=>array('CRM_RoundcubeCommon', 'display_epesi_user'), 'QFfield_callback'=>array('CRM_RoundcubeCommon', 'QFfield_epesi_user')),
            array('name' => _M('Email'),             'type'=>'text', 'extra'=>false, 'visible'=>true, 'required'=>true, 'param'=>128),
        	array('name' => _M('Account Name'),             'type'=>'text', 'extra'=>false, 'visible'=>true, 'required'=>true, 'param'=>32,'QFfield_callback'=>array('CRM_RoundcubeCommon','QFfield_account_name')),
        	array('name' => _M('Server'),             'type'=>'text', 'extra'=>false, 'visible'=>true, 'param'=>'255', 'required'=>true),
            array('name' => _M('Login'),              'type'=>'text', 'required'=>true, 'param'=>'255', 'extra'=>false, 'visible'=>true),
            array('name' => _M('Password'),           'type'=>'text', 'required'=>true,'extra'=>false, 'param'=>'255', 'visible'=>false, 'QFfield_callback'=>array('CRM_RoundcubeCommon','QFfield_password'), 'display_callback'=>array('CRM_RoundcubeCommon','display_password')),
            array('name' => _M('Security'),           'type'=>'commondata', 'param'=>array('CRM/Roundcube/Security'), 'extra'=>false, 'visible'=>false, 'QFfield_callback'=>array('CRM_RoundcubeCommon','QFfield_security')),

            array('name' => _M('SMTP Server'),             'type'=>'text', 'extra'=>false, 'visible'=>false, 'param'=>'255', 'required'=>true),
            array('name' => _M('SMTP Auth'),             'type'=>'checkbox', 'extra'=>false, 'visible'=>false, 'QFfield_callback'=>array('CRM_RoundcubeCommon','QFfield_smtp_auth')),
            array('name' => _M('SMTP Login'),              'type'=>'text', 'required'=>false, 'param'=>'255', 'extra'=>false, 'visible'=>false, 'QFfield_callback'=>array('CRM_RoundcubeCommon','QFfield_smtp_login')),
            array('name' => _M('SMTP Password'),           'type'=>'text', 'extra'=>false, 'param'=>'255', 'visible'=>false, 'QFfield_callback'=>array('CRM_RoundcubeCommon','QFfield_smtp_password'), 'display_callback'=>array('CRM_RoundcubeCommon','display_password')),
            array('name' => _M('SMTP Security'),           'type'=>'commondata', 'param'=>array('CRM/Roundcube/Security'), 'extra'=>false, 'visible'=>false, 'QFfield_callback'=>array('CRM_RoundcubeCommon','QFfield_smtp_security')),

            array('name' => _M('Default Account'),             'type'=>'checkbox', 'extra'=>false, 'visible'=>true, 'QFfield_callback'=>array('CRM_RoundcubeCommon','QFfield_default_account')),

            array('name' => _M('Advanced'), 'type'=>'page_split'),
            array('name' => _M('Archive on sending'), 'type'=>'checkbox', 'extra'=>true, 'visible'=>false),
            array('name' => _M('Use EPESI Archive directories'), 'type'=>'checkbox', 'extra'=>true, 'visible'=>false),
            array('name' => _M('IMAP Root'), 'type'=>'text', 'param'=>32, 'extra'=>true, 'visible'=>false),
            array('name' => _M('IMAP Delimiter'), 'type'=>'text', 'param'=>8, 'extra'=>true, 'visible'=>false)
        );
        Utils_RecordBrowserCommon::install_new_recordset('rc_accounts', $fields);
        Utils_RecordBrowserCommon::set_caption('rc_accounts', _M('Mail accounts'));
	Utils_RecordBrowserCommon::register_processing_callback('rc_accounts', array('CRM_RoundcubeCommon', 'submit_account'));

        $fields = array(
            array(
                'name' => _M('Subject'),
                'type'=>'text',
                'param'=>'256',
                'extra'=>false,
                'visible'=>true,
                'required'=>false,
                'display_callback'=>array('CRM_RoundcubeCommon','display_subject')
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
                'display_callback'=>array('CRM_RoundcubeCommon','display_attachments'),
                'QFfield_callback'=>array('CRM_RoundcubeCommon','QFfield_attachments')
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
                'QFfield_callback'=>array('CRM_RoundcubeCommon','QFfield_body')
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
                'param'=>512,
                'extra'=>false,
                'visible'=>false,
                'required'=>false
            )
        );
        Utils_RecordBrowserCommon::install_new_recordset('rc_mails', $fields);
        Utils_RecordBrowserCommon::set_caption('rc_mails', _M('Mails'));
		Utils_RecordBrowserCommon::set_tpl('rc_mails', Base_ThemeCommon::get_template_filename('CRM/Roundcube', 'mails'));
	Utils_RecordBrowserCommon::register_processing_callback('rc_mails', array('CRM_RoundcubeCommon', 'submit_mail'));

        $fields = array(
            array(
                'name' => _M('Mail'),
                'type'=>'select',
                'param'=>'rc_mails::Subject' ,
                'required'=>true, 'extra'=>false, 'visible'=>false
            ), array(
                'name' => _M('Recordset'),
                'type'=>'text',
                'param'=>64,
                'required'=>true, 'extra'=>false, 'visible'=>false
            ), array(
                'name' => _M('Record ID'),
                'type'=>'integer',
                'display_callback'=>array($this->get_type().'Common', 'display_record_id'),
                'required'=>true, 'extra'=>false, 'visible'=>true, 'style'=>''
            )
        );
        Utils_RecordBrowserCommon::install_new_recordset('rc_mails_assoc', $fields);
        Utils_RecordBrowserCommon::set_caption('rc_mails_assoc', _M('Mails Associations'));
		Utils_RecordBrowserCommon::new_addon('rc_mails', 'CRM/Roundcube', 'mail_body_addon', _M('Body'));
        Utils_RecordBrowserCommon::new_addon('rc_mails', 'CRM/Roundcube', 'assoc_addon', _M('Associated records'));
        Utils_RecordBrowserCommon::new_addon('rc_mails', 'CRM/Roundcube', 'attachments_addon', _M('Attachments'));
		Utils_RecordBrowserCommon::new_addon('rc_mails', 'CRM/Roundcube', 'mail_headers_addon', _M('Headers'));

        @DB::DropTable('rc_mails_attachments');
        DB::CreateTable('rc_mails_attachments','
            mail_id I4 NOTNULL,
            type C(32),
            name C(255),
            mime_id C(32),
            attachment I1 DEFAULT 1',
            array('constraints'=>', FOREIGN KEY (mail_id) REFERENCES rc_mails_data_1(ID)'));

        Utils_RecordBrowserCommon::new_addon('contact', 'CRM/Roundcube', 'addon', _M('E-mails'));
        Utils_RecordBrowserCommon::new_addon('company', 'CRM/Roundcube', 'addon', _M('E-mails'));

		$fields = array(
			array('name' => _M('Record Type'), 	'type'=>'hidden', 'param'=>Utils_RecordBrowserCommon::actual_db_type('text',64), 'required'=>false, 'visible'=>false, 'filter'=>true, 'extra'=>false),
			array('name' => _M('Record ID'), 		'type'=>'hidden', 'param'=>Utils_RecordBrowserCommon::actual_db_type('integer'), 'filter'=>false, 'required'=>false, 'extra'=>false, 'visible'=>false),
			array('name' => _M('Nickname'), 		'type'=>'text', 'required'=>true, 'param'=>'64', 'extra'=>false, 'visible'=>true, 'QFfield_callback'=>array('CRM_RoundcubeCommon','QFfield_nickname')),
			array('name' => _M('Email'), 			'type'=>'email', 'required'=>true, 'param'=>array('unique'=>true), 'extra'=>false, 'visible'=>true)
		);

		Utils_RecordBrowserCommon::install_new_recordset('rc_multiple_emails', $fields);
		
		Utils_RecordBrowserCommon::set_favorites('rc_multiple_emails', true);
		Utils_RecordBrowserCommon::set_caption('rc_multiple_emails', _M('Mail addresses'));
		Utils_RecordBrowserCommon::set_icon('rc_multiple_emails', Base_ThemeCommon::get_template_filename('CRM/Roundube', 'icon.png'));

		Utils_RecordBrowserCommon::new_addon('contact', 'CRM/Roundcube', 'mail_addresses_addon', _M('E-mail addresses'));
		Utils_RecordBrowserCommon::new_addon('company', 'CRM/Roundcube', 'mail_addresses_addon', _M('E-mail addresses'));

        Variable::set('crm_roundcube_global_signature',"Message sent with EPESI - managing business your way!<br /><a href=\"http://epe.si\">http://epe.si</a>");

		Utils_RecordBrowserCommon::add_access('rc_accounts', 'view', 'ACCESS:employee', array('epesi_user'=>'USER_ID'));
		Utils_RecordBrowserCommon::add_access('rc_accounts', 'add', 'ACCESS:employee');
		Utils_RecordBrowserCommon::add_access('rc_accounts', 'edit', 'ACCESS:employee', array(), array('epesi_user'));
		Utils_RecordBrowserCommon::add_access('rc_accounts', 'delete', 'ACCESS:employee', array('epesi_user'=>'USER_ID'));

		Utils_RecordBrowserCommon::add_access('rc_mails', 'view', 'ACCESS:employee', array(), array('headers_data'));
		Utils_RecordBrowserCommon::add_access('rc_mails', 'delete', 'ACCESS:employee');

		Utils_RecordBrowserCommon::add_access('rc_mails_assoc', 'view', 'ACCESS:employee', array(), array('recordset'));
		Utils_RecordBrowserCommon::add_access('rc_mails_assoc', 'delete', 'ACCESS:employee');

		Utils_RecordBrowserCommon::add_access('rc_multiple_emails', 'view', 'ACCESS:employee');
		Utils_RecordBrowserCommon::add_access('rc_multiple_emails', 'add', 'ACCESS:employee');
		Utils_RecordBrowserCommon::add_access('rc_multiple_emails', 'edit', 'ACCESS:employee');
		Utils_RecordBrowserCommon::add_access('rc_multiple_emails', 'delete', 'ACCESS:employee');

        return true;
    }

    public function uninstall() {
		@DB::DropSequence('rc_user_ids');
        @DB::DropTable('rc_users');
		@DB::DropSequence('rc_identity_ids');
        @DB::DropTable('rc_identities');
		@DB::DropSequence('rc_contact_ids');
        @DB::DropTable('rc_contacts');
		@DB::DropSequence('rc_contactgroups_ids');
        @DB::DropTable('rc_contactgroups');
        @DB::DropTable('rc_contactgroupmembers');
        @DB::DropTable('rc_session');
		@DB::DropSequence('rc_cache_ids');
        @DB::DropTable('rc_cache');
		@DB::DropSequence('rc_message_ids');
        @DB::DropTable('rc_messages');

        Utils_RecordBrowserCommon::delete_addon('rc_mails', 'CRM/Roundcube', 'attachments_addon');
        Utils_RecordBrowserCommon::delete_addon('contact', 'CRM/Roundcube', 'addon');
        Utils_RecordBrowserCommon::delete_addon('company', 'CRM/Roundcube', 'addon');
        Utils_RecordBrowserCommon::delete_addon('rc_mails', 'CRM/Roundcube', 'assoc_addon');
        DB::DropTable('rc_mails_attachments');
        Utils_RecordBrowserCommon::uninstall_recordset('rc_mails_assoc');
        Utils_RecordBrowserCommon::uninstall_recordset('rc_mails');
        Utils_RecordBrowserCommon::uninstall_recordset('rc_accounts');
        Utils_RecordBrowserCommon::uninstall_recordset('rc_multiple_emails');
        Utils_CommonDataCommon::remove('CRM/Roundcube/Security');
		Utils_RecordBrowserCommon::unregister_processing_callback('rc_accounts', array('CRM_RoundcubeCommon', 'submit_account'));
		Utils_RecordBrowserCommon::unregister_processing_callback('rc_mails', array('CRM_RoundcubeCommon', 'submit_mail'));

        Base_ThemeCommon::uninstall_default_theme($this -> get_type());
        Variable::delete('crm_roundcube_global_signature');

        return true;
    }

    public function version() {
        return array("0.1");
    }

    public function requires($v) {
        return array(array('name'=>'Utils/RecordBrowser','version'=>0),
                    array('name'=>'CRM/Contacts','version'=>0),
                    array('name'=>'Utils/Watchdog','version'=>0)
                    );
    }

    public static function info() {
        return array(
            'Description'=>'Roundcube bindings',
            'Author'=>'pbukowski@telaxus.com',
            'License'=>'GPL');
    }

    public static function simple_setup() {
        return 'CRM';
    }

}

?>
