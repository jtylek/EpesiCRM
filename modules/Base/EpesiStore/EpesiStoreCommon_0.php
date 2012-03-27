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

    const ACTION_BUY = 'buy';
    const ACTION_PAY = 'pay';
    const ACTION_DOWNLOAD = 'download';
    const ACTION_UPDATE = 'update';
    const ACTION_INSTALL = 'install';
    //
    const MOD_PATH = 'Base_EpesiStoreCommon';
    const CART_VAR = 'cart';
    const DOWNLOAD_QUEUE_VAR = 'queue';

    public static function admin_access() {
        return Base_AclCommon::i_am_sa();
    }

    public static function admin_caption() {
        return "Modules Administration & Store";
    }

    public static function get_modules_all_available() {
        return Base_EssClientCommon::server()->modules_list_all();
    }

    private static function get_payment_data_keys() {
        // key = field name from contact => value = field name in settings
        return array(
            'first_name' => 'first_name',
            'last_name' => 'last_name',
            'address_1' => 'address_1',
            'address_2' => 'address_2',
            'city' => 'city',
            'country' => 'country',
            'postal_code' => 'postal_code',
            'email' => 'email',
            'mobile_phone' => 'phone');
    }

    public static function get_payment_credentials() {
        $keys = self::get_payment_data_keys();
        $ret = array();
        foreach ($keys as $k) {
            $ret[$k] = Base_User_SettingsCommon::get('Base_EpesiStore', $k);
        }
        return $ret;
    }

    public static function user_settings() {
        // get default data from user contact
        if (ModuleManager::is_installed('CRM_Contacts') > -1)
            $r = CRM_ContactsCommon::get_my_record();
        else
            $r = array();
        // key = field name from contact => value = field name in settings
        $keys = self::get_payment_data_keys();
        $values = array();
        // do user setting entries from data
        foreach ($keys as $k => $v) {
            $x = array('name' => $v, 'label' => ucwords(str_replace('_', ' ', $v)), 'type' => 'text', 'default' => isset($r[$k]) ? $r[$k] : '');
            if ($k == 'country') {
                $x['type'] = 'select';
                $x['values'] = Utils_CommonDataCommon::get_array('Countries');
            }
            $values[] = $x;
        }
        return array('Epesi Store' =>
            array_merge(
                    array(array('name' => 'payments_header', 'label' => '', 'type' => 'header', 'default' => Base_LangCommon::ts('Base_EpesiStore', 'Payment credentials')))
                    , $values));
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

    /**
     * Format info table about module.
     * Used with Ajax request from EpesiStore_0.php file
     * @param array $r modules data
     * @return string html with table
     */
    public static function module_format_info($r) {
        if (isset($r['id']))
            unset($r['id']);
        if (isset($r['active']))
            $r['active'] = Base_LangCommon::ts('Base_EpesiStore', $r['active'] ? 'Yes' : 'No');
        $r['files'] = implode("<br/>", $r['files']);
        unset($r['icon_url']);
        unset($r['description_url']);
        return Utils_TooltipCommon::format_info_tooltip($r, "Base_EpesiStore");
    }

    /**
     * Get total number of available modules.
     * @return int number of modules
     */
    public static function modules_total_amount() {
        $total = Module::static_get_module_variable(self::MOD_PATH, 'modules_total_amount');
        if ($total === null) {
            $total = Base_EssClientCommon::server()->modules_list_total_amount();
            Module::static_set_module_variable(self::MOD_PATH, 'modules_total_amount', $total);
        }
        return $total;
    }

    /**
     * Cached modules listing in some range
     * @param int $offset starting module
     * @param int $amount number of items
     * @return array modules data
     */
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

    /**
     * Get module info from cache or server.
     * @param numeric $module_id module id
     * @param boolean $force set true to force query to server
     * @return array modules data array
     */
    public static function get_module_info($module_id, $force = false) {
        $modules_cache = Module::static_get_module_variable(self::MOD_PATH, 'modules_info', array());
        if ($force == false && isset($modules_cache[$module_id]))
            return $modules_cache[$module_id];
        // if not - request server
        $modules_cache[$module_id] = Base_EssClientCommon::server()->module_get_info($module_id);
        Module::static_set_module_variable(self::MOD_PATH, 'modules_info', $modules_cache);
        // update in module list
        $modules_list = Module::static_get_module_variable(self::MOD_PATH, 'modules_list', array());
        foreach ($modules_list as $k => $v) {
            if ($v['id'] == $module_id) {
                $modules_list[$k] = $modules_cache[$module_id];
                Module::static_set_module_variable(self::MOD_PATH, 'modules_list', $modules_list);
                break;
            }
        }
        return $modules_cache[$module_id];
    }

    /**
     * Download module to epesi installation.
     * @param array $module_license module license data
     * @return mixed string with error message or true on success
     */
    public static function download_module($module_license) {
        try {
            $file = self::download_module_file($module_license);
            self::extract_module_file($file);
            self::store_info_about_downloaded_module($module_license, $file);
        } catch (Exception $e) {
            return $e->getMessage();
        }
        return true;
    }

    private static function download_module_file($module_license) {
        $hash_or_url = Base_EssClientCommon::server()->download_prepare($module_license['id']);
        if (!$hash_or_url)
            throw new ErrorException("Prepare error");
        $file_contents = Base_EssClientCommon::server()->download_prepared_file($hash_or_url);
        // check hash if it wasn't external package
        if (!self::is_url($hash_or_url) && sha1($file_contents) !== $hash_or_url)
            throw new ErrorException('File hash error');
        // store file
        $destfile = self::make_temp_filename();
        if (file_put_contents($destfile, $file_contents) === false)
            throw new ErrorException("File store error ($destfile)");
        return basename($destfile);
    }

    private static function is_url($string) {
        return false !== strpos($string, "://");
    }

    private static function make_temp_filename() {
        $destfile = self::Instance()->get_data_dir() . time();
        $i = 0;
        while (file_exists("{$destfile}{$i}.zip"))
            $i++;
        $destfile .= "{$i}.zip";
        return $destfile;
    }

    private static function extract_module_file($file) {
        $destfile = self::Instance()->get_data_dir() . $file;
        if (!file_exists($destfile))
            throw new ErrorException("file $destfile not exists");
        // extract
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if (filesize($destfile) == 0 || $zip->open($destfile) !== true || $zip->extractTo('./') == false) {
                throw new ErrorException('Archive error');
            } else {
                $zip->close();
            }
        } else {
            throw new ErrorException('Please enable zip extension in server configuration!');
        }
    }

    private static function store_info_about_downloaded_module($module_license, $file) {
        $module_info = self::get_module_info($module_license['module']);
        self::add_downloaded_module($module_info['id'], $module_info['version'], $module_license['id'], $file);
    }

    private static function _active_module_license_for_module($module_id) {
        static $module_licenses = null;
        if ($module_licenses === null)
            $module_licenses = Base_EssClientCommon::server()->module_licenses_list();
        foreach ($module_licenses as $m) {
            if ($m['module'] == $module_id && $m['active'] == true) {
                return $m;
            }
        }
        return false;
    }

    private static function _is_module_free($module_id) {
        $mi = self::get_module_info($module_id);
        return strtolower($mi['price']) == 'free';
    }

    private static function _is_module_license_active($module_id) {
        return false !== self::_active_module_license_for_module($module_id);
    }

    private static function _is_module_paid($module_id) {
        $ml = self::_active_module_license_for_module($module_id);
        if ($ml === false || $ml['active'] == false)
            return false;
        return $ml['paid'];
    }

    private static function _is_module_downloaded($module_id) {
        return false !== self::get_downloaded_module_version($module_id);
    }

    private static function _is_module_up_to_date($module_id) {
        $mi = self::get_module_info($module_id);
        return $mi['version'] <= self::get_downloaded_module_version($module_id);
    }

    public static function next_possible_action($module_id) {
        if (!self::_is_module_license_active($module_id))
            return self::ACTION_BUY;
        if (!self::_is_module_paid($module_id))
            return self::ACTION_PAY;
        if (!self::_is_module_downloaded($module_id))
            return self::ACTION_DOWNLOAD;
        if (!self::_is_module_up_to_date($module_id))
            return self::ACTION_UPDATE;

        return self::ACTION_INSTALL;
    }

    public static function next_possible_action_href($module_id, $response_callback = null) {
        $action = self::next_possible_action($module_id);
        return self::action_href($module_id, $action, $response_callback);
    }

    public static function action_href($module_id, $action, $response_callback = null) {
        return Base_BoxCommon::main_module_instance()->create_callback_href(array('Base_EpesiStoreCommon', 'handle_module_action'), array($module_id, $action, $response_callback));
    }

    public static function handle_module_action($module_id, $action, $response_callback = null) {
        if(Base_BoxCommon::main_module_instance()->is_back())
            return false;
        $return = null;
        switch ($action) {
            case self::ACTION_BUY:
                $response = Base_EssClientCommon::server()->order_submit($module_id);
                $return = $response[$module_id];
                if($return !== true)
                    break;
            case self::ACTION_PAY:
                $return = self::_display_payments_for_module($module_id);
                if ($return === true) {
                    Base_ActionBarCommon::add('back', 'Back', Base_BoxCommon::main_module_instance()->create_back_href());
                    return true;
                }
                break;
            case self::ACTION_DOWNLOAD:
            case self::ACTION_UPDATE:
                $mi = self::_active_module_license_for_module($module_id);
                if ($mi !== false)
                    $return = self::download_module($mi);
                else
                    $return = false;
                break;
            case self::ACTION_INSTALL:
                break;
        }
        if (is_callable($response_callback)) {
            call_user_func($response_callback, $action, $return);
            return;
        }
        return $return;
    }

    private static function _display_payments_for_module($module_id) {
        $module_license = self::_active_module_license_for_module($module_id);
        if ($module_license === false)
            return false;
        $module_license_id = $module_license['id'];
        $orders = Base_EssClientCommon::server()->orders_list();
        foreach ($orders as $o) {
            if (false === array_search($module_license_id, $o['modules']))
                continue;
            // order should contain only one 'currency => value' pair. Take first
            $keys = array_keys($o['price']);
            $currency = reset($keys);
            $value = $o['price'][$currency]['to_pay'];
            $mi = self::get_module_info($module_id);
            $main_module = Base_BoxCommon::main_module_instance();
            $store = $main_module->init_module('Base/EpesiStore');
            $main_module->display_module($store, array($o['id'], $value, $currency, $mi['name']), 'form_payment_frame');
            return true;
        }
        return "No orders with such module to perform payment";
    }

    private static function get_downloaded_module_version($module_id) {
        return DB::GetOne('SELECT `version` FROM epesi_store_modules WHERE `module_id` = %d', array($module_id));
    }

    /**
     * Get downloaded modules list.
     * Array keys are 'module_id', 'version', 'file' and 'module_license_id' as bought module id
     * @return array of data. 
     */
    public static function get_downloaded_modules() {
        $records = DB::GetAll('SELECT * FROM epesi_store_modules');
        // TODO: remove this column name substitution
        foreach ($records as & $r) {
            if (isset($r['order_id'])) {
                $r['module_license_id'] = $r['order_id'];
                unset($r['order_id']);
            }
        }
        return $records;
    }

    private static function add_downloaded_module($module_id, $version, $module_license_id, $file) {
        // TODO: change column name in db
        DB::Execute('REPLACE INTO epesi_store_modules(module_id, version, order_id, file) VALUES (%d, %s, %d, %s)', array($module_id, $version, $module_license_id, $file));
    }

}

?>