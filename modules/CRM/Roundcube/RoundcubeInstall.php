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
            array('name'=>'Server',             'type'=>'text', 'extra'=>false, 'visible'=>true, 'param'=>'255', 'required'=>true),
            array('name'=>'Login',              'type'=>'text', 'required'=>true, 'param'=>'255', 'extra'=>false, 'visible'=>true),
            array('name'=>'Password',           'type'=>'text', 'required'=>true,'extra'=>false, 'param'=>'255', 'visible'=>false, 'QFfield_callback'=>array('CRM_RoundcubeCommon','QFfield_password')),
            array('name'=>'Security',           'type'=>'commondata', 'param'=>array('CRM/Roundcube/Security'), 'extra'=>false, 'visible'=>false),

            array('name'=>'SMTP Server',             'type'=>'text', 'extra'=>false, 'visible'=>false, 'param'=>'255', 'required'=>true),
            array('name'=>'SMTP Auth',             'type'=>'checkbox', 'extra'=>false, 'visible'=>false, 'QFfield_callback'=>array('CRM_RoundcubeCommon','QFfield_smtp_auth')),
            array('name'=>'SMTP Login',              'type'=>'text', 'required'=>false, 'param'=>'255', 'extra'=>false, 'visible'=>false, 'QFfield_callback'=>array('CRM_RoundcubeCommon','QFfield_smtp_login')),
            array('name'=>'SMTP Password',           'type'=>'text', 'extra'=>false, 'param'=>'255', 'visible'=>false, 'QFfield_callback'=>array('CRM_RoundcubeCommon','QFfield_smtp_password')),
            array('name'=>'SMTP Security',           'type'=>'commondata', 'param'=>array('CRM/Roundcube/Security'), 'extra'=>false, 'visible'=>false, 'QFfield_callback'=>array('CRM_RoundcubeCommon','QFfield_smtp_security')),

            array('name'=>'Default Account',             'type'=>'checkbox', 'extra'=>false, 'visible'=>true, 'QFfield_callback'=>array('CRM_RoundcubeCommon','QFfield_default_account'))
        );
        Utils_RecordBrowserCommon::install_new_recordset('rc_accounts', $fields);
        Utils_RecordBrowserCommon::set_caption('rc_accounts', 'Mail accounts');
        Utils_RecordBrowserCommon::set_processing_callback('rc_accounts', array('CRM_RoundcubeCommon', 'submit_account'));


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

        Utils_RecordBrowserCommon::uninstall_recordset('cades_immunizations');
        Utils_CommonDataCommon::remove('CRM/Roundcube/Security');

        return true;
    }

    public function version() {
        return array("0.1");
    }

    public function requires($v) {
        return array(array('name'=>'Utils/RecordBrowser','version'=>0));
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
