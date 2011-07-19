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
    const CART_PATH = 'Base_AppStoreCommon';
    const CART_VAR = 'cart';

    public static function admin_caption() {
        return "AppStore";
    }

    public static function get_cart() {
        return Module::static_get_module_variable(self::CART_PATH, self::CART_VAR, array());
    }

    public static function set_cart($cart) {
        return Module::static_set_module_variable(self::CART_PATH, self::CART_VAR, $cart);
    }

    public static function empty_cart() {
        return Module::static_set_module_variable(self::CART_PATH, self::CART_VAR, array());
    }
}

?>