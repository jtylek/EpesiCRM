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

    public function get_list_of_modules($start, $amount) {
        return $this->call(__FUNCTION__, func_get_args());
    }

    public function get_list_of_modules_total_amount() {
        return $this->call(__FUNCTION__, func_get_args());
    }

    public function get_module_file($module_file_hash) {
        return $this->call(__FUNCTION__, func_get_args(), false);
    }

    public function get_module_hash($module_id) {
        return $this->call(__FUNCTION__, func_get_args());
    }

    public function get_module_info($module_id) {
        return $this->call(__FUNCTION__, func_get_args());
    }

    public function register_installation_confirm() {
        return $this->call(__FUNCTION__, func_get_args());
    }

    public function register_installation_request($data) {
        return $this->call(__FUNCTION__, func_get_args());
    }

    public function get_installation_status() {
        return $this->call(__FUNCTION__, func_get_args());
    }

    public function get_registered_data() {
        return $this->call(__FUNCTION__, func_get_args());
    }

    public function set_client_license_key($license_key) {
        $this->license_key = $license_key;
    }

    protected function call($function, $params, $unserialize = true) {
        $err_msg = '';
        $post = http_build_query(
                array(
                    IClient::param_function => $function,
                    IClient::param_installation_key => $this->license_key,
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

        if ($unserialize) {
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
