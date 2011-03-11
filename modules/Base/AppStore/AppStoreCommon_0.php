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
    public static function get_license_key() {
    	return Variable::get("license_key");
    }

    public static function admin_caption() {
        return "AppStore";
    }
}

?>