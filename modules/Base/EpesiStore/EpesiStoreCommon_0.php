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

    public static function admin_access() {
        return Base_AclCommon::i_am_sa();
    }

    public static function admin_caption() {
        return "Epesi Store";
    }

    protected static function get_payment_data_keys() {
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
		if (ModuleManager::is_installed('CRM_Contacts')>-1)
			$r = CRM_ContactsCommon::get_my_record();
		else
			$r = array();
        // key = field name from contact => value = field name in settings
        $keys = self::get_payment_data_keys();
        $values = array();
        // do user setting entries from data
        foreach ($keys as $k => $v) {
            $x = array('name' => $v, 'label' => ucwords(str_replace('_', ' ', $v)), 'type' => 'text', 'default' => isset($r[$k])?$r[$k]:'');
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

    public static function module_format_info($r) {
        $x = array();
        $x[] = "<big><strong>{$r['name']}</strong></big>";
        if ($r['description'])
            $x[] = "<b>Description:</b><br/>{$r['description']}";
        $x[] = "<b>Repository:</b> {$r['repository']}";
        if (isset($r['path']))
            $x[] = "<b>Files:</b><br/>{$r['path']}";
        if (isset($r['files']))
            $x[] = "<b>Files:</b><br/>" . implode("<br/>", $r['files']);
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

    public static function download_module($module_license) {
        $file = self::download_module_file($module_license);
        self::extract_module_file($file);
        self::store_info_about_downloaded_module($module_license, $file);
        return $file;
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
        Base_EpesiStoreCommon::add_downloaded_module($module_info['id'], $module_info['version'], $module_license['id'], $file);
    }

    /**
     * Extract from archives and download modules, that have been previously downloaded.
     * Useful to extract files after epesi update.
     * @return array 'old' => modules, 'new' => modules, 'error' => array(error_message => modules, ...)
     */
    public static function download_all_downloaded() {
        $return = array('old' => array(), 'new' => array(), 'error' => array());
        $mods = self::get_downloaded_modules();
        if (!count($mods))
            return true;

        $modules_to_download = array();
        $files = array();
        foreach ($mods as $m) {
            if ($m['file']) {
                if (!isset($files[$m['file']]))
                    $files[$m['file']] = array();
                $files[$m['file']][] = $m;
            } else {
                $modules_to_download[] = $m;
            }
        }
        // extract files
        foreach ($files as $file => $downloaded_modules_data) {
            try {
                extract_package($file);
                $return['old'] = array_merge($return['old'], $downloaded_modules_data);
            } catch (ErrorException $e) {
                $modules_to_download = array_merge($modules_to_download, $downloaded_modules_data);
            }
        }
        // download necessary modules
        if (!count($modules_to_download)) {
            return $return;
        }
        foreach ($modules_to_download as $o) {
            try {
                $file = self::download_module($o['module_license_id']);
                foreach ($modules_to_download as &$o) {
                    self::add_downloaded_module($o['module_id'], $o['version'], $o['order_id'], $new_file);
                    $o['file'] = $new_file;
                }
                $return['new'] = array_merge($return['new'], $modules_to_download);
            } catch (ErrorException $e) {
                $msg = $e->getMessage();
                if (isset($return['error'][$msg]))
                    $return['error'][$msg] = array_merge($return['error'][$msg], $modules_to_download);
                else
                    $return['error'][$msg] = $modules_to_download;
            }
        }
        return $return;
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

    public static function add_downloaded_module($module_id, $version, $module_license_id, $file) {
        // TODO: change column name in db
        DB::Execute('REPLACE INTO epesi_store_modules(module_id, version, order_id, file) VALUES (%d, %s, %d, %s)', array($module_id, $version, $module_license_id, $file));
    }

}

?>