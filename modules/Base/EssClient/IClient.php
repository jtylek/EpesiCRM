<?php

/**
 * Use this interface to perform clients requests to Epesi Service Server.
 * @author Adam Bukowski <abukowski@telaxus.com>
 * @copyright Copyright &copy; 2011, Telaxus LLC
 * @version 20120310
 */
interface IClient {

    const client_version = 5;
    const MESSAGES_INFO = 0;
    const MESSAGES_WARN = 1;
    const MESSAGES_ERROR = 2;
    const param_function = 'f';
    const param_arguments = 'a';
    const param_installation_key = 'c';
    const param_client_version = 'v';
    const param_serialize = 's';
    const param_lang = 'l';
    const return_messages = 'msg';
    const return_value = 'val';

    /**
     * Set specific client license key.
     * USED ONLY LOCALLY - NOT SERVER REQUEST
     *
     * @param string $license_key unique client identifier
     */
    function set_client_license_key($license_key);

    /**
     * Get installation status.
     *
     * @return string|false Installation status or false on error.
     */
    function installation_status();

    /**
     * Get company data stored on server.
     * 
     * @return array registered data.
     */
    function installation_registered_data();

    /**
     * Register installation and request client identifier.
     *
     * @param array $data from registration form
     * @return string|bool client identifier, true on successful data update or false on any error
     */
    function register_installation_request($data);

    /**
     * Confirm client id.
     *
     * @param string $key confirmation key
     * @return boolean true on success, false on failure
     */
    function register_installation_confirm($key);

    /**
     * Use this function before download_prepared_file() to generate package on server side.
     * 
     * @param array $module_license_ids Ids of module licenses to download in one file
     * @return string|null file sha1 sum or null on error
     */
    function download_prepare($module_license_ids);

    /**
     * Download package file contents.
     * 
     * @param string $file_hash sha1 sum of package file returned by download_prepare()
     * @return string|false file data on success(use file_put_contents) or false on error
     */
    function download_prepared_file($file_hash);

    /**
     * Request module info.
     *
     * @param string $module_id unique identifier of module package
     * @return array|false array with module info or false on error
     */
    function module_get_info($module_id);

    /**
     * Request list of available modules in specific range.
     * Useful in Tabbed browsing in GenericBrowser.
     *
     * @param int $start number of first record
     * @param int $amount amount of records
     * @return array|false array of modules or false on error
     */
    function modules_list($start = null, $amount = null);

    /**
     * Submit order to server to buy modules.
     * 
     * @param array $modules_ids array of module ids
     * @return array Array of mixed values. One is 'order_id'=>order_id, rest - key is module id, value is true on success, false or string message on error.
     */
    function order_submit($modules_ids);

    /**
     * Get list of orders
     *
     * @return array Array of orders
     */
    function orders_list();

    /**
     * Get list of module licenses
     * 
     * @return array Array of module licenses
     */
    function module_licenses_list();
}

?>
