<?php

/**
 * 
 * @author pbukowski@telaxus.com
 * @copyright Telaxus LLC
 * @license MIT
 * @version 0.1
 * @package epesi-Base
 * @subpackage AppStore
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_AppStoreCommon extends Base_AdminModuleCommon {
    const VAR_LICENSE_KEY = 'license_key';

    public static function get_license_key() {
        return Variable::get(self::VAR_LICENSE_KEY);
    }

    public static function set_license_key($license_key) {
        return Variable::set(self::VAR_LICENSE_KEY, $license_key);
    }

    /** @var IClient */
    protected static $client_requester = null;

    /**
     * Get server connection object to perform requests
     * @return IClient server requester
     */
    public static function server() {
        if(self::$client_requester == null) {
            require_once('modules/Base/AppStore/ClientRequester.php');
            self::$client_requester = new ClientRequester();
        }
        return self::$client_requester;
    }

    public static function admin_caption() {
        return "AppStore";
    }

}

?>