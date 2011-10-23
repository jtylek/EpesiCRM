<?php

/**
 * 
 * @author abukowski@telaxus.com
 * @copyright Telaxus LLC
 * @license MIT
 * @version 0.1
 * @package epesi-Base
 * @subpackage EssClient
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_EssClientCommon extends Base_AdminModuleCommon {
//    const SERVER_ADDRESS = 'http://localhost/epesi/modules/Custom/ESS/serv/';
//    const SERVER_ADDRESS = 'http://localhost/epesi/tools/EpesiServiceServer/';
    const SERVER_ADDRESS = 'http://ess.epesibim.com/';
    const VAR_LICENSE_KEY = 'license_key';

    public static function menu() {
        if (!Base_AclCommon::i_am_sa())
            return;
        $text = 'Epesi registration';
        if (!self::get_license_key()) {
            $text = 'Register Epesi!';
        }
        return array('Help' => array('__submenu__' => 1, $text => array()));
    }

    /**
     * Get first(by id) user that is super administrator and get it's first
     * and last name from crm_contacts
     *
     * This function uses DB query to get users and generally it should be
     * easier way to get super admin.
     *
     * @return array with keys admin_email, admin_first_name, admin_last_name
     */
    public static function get_possible_admin() {
        $users = DB::GetAll('select id, mail from user_login inner join user_password on user_login_id = id');
        $arr = array();
        foreach ($users as $u) {
            if (Base_AclCommon::is_user_in_group(
                            Base_AclCommon::get_acl_user_id($u['id']), 'Super administrator')) {
                $x = array('admin_email' => $u['mail']);
                $contact = CRM_ContactsCommon::get_contact_by_user_id($u['id']);
                if ($contact) {
                    $x['admin_first_name'] = $contact['first_name'];
                    $x['admin_last_name'] = $contact['last_name'];
                }
                return $x;
            }
        }
        return null;
    }

    public static function get_license_key() {
        return Variable::get(self::VAR_LICENSE_KEY, false);
    }

    public static function set_license_key($license_key) {
        return Variable::set(self::VAR_LICENSE_KEY, $license_key);
    }

    /** @var IClient */
    protected static $client_requester = null;

    /**
     * Get server connection object to perform requests
     * @param boolean $recreate_object force to recreate object
     * @return IClient server requester
     */
    public static function server($recreate_object = false) {
        if (self::$client_requester == null || $recreate_object == true) {
            // include php file
            $dir = self::Instance()->get_module_dir();
            require_once $dir . 'ClientRequester.php';
            // create object
            self::$client_requester = new ClientRequester(self::SERVER_ADDRESS);
            self::$client_requester->set_client_license_key(self::get_license_key());
        }
        return self::$client_requester;
    }

    public static function admin_caption() {
        if (Base_AclCommon::i_am_sa())
            return "Epesi Registration";
        return null;
    }
	
	public static function get_support_email() {
		$email = 'bugs@telaxus.com'; // FIXME
		if(ModuleManager::is_installed('CRM_Roundcube')>=0) {
			$email = CRM_RoundcubeCommon::get_mailto_link($email);
		} else {
			$email = '<a href="mailto:'.$email.'">'.$email.'</a>';
		}
		return $email;
	}

}

?>