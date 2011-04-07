<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

require('IClient.php');

/**
 * Description of ClientRequester
 *
 * @author ajb
 */
class ClientRequester implements IClient {

    protected $license_key;

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

    public function register_client_id_confirm() {
        return $this->call(__FUNCTION__, func_get_args());
    }

    public function register_client_id_request($data) {
        return $this->call(__FUNCTION__, func_get_args());
    }

    public function set_client_license_key($license_key) {
        $this->license_key = $license_key;
    }

    protected function call($function, $params, $unserialize = true) {
        $err_msg = '';
        $post = http_build_query(array('f' => $function, 'c' => $this->license_key, 'a' => serialize($params)));
//        $ch = curl_init('https://localhost/epesi/tools/EpesiServiceServer/');
        $ch = curl_init('http://127.0.0.1/epesi-trunk/tools/EpesiServiceServer/');

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
            print($filename . ' download returned cURL error: ' . $errno);
        }

        if ($response_code == '404' || $response_code == '403') {
            return $response_code;
        }

        if ($unserialize) {
            $r = @unserialize($output);
            return $r?$r:504;
        } else
            return $output;
    }

}

?>
