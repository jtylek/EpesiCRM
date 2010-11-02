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

        @DB::DropTable('rc_users');
        @DB::DropTable('rc_identities');
        @DB::DropTable('rc_contacts');
        @DB::DropTable('rc_contactgroups');
        @DB::DropTable('rc_contactgroupmembers');
        @DB::DropTable('rc_session');
        @DB::DropTable('rc_cache');
        @DB::DropTable('rc_messages');

        if(DATABASE_DRIVER=='mysqlt')
            $f = file_get_contents('modules/CRM/Roundcube/src/SQL/mysql.initial.sql');
        else
            $f = file_get_contents('modules/CRM/Roundcube/src/SQL/postgres.initial.sql');
        foreach(explode(';',$f) as $q) {
            $q = trim($q);
            if(!$q) continue;
            DB::Execute($q);
        }

        Utils_CommonDataCommon::new_array('CRM/Roundcube/Security', array('tls'=>'TLS','ssl'=>'SSL'),true,true);

        $fields = array(
            array('name'=>'Epesi User',             'type'=>'integer', 'extra'=>false, 'visible'=>true, 'required'=>true, 'display_callback'=>array('CRM_RoundcubeCommon', 'display_epesi_user'), 'QFfield_callback'=>array('CRM_RoundcubeCommon', 'QFfield_epesi_user')),
            array('name'=>'Email',             'type'=>'text', 'extra'=>false, 'visible'=>true, 'required'=>true, 'param'=>128),
            array('name'=>'Server',             'type'=>'text', 'extra'=>false, 'visible'=>true, 'param'=>'255', 'required'=>true),
            array('name'=>'Login',              'type'=>'text', 'required'=>true, 'param'=>'255', 'extra'=>false, 'visible'=>true),
            array('name'=>'Password',           'type'=>'text', 'required'=>true,'extra'=>false, 'param'=>'255', 'visible'=>false, 'QFfield_callback'=>array('CRM_RoundcubeCommon','QFfield_password')),
            array('name'=>'Security',           'type'=>'commondata', 'param'=>array('CRM/Roundcube/Security'), 'extra'=>false, 'visible'=>false, 'QFfield_callback'=>array('CRM_RoundcubeCommon','QFfield_security')),

            array('name'=>'SMTP Server',             'type'=>'text', 'extra'=>false, 'visible'=>false, 'param'=>'255', 'required'=>true),
            array('name'=>'SMTP Auth',             'type'=>'checkbox', 'extra'=>false, 'visible'=>false, 'QFfield_callback'=>array('CRM_RoundcubeCommon','QFfield_smtp_auth')),
            array('name'=>'SMTP Login',              'type'=>'text', 'required'=>false, 'param'=>'255', 'extra'=>false, 'visible'=>false, 'QFfield_callback'=>array('CRM_RoundcubeCommon','QFfield_smtp_login')),
            array('name'=>'SMTP Password',           'type'=>'text', 'extra'=>false, 'param'=>'255', 'visible'=>false, 'QFfield_callback'=>array('CRM_RoundcubeCommon','QFfield_smtp_password')),
            array('name'=>'SMTP Security',           'type'=>'commondata', 'param'=>array('CRM/Roundcube/Security'), 'extra'=>false, 'visible'=>false, 'QFfield_callback'=>array('CRM_RoundcubeCommon','QFfield_smtp_security')),

            array('name'=>'Default Account',             'type'=>'checkbox', 'extra'=>false, 'visible'=>true, 'QFfield_callback'=>array('CRM_RoundcubeCommon','QFfield_default_account')),

            array('name'=>'Advanced', 'type'=>'page_split'),
            array('name'=>'IMAP Root', 'type'=>'text', 'param'=>32, 'extra'=>true, 'visible'=>false),
            array('name'=>'IMAP Delimiter', 'type'=>'text', 'param'=>8, 'extra'=>true, 'visible'=>false)
        );
        Utils_RecordBrowserCommon::install_new_recordset('rc_accounts', $fields);
        Utils_RecordBrowserCommon::set_caption('rc_accounts', 'Mail accounts');
		Utils_RecordBrowserCommon::register_processing_callback('rc_accounts', array('CRM_RoundcubeCommon', 'submit_account'));

        $fields = array(
            array(
                'name'=>'Subject',
                'type'=>'text',
                'param'=>'256',
                'extra'=>false,
                'visible'=>true,
                'required'=>false,
                'display_callback'=>array('CRM_RoundcubeCommon','display_subject')
            ),
            array(
                'name'=>'Contacts',
                'type'=>'crm_company_contact',
                'param'=>array('field_type'=>'multiselect'),
                'required'=>false,
                'extra'=>false,
                'visible'=>true
            ),
            array(
                'name'=>'Direction',
                'type'=>'text',
                'param'=>1,
                'extra'=>false,
                'visible'=>true,
                'required'=>true,
                'QFfield_callback'=>array('CRM_RoundcubeCommon','QFfield_direction'),
                'display_callback'=>array('CRM_RoundcubeCommon','display_direction')
            ),
            array(
                'name'=>'Employee',
                'type'=>'crm_contact',
                'param'=>array('field_type'=>'select'),
                'extra'=>false,
                'visible'=>true,
                'required'=>false
            ),
            array(
                'name'=>'Date',
                'type'=>'timestamp',
                'extra'=>false,
                'visible'=>true,
                'required'=>false
            ),
            array(
                'name'=>'Attachments',
                'type'=>'calculated',
                'extra'=>false,
                'visible'=>true,
                'display_callback'=>array('CRM_RoundcubeCommon','display_attachments'),
                'QFfield_callback'=>array('CRM_RoundcubeCommon','QFfield_attachments')
            ),
            array(
                'name'=>'Headers Data',
                'type'=>'long text',
                'extra'=>false,
                'visible'=>false,
                'required'=>false
            ),
            array(
                'name'=>'Headers',
                'type'=>'calculated',
                'extra'=>false,
                'visible'=>false,
                'required'=>false,
                'QFfield_callback'=>array('CRM_RoundcubeCommon','QFfield_headers')
            ),
            array(
                'name'=>'Body',
                'type'=>'long text',
                'extra'=>false,
                'visible'=>false,
                'required'=>false,
                'QFfield_callback'=>array('CRM_RoundcubeCommon','QFfield_body')
            )
        );
        Utils_RecordBrowserCommon::install_new_recordset('rc_mails', $fields);
        Utils_RecordBrowserCommon::set_caption('rc_mails', 'Mails');
        Utils_RecordBrowserCommon::set_access_callback('rc_mails', array('CRM_RoundcubeCommon', 'access_mails'));

        $fields = array(
            array(
                'name'=>'Mail',
                'type'=>'select',
                'param'=>'rc_mails::Subject' ,
                'required'=>true, 'extra'=>false, 'visible'=>false
            ), array(
                'name'=>'Recordset',
                'type'=>'text',
                'param'=>64,
                'required'=>true, 'extra'=>false, 'visible'=>false
            ), array(
                'name'=>'Record ID',
                'type'=>'integer',
                'display_callback'=>array($this->get_type().'Common', 'display_record_id'),
                'required'=>true, 'extra'=>false, 'visible'=>true
            )
        );
        Utils_RecordBrowserCommon::install_new_recordset('rc_mails_assoc', $fields);
        Utils_RecordBrowserCommon::set_caption('rc_mails_assoc', 'Mails Associations');
        Utils_RecordBrowserCommon::set_access_callback('rc_mails_assoc', array('CRM_RoundcubeCommon', 'access_mails_assoc'));
        Utils_RecordBrowserCommon::new_addon('rc_mails', 'CRM/Roundcube', 'assoc_addon', 'Associated records');

        DB::CreateTable('rc_mails_attachments','
            mail_id I4 NOTNULL,
            type C(32),
            name C(255),
            mime_id C(32),
            attachment I1 DEFAULT 1',
            array('constraints'=>', FOREIGN KEY (mail_id) REFERENCES rc_mails_data_1(ID)'));

        Utils_RecordBrowserCommon::new_addon('contact', 'CRM/Roundcube', 'addon', 'Mails');
        Utils_RecordBrowserCommon::new_addon('company', 'CRM/Roundcube', 'addon', 'Mails');

		$fields = array(
			array('name'=>'Record Type', 		'type'=>'text', 'param'=>'64', 'required'=>false, 'visible'=>false, 'filter'=>true, 'extra'=>false),
			array('name'=>'Record ID', 		'type'=>'integer', 'filter'=>false, 'required'=>false, 'extra'=>false, 'visible'=>false),
			array('name'=>'Nickname', 		'type'=>'text', 'required'=>true, 'param'=>'64', 'extra'=>false, 'visible'=>true, 'QFfield_callback'=>array('CRM_RoundcubeCommon','QFfield_nickname')),
			array('name'=>'Email', 			'type'=>'text', 'required'=>true, 'param'=>'128', 'extra'=>false, 'visible'=>true, 'display_callback'=>array('CRM_ContactsCommon', 'display_email'), 'QFfield_callback'=>array('CRM_ContactsCommon', 'QFfield_email'))
		);

		Utils_RecordBrowserCommon::install_new_recordset('rc_multiple_emails', $fields);
		
		Utils_RecordBrowserCommon::set_favorites('rc_multiple_emails', true);
		Utils_RecordBrowserCommon::set_caption('rc_multiple_emails', 'Mail addresses');
		Utils_RecordBrowserCommon::set_icon('rc_multiple_emails', Base_ThemeCommon::get_template_filename('CRM/Roundube', 'icon.png'));
		Utils_RecordBrowserCommon::set_access_callback('rc_multiple_emails', array('CRM_RoundcubeCommon', 'access_mail_addresses'));

		Utils_RecordBrowserCommon::new_addon('contact', 'CRM/Roundcube', 'mail_addresses_addon', 'Mail addresses');
		Utils_RecordBrowserCommon::new_addon('company', 'CRM/Roundcube', 'mail_addresses_addon', 'Mail addresses');

        return true;
    }

    public function uninstall() {
        DB::DropTable('rc_users');
        DB::DropTable('rc_identities');
        DB::DropTable('rc_contacts');
        DB::DropTable('rc_contactgroups');
        DB::DropTable('rc_contactgroupmembers');
        DB::DropTable('rc_session');
        DB::DropTable('rc_cache');
        DB::DropTable('rc_messages');

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

        Base_ThemeCommon::uninstall_default_theme($this -> get_type());

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
        return true;
    }

}

?>
