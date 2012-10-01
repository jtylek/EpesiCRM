<?php

/**
 * 
 * @author Adam Bukowski <abukowski@telaxus.com>
 * @copyright Telaxus LLC
 * @license MIT
 * @version 0.1
 * @package epesi-Base
 * @subpackage EpesiStore
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_EpesiStoreCommon extends Base_AdminModuleCommon {

    const ACTION_BUY = 'buy';    // __('Buy')
    const ACTION_DOWNLOAD = 'download'; // __('Download')
    const ACTION_UPDATE = 'update';  // __('Update')
    const ACTION_INSTALL = 'install';  // __('Install')
    //
    const MOD_PATH = 'Base_EpesiStoreCommon';
    const CART_VAR = 'cart';
    const DOWNLOAD_QUEUE_VAR = 'queue';

    public static function menu() {
        if (!self::admin_access())
            return;
        if (!Base_EssClientCommon::has_license_key())
            return;
        return array(_M('Support') => array('__submenu__' => 1, _M('EPESI Store') => array('__function__' => 'manage')));
    }

    public static function admin_access() {
        $trial = defined('TRIAL_MODE') ? TRIAL_MODE : 0;
        return Base_AclCommon::i_am_sa() && !$trial;
    }

    public static function admin_caption() {
        return array('label' => __('Modules Administration & Store'), 'section' => __('Server Configuration'));
    }

    static $module_cache = null;

    public static function get_modules_all_available() {
        if (self::$module_cache === null) {
            $ret = Base_EssClientCommon::server()->modules_list();
            if (is_array($ret)) {
                $modules = $ret['modules'];
                $downloaded_modules = self::get_downloaded_modules();
                foreach ($modules as & $m) {
                    $m['action'] = self::next_possible_action($m, $downloaded_modules);
                }
                self::$module_cache = $modules;
            }
        }
        return self::$module_cache;
    }

    public static function get_module_info_cached($module_id) {
        if (self::$module_cache === null) {
            self::get_modules_all_available();
        }
        return isset(self::$module_cache[$module_id]) ? self::$module_cache[$module_id] : null;
    }

    private static function next_possible_action($module, $downloaded_modules = null) {
        if (!$downloaded_modules)
            $downloaded_modules = self::get_downloaded_modules();

        if (isset($module['bought']) && $module['bought'] && isset($module['paid']) && $module['paid']) {
            if (!isset($downloaded_modules[$module['id']]))
                return self::ACTION_DOWNLOAD;
            else if (self::version_compare($module['version'], $downloaded_modules[$module['id']]['version']) > 0)
                return self::ACTION_UPDATE;
            else
                return self::ACTION_INSTALL;
        }
        else
            return self::ACTION_BUY;
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
        if (!Base_EpesiStoreCommon::admin_access())
            return array();
        set_time_limit(0);
        // get default data from user contact
        $my_contact = ModuleManager::is_installed('CRM_Contacts') > -1 ?
                CRM_ContactsCommon::get_my_record() : array();
        // key = field name from contact => value = field name in settings
        $keys = self::get_payment_data_keys();
        $values = array();
        // do user setting entries from data
        foreach ($keys as $k => $v) {
            $x = array(
                'name' => $v,
                'label' => _V(ucwords(str_replace('_', ' ', $v))),
                'type' => 'text',
                'default' => isset($my_contact[$k]) ? $my_contact[$k] : '');
            if ($k == 'country') {
                $x['type'] = 'select';
                $x['values'] = Utils_CommonDataCommon::get_translated_array('Countries');
            }
            $values[] = $x;
        }
        return array(__('EPESI Store') =>
            array_merge(
                    array(array('name' => 'payments_header', 'label' => '', 'type' => 'header', 'default' => __('Payment credentials')))
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
            $r['active'] = $r['active'] ? __('Yes') : __('No');
        $r['files'] = implode("<br/>", $r['files']);
        unset($r['icon_url']);
        unset($r['description_url']);
        unset($r['required_modules']);
        unset($r['needed_modules']);
        return Utils_TooltipCommon::format_info_tooltip($r);
    }

    /**
     * modules list in some range
     * @param int $offset starting module
     * @param int $amount number of items
     * @return array modules data
     */
    public static function modules_list($offset, $amount) {
        $response = Base_EssClientCommon::server()->modules_list($offset, $amount);
        return $response;
    }

    /**
     * Get module info from cache or server.
     * @param numeric|array $module_id module id
     * @param boolean $force set true to force query to server
     * @return array modules data array
     */
    public static function get_module_info($module_id, $force = false) {
        $modules_cache = Module::static_get_module_variable(self::MOD_PATH, 'modules_info', array());
        $ret = array();
        $request = array();
        $return_array = is_array($module_id) ? true : false;
        if (!is_array($module_id))
            $module_id = array($module_id);
        // split cached and modules for request.
        foreach ($module_id as $id) {
            if (!array_key_exists($id, $modules_cache) || $force)
                $request[] = $id;
            else
                $ret[$id] = $modules_cache[$id];
        }
        // request modules info and merge with cache and return value
        if (count($request)) {
            $response = Base_EssClientCommon::server()->module_get_info($request);
            if (is_array($response)) {
                foreach ($response as $k => $v) {
                    $ret[$k] = $v;
                    $modules_cache[$k] = $v;
                }
            }
        }
        Module::static_set_module_variable(self::MOD_PATH, 'modules_info', $modules_cache);
        // ret only one record if only one was requested
        return $return_array ? $ret : reset($ret);
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

            self::apply_patches();
            ModuleManager::create_common_cache();
            Base_ThemeCommon::themeup();
            Base_LangCommon::update_translations();
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
        if (!$file_contents)
            throw new ErrorException('File download error. See user messages for more info.');
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

    private static function apply_patches() {
        $patches = PatchUtil::apply_new();
        foreach ($patches as $patch) {
            if (!$patch->get_apply_success()) {
                Base_EssClientCommon::add_client_message_error("Patch apply error. See patches log for more information (EPESI_DIR/data/patches_log.txt)");
            }
        }
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

    /**
     * compare version of modules
     * @param string $v1 version param 1
     * @param string $v2 version param 2
     * @return int 1 when $v1 > $v2; -1 when $v1 < $v2; 0 when equal
     */
    public static function version_compare($v1, $v2) {
        if ($v1 > $v2)
            return 1;
        if ($v1 < $v2)
            return -1;
        return 0;
    }

    public static function action_href($module_id, $action, $response_callback = null) {
        return Base_BoxCommon::main_module_instance()->create_callback_href(array('Base_EpesiStoreCommon', 'handle_module_action'), array($module_id, $action, $response_callback));
    }

    public static function handle_module_action($module, $action, $response_callback = null) {
        if (Base_BoxCommon::main_module_instance()->is_back())
            return false;
        $return = null;
        switch ($action) {
            case self::ACTION_BUY:
                $modules = array_merge(array($module['id']), $module['needed_modules']);
                $response = Base_EssClientCommon::server()->order_submit($modules);
                $return = isset($response['order_id']) ?
                            ($response['order_id'] !== null) : false;
                if ($return !== true)
                    break;
                $needs_payment = isset($response['needs_payment']) ?
                                    $response['needs_payment'] : false;
                if (!$needs_payment)
                    break;
                $return = self::_display_payments_for_order($response['order_id']);
                if ($return === true) {
                    Base_ActionBarCommon::add('back', __('Back'), Base_BoxCommon::main_module_instance()->create_back_href());
                    return true;
                }
                break;
            case self::ACTION_DOWNLOAD:
            case self::ACTION_UPDATE:
                $mi = self::_active_module_license_for_module($module['id']);
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

    private static function _display_payments_for_order($order_id) {
        $orders = Base_EssClientCommon::server()->orders_list();
        if (isset($orders[$order_id])) {
            $o = $orders[$order_id];
            $keys = array_keys($o['price']);
            $currency = reset($keys);
            $value = $o['price'][$currency]['to_pay'];
            $main_module = Base_BoxCommon::main_module_instance();
            $store = $main_module->init_module('Base/EpesiStore');
            $main_module->display_module($store, array($o['id'], $value, $currency), 'form_payment_frame');
            return true;
        }
        return "No such order to perform payment.";
    }

    private static function crc_file_matches($file, $crc) {
        if (!is_readable($file))
            return false;
        $file_crc = hexdec(@hash_file("crc32b", $file));
        if ($file_crc == $crc)
            return true;
        // crc may be negative - sprintf as unsigned
        return $file_crc == sprintf("%u", $crc);
    }

    /**
     * Check if:
     * 1. zipped package is present in data dir
     * 2. files in zipped package matches files in epesi installation
     * to compare files crc32b is used which is present in stat info of zip file
     * @param int $module_id
     * @return boolean true when above requirements are satisfied, false otherwise
     */
    public static function is_module_downloaded($module_id) {
        $file = DB::GetOne('SELECT file FROM epesi_store_modules WHERE module_id=%s', array($module_id));
        if (!$file)
            return false;
        $file = self::Instance()->get_data_dir() . $file;
        $zip = new ZipArchive();
        if ($zip->open($file) !== true)
            return false;

        $ret = true;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!self::crc_file_matches($stat['name'], $stat['crc'])) {
                $ret = false;
                break;
            }
        }
        $zip->close();
        return $ret;
    }

    /**
     * Get downloaded modules list.
     * Array keys are 'module_id', 'version', 'file' and 'module_license_id'
     * @return associative array of data. Key is module id.
     */
    public static function get_downloaded_modules() {
        $records = DB::GetAssoc('SELECT * FROM epesi_store_modules');
        return $records;
    }

    private static function add_downloaded_module($module_id, $version, $module_license_id, $file) {
        DB::Execute('DELETE FROM epesi_store_modules WHERE module_id=%d', array($module_id));
        DB::Execute('INSERT INTO epesi_store_modules(module_id, version, module_license_id, file) VALUES (%d, %s, %d, %s)', array($module_id, $version, $module_license_id, $file));
    }

    public static function is_update_available($force_check = false) {
        $esu = Variable::get('epesi_store_updates', false);
        $today = date('Ymd');
        if ($force_check || !is_array($esu) || $esu['check_day'] != $today) {
            $updates = self::_count_updates_of_downloaded_modules();
            $esu = array('check_day' => $today, 'updates' => $updates);
            Variable::set('epesi_store_updates', $esu);
        }
        return $esu['updates'];
    }

    private static function _count_updates_of_downloaded_modules() {
        $modules = self::get_downloaded_modules();
        $updates = 0;
        $modules_ids = array_keys($modules);
        $response = self::get_module_info($modules_ids);
        if (is_array($response)) {
            foreach ($response as $mod) {
                if (isset($modules[$mod['id']]) &&
                        self::version_compare($mod['version'], $modules[$mod['id']]['version']) > 0)
                    $updates++;
            }
        }
        return $updates;
    }

}

?>