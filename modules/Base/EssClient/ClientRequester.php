<?php

require_once 'IClient.php';

/**
 * ClientRequester to perform Epesi Service Server clients requests.
 * @author Adam Bukowski <abukowski@telaxus.com>
 * @copyright Copyright &copy; 2011, Telaxus LLC
 */
class ClientRequester implements IClient {

    protected $server;
    protected $license_key;

    public function __construct($server) {
        $this->server = $server;
    }

    public function download_prepare($order_ids) {
        $args = func_get_args();
        return $this->call(__FUNCTION__, $args);
    }

    public function download_prepared_file($file_hash) {
        $args = func_get_args();
        return $this->call(__FUNCTION__, $args, false);
    }

    public function modules_list($start, $amount) {
        $args = func_get_args();
        return $this->call(__FUNCTION__, $args);
    }

    public function modules_list_total_amount() {
        $args = func_get_args();
        return $this->call(__FUNCTION__, $args);
    }

    public function module_get_info($module_id) {
        $args = func_get_args();
        return $this->call(__FUNCTION__, $args);
    }

    public function register_installation_confirm() {
        $args = func_get_args();
        return $this->call(__FUNCTION__, $args);
    }

    public function register_installation_request($data) {
        $args = func_get_args();
        return $this->call(__FUNCTION__, $args);
    }

    public function installation_status() {
        $args = func_get_args();
        return $this->call(__FUNCTION__, $args);
    }

    public function installation_registered_data() {
        $args = func_get_args();
        return $this->call(__FUNCTION__, $args);
    }

    public function set_client_license_key($license_key) {
        $this->license_key = $license_key;
    }

    public function order_submit($modules) {
        $args = func_get_args();
        return $this->call(__FUNCTION__, $args);
    }

    public function orders_list() {
        $args = func_get_args();
        return $this->call(__FUNCTION__, $args);
    }

    protected function call($function, $params, $serialize_response = true) {
        $err_msg = '';
        $post = http_build_query(
                array(
                    IClient::param_function => $function,
                    IClient::param_installation_key => $this->license_key,
                    IClient::param_serialize => $serialize_response,
                    IClient::param_arguments => serialize($params)
                ));
        $ch = curl_init($this->server);

        curl_setopt($ch, CURLOPT_VERBOSE, 1);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
//        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
//        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        $output = curl_exec($ch);
        $errno = curl_error($ch);
        $av_speed = curl_getinfo($ch, CURLINFO_SPEED_DOWNLOAD);
        $response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);
        if ($errno != '') {
            throw new ErrorException("cURL error: $errno");
        }

        if ($response_code == '404') {
            throw new ErrorException("Server not available!");
        }
        if ($response_code == '403') {
            throw new ErrorException("Authentication failed!");
        }

        if ($serialize_response) {
            // handle unserialization error
            if ($output == serialize(false))
                return false;
            $r = @unserialize($output);
            if ($r === false)
                throw new ErrorException("Unserialize error $output");
            return $r;
        } else
            return $output;
    }

}

?>
