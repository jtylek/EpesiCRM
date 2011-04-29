<?php

/**
 *
 * @author Adam Bukowski <abukowski@telaxus.com>
 * @copyright Copyright &copy; 2011, Telaxus LLC
 */

/**
 * Use this interface to perform clients requests to Epesi Service Server
 */
interface IClient {
    const param_function = 'f';
    const param_arguments = 'a';
    const param_installation_key = 'c';

    /**
     * Set specific client license key.
     * USED ONLY LOCALLY - NOT SERVER REQUEST
     *
     * @param string $license_key unique client identifier
     */
    function set_client_license_key($license_key);

    /**
     * Get installation status.
     * @return string|false Installation status or false on error.
     */
    function get_installation_status();
    /**
     * Register installation and request temporary client identifier.
     *
     * @todo specify data in registration
     * @param array data from registration form
     * @return string|bool temporary client identifier, true on successful data update or false on any error
     */
    function register_client_id_request($data);

    /**
     * Confirm temporary client id.
     *
     * @return boolean true on success, false on failure
     */
    function register_client_id_confirm();

    /**
     * Use this function before get_module_file() to generate package on server side.
     * 
     * @param string $module_id unique identifier of module package
     * @return string|false file sha1 sum or false on error
     */
    function get_module_hash($module_id);

    /**
     * Download package file contents.
     * 
     * @param string $module_file_hash sha1 sum of module package file returned by get_module_hash()
     * $return string|false file data on success(use file_put_contents) or false on error
     */
    function get_module_file($module_file_hash);

    /**
     * Request module additional info.
     *
     * @todo specify returned array
     * @param string $module_id unique identifier of module package
     * @return array|false array with module info or false on error
     */
    function get_module_info($module_id);

    /**
     * Request list of available modules in specific range.
     * Useful in Tabbed browsing in GenericBrowser.
     *
     * @todo specify module array fields
     * @param int $start number of first record
     * @param int $amount amount of records
     * @return array|false array of modules with info or false on error
     */
    function get_list_of_modules($start, $amount);

    /**
     * Request total amount of available modules.
     * 
     * @return int|false amount or false on error
     */
    function get_list_of_modules_total_amount();
}

?>
