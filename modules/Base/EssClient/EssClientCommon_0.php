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
    const SERVER_ADDRESS = 'http://ess.epesibim.com/';
    const VAR_LICENSE_KEY = 'license_key';

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
        return "Epesi Services Server";
    }

}

?>