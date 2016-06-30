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
        // create htaccess to prevent logs to be available on the internet
        $htaccess = $this->get_data_dir() . '.htaccess';
        $f = fopen($htaccess, 'w');
        if ($f === false) {
            print("Cannot create .htaccess file ($htaccess). "
                    . "Your Roundcube logs may be available on the internet!");
        } else {
            fwrite($f, "deny from all\n");
            fclose($f);
        }

        $this->drop_all_rc_tables();

        if(DB::is_mysql())
            $f = file_get_contents('modules/CRM/Roundcube/RC/SQL/mysql.initial.sql');
        else
            $f = file_get_contents('modules/CRM/Roundcube/RC/SQL/postgres.initial.sql');
        foreach(explode(';',$f) as $q) {
            $q = trim($q);
            if(!$q) continue;
            DB::Execute($q);
        }

        Variable::set('crm_mail_default_client','CRM_Roundcube');

        return true;
    }

    public function uninstall() {
        $this->drop_all_rc_tables();



        return true;
    }

    private function drop_all_rc_tables() {
        @DB::DropSequence('rc_search_ids');
        @DB::DropTable('rc_searches');
        @DB::DropTable('rc_dictionary');
        @DB::DropSequence('rc_cache_ids');
        @DB::DropTable('rc_cache');
        @DB::DropTable('rc_cache_index');
        @DB::DropTable('rc_cache_messages');
        @DB::DropTable('rc_cache_thread');
        @DB::DropSequence('rc_identity_ids');
        @DB::DropTable('rc_identities');
        @DB::DropTable('rc_contactgroupmembers');
        @DB::DropSequence('rc_contactgroups_ids');
        @DB::DropTable('rc_contactgroups');
        @DB::DropSequence('rc_contact_ids');
        @DB::DropTable('rc_contacts');
        @DB::DropTable('rc_session');
        @DB::DropSequence('rc_user_ids');
        @DB::DropTable('rc_users');
        return true;
    }

    public function version() {
        return array("0.1");
    }

    public function requires($v) {
        return array(array('name'=>CRM_MailInstall::module_name(),'version'=>0));
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
