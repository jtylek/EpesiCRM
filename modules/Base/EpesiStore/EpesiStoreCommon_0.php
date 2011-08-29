<?php

/**
 * 
 * @author pbukowski@telaxus.com
 * @copyright Telaxus LLC
 * @license MIT
 * @version 0.1
 * @package epesi-Base
 * @subpackage EpesiStore
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_EpesiStoreCommon extends Base_AdminModuleCommon {
    const MOD_PATH = 'Base_EpesiStoreCommon';
    const CART_VAR = 'cart';
    const DOWNLOAD_QUEUE_VAR = 'queue';

    public static function admin_caption() {
        if (Base_AclCommon::i_am_sa())
            return "Epesi Store";
        return null;
    }

    public static function get_cart() {
        return Module::static_get_module_variable(self::MOD_PATH, self::CART_VAR, array());
    }

    public static function set_cart($cart) {
        return Module::static_set_module_variable(self::MOD_PATH, self::CART_VAR, $cart);
    }

    public static function empty_cart() {
        return Module::static_set_module_variable(self::MOD_PATH, self::CART_VAR, array());
    }

    public static function get_download_queue() {
        return Module::static_get_module_variable(self::MOD_PATH, self::DOWNLOAD_QUEUE_VAR, array());
    }

    public static function set_download_queue($queue) {
        return Module::static_set_module_variable(self::MOD_PATH, self::DOWNLOAD_QUEUE_VAR, $queue);
    }

    public static function empty_download_queue() {
        return Module::static_set_module_variable(self::MOD_PATH, self::DOWNLOAD_QUEUE_VAR, array());
    }

    public static function module_format_info($r) {
        $x = array();
        $x[] = "<big><strong>{$r['name']}</strong></big>";
        if ($r['description'])
            $x[] = "<b>Description:</b><br/>{$r['description']}";
        $x[] = "<b>Repository:</b> {$r['repository']}";
        $x[] = "<b>Files:</b><br/>{$r['path']}";
        $x[] = "<b>Price:</b> {$r['price']}";
        $x[] = "<b>Version:</b> {$r['version']}";
        $x[] = "<b>Active:</b> {$r['active']}";

        return implode('<br/>', $x);
    }

    public static function modules_total_amount() {
        $total = Module::static_get_module_variable(self::MOD_PATH, 'modules_total_amount');
        if ($total === null) {
            $total = Base_EssClientCommon::server()->modules_list_total_amount();
            Module::static_set_module_variable(self::MOD_PATH, 'modules_total_amount', $total);
        }
        return $total;
    }

    public static function modules_list($offset, $amount) {
        $modules = Module::static_get_module_variable(self::MOD_PATH, 'modules_list', array());
        $start = $offset;
        $end = $offset + $amount - 1;
        while (isset($modules[$start]))
            $start++;
        while (isset($modules[$end]))
            $end--;
        $modules_from_serv = $end >= $start ? Base_EssClientCommon::server()->modules_list($start, $end - $start + 1) : array();
        $i = $start;
        foreach ($modules_from_serv as $m) {
            $modules[$i++] = $m;
        }
        Module::static_set_module_variable(self::MOD_PATH, 'modules_list', $modules);
        return array_slice($modules, $offset, $amount);
    }

    public static function get_module_info($module_id, $force = false) {
        $modules_cache = Module::static_get_module_variable(self::MOD_PATH, 'modules_info', array());
        if ($force == false && isset($modules_cache[$module_id]))
            return $modules_cache[$module_id];
        // if not - request server
        $modules_cache[$module_id] = Base_EssClientCommon::server()->module_get_info($module_id);
        Module::static_set_module_variable(self::MOD_PATH, 'modules_info', $modules_cache);
        // update in module list
        $modules_list = Module::static_get_module_variable(self::MOD_PATH, 'modules_list', array());
        foreach($modules_list as $k => $v) {
            if($v['id'] == $module_id) {
                $modules_list[$k] = $modules_cache[$module_id];
                Module::static_set_module_variable(self::MOD_PATH, 'modules_list', $modules_list);
                break;
            }
        }
        return $modules_cache[$module_id];
    }

    public static function get_downloaded_modules() {
        return DB::GetAll('SELECT * FROM epesi_store_modules');
    }

    public static function add_downloaded_module($module_id, $version, $order_id) {
        DB::Execute('INSERT INTO epesi_store_modules(module_id, version, order_id) VALUES (%d, %d, %d)', array($module_id, $version, $order_id));
    }

}

?>