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

    /**
     * Download and extract package of modules.
     * @param array $bought_modules_ids bought modules ids
     * @param var_reference $filename output parameter to pass filename of package
     * @return string|true string with error message, true on success
     */
    public static function download_package($bought_modules_ids, &$filename = false) {
        $hash = Base_EssClientCommon::server()->download_prepare($bought_modules_ids);
        if (!$hash) {
            return 'Prepare error';
        }
        // download file and check sum
        $file_contents = Base_EssClientCommon::server()->download_prepared_file($hash);
        if (sha1($file_contents) !== $hash) {
            return 'File hash error';
        }
        // make temp destination filename
        $destfile = self::Instance()->get_data_dir() . time();
        $i = 0;
        while (file_exists("{$destfile}{$i}.zip"))
            $i++;
        $destfile .= "{$i}.zip";
        // store file
        if (file_put_contents($destfile, $file_contents) === false) {
            return 'File store error';
        }
        if ($filename !== false) {
            $filename = basename($destfile);
        }
        return true;
    }

    /**
     * Extract package from file under EpesiStore module data directory
     * @param string $filename filename with extension
     * @return true|string true on success, string message on error
     */
    public static function extract_package($filename) {
        $destfile = self::Instance()->get_data_dir() . $filename;
        if (!file_exists($destfile))
            return "file $destfile not exists";
        // extract
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if (filesize($destfile) == 0 || $zip->open($destfile) !== true || $zip->extractTo('./') == false) {
                return 'Archive error';
            } else {
                $zip->close();
            }
        } else {
            return 'Please enable zip extension in server configuration!';
        }
        return true;
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

        $orders_to_download = array();
        $files = array();
        foreach ($mods as $m) {
            if ($m['file']) {
                if (!isset($files[$m['file']]))
                    $files[$m['file']] = array();
                $files[$m['file']][] = $m;
            } else {
                $orders_to_download[] = $m;
            }
        }
        // extract files
        foreach ($files as $file => $orders) {
            $ret = self::extract_package($file);
            if ($ret === true) {
                $return['old'] = array_merge($return['old'], $orders);
            } else {
                // no file or some error -> add order to download
                $orders_to_download = array_merge($orders_to_download, $orders);
            }
        }
        // download modules
        if (!count($orders_to_download)) {
            return $return;
        }
        $order_ids = array();
        foreach ($orders_to_download as $o) {
            $order_ids[] = $o['order_id'];
        }
        $new_file = null;
        $ret = self::download_package($order_ids, $new_file);
        if (is_string($ret)) {
            if (isset($return['error'][$ret]))
                $return['error'][$ret] = array_merge($return['error'][$ret], $orders_to_download);
            else
                $return['error'][$ret] = $orders_to_download;
        }
        if ($new_file !== null) {
            $ret = self::extract_package($new_file);
            if ($ret === true) {
                foreach ($orders_to_download as &$o) {
                    self::add_downloaded_module($o['module_id'], $o['version'], $o['order_id'], $new_file);
                    $o['file'] = $new_file;
                }
                $return['new'] = array_merge($return['new'], $orders_to_download);
            } else {
                if (isset($return['error'][$ret]))
                    $return['error'][$ret] = array_merge($return['error'][$ret], $orders_to_download);
                else
                    $return['error'][$ret] = $orders_to_download;
            }
        }
        return $return;
    }

    /**
     * Get downloaded modules list.
     * Array keys are 'module_id', 'version', 'file' and 'order_id' as bought module id
     * @return array of data. 
     */
    public static function get_downloaded_modules() {
        return DB::GetAll('SELECT * FROM epesi_store_modules');
    }

    public static function add_downloaded_module($module_id, $version, $bought_module_id, $file) {
        DB::Execute('REPLACE INTO epesi_store_modules(module_id, version, order_id, file) VALUES (%d, %s, %d, %s)', array($module_id, $version, $bought_module_id, $file));
    }

}

?>