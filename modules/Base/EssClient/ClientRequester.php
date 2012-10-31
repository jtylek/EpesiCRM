<?php

require_once 'IClient.php';

class SecureConnectionException extends Exception {
    
}

/**
 * ClientRequester to perform Epesi Service Server clients requests.
 * @author Adam Bukowski <abukowski@telaxus.com>
 * @copyright Copyright &copy; 2011, Telaxus LLC
 * @version 20111121
 */
class ClientRequester implements IClient {

    static private $request_log = array();
    protected $server;
    protected $license_key;
    private $secure = true;

    public function __construct($server) {
        $this->server = $server;
    }

    public function set_client_license_key($license_key) {
        $this->license_key = $license_key;
    }

    public function download_prepare($order_ids) {
        $args = func_get_args();
        return $this->call(__FUNCTION__, $args);
    }

    public function download_prepared_file($file_hash) {
        $args = func_get_args();
        return $this->call(__FUNCTION__, $args, false);
    }

    public function modules_list($start = null, $amount = null) {
        $args = func_get_args();
        return $this->call(__FUNCTION__, $args);
    }

    public function module_get_info($module_id) {
        $args = func_get_args();
        return $this->call(__FUNCTION__, $args);
    }

    public function register_installation_confirm($key) {
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

    public function order_submit($modules) {
        if (TRIAL_MODE) {
            Base_EssClientCommon::add_client_message_error(__('Your installation is locked, you can\'t download new modules. Switching to paid hosting will enable you to unlock your installation and purchase and download new modules.'));
            return array();
        }
        $args = func_get_args();
        return $this->call(__FUNCTION__, $args);
    }

    public function orders_list() {
        $args = func_get_args();
        return $this->call(__FUNCTION__, $args);
    }

    public function module_licenses_list() {
        $args = func_get_args();
        return $this->call(__FUNCTION__, $args);
    }

    public function get_module_as_file_post_data_array($hash_or_url) {
        $args = func_get_args();
        return $this->prepare_post_data('download_prepared_file', $args, false);
    }

    protected function call($function, $params, $serialize_response = true) {
        self::log($function, $params, $serialize_response);
        $post_data = $this->build_query_post_data($function, $params, $serialize_response);
        $try_times = 3;
        while ($try_times--) {
            try {
                if ($this->secure_connection() == false)
                    Base_EssClientCommon::add_client_message_warning("Used unsecure connection!");
                $response = $this->request_server($post_data, !$serialize_response);
                return $this->return_response_value_handling_user_messages($serialize_response, $response);
            } catch (SecureConnectionException $e) {
                if (!Base_EssClientCommon::is_no_ssl_allowed()) {
                    $disable_link_href = Base_BoxCommon::main_module_instance()->create_callback_href(array('Base_BoxCommon', 'push_module'), array('Base_EssClient', 'no_ssl_settings'));
                    $disable_msg = "<br/>Or disable secure connection here: <a $disable_link_href>SSL settings</a>";
                    Base_EssClientCommon::add_client_message_error($e->getMessage() . $disable_msg);
                } else {
                    $this->disable_secure_connection();
                    continue;
                }
            } catch (ErrorException $e) {
                Base_EssClientCommon::add_client_message_error($e->getMessage());
                return null;
            }
        }
        if (!$try_times)
            Base_EssClientCommon::add_client_message_error("Connection try limit exceeded");
    }

    private function disable_secure_connection() {
        $this->secure = false;
        $this->server = str_replace('https:', 'http:', $this->server);
    }

    private function secure_connection() {
        return preg_match("/^https.*/", $this->server);
    }
    
    private function build_query_post_data($function, & $params, $serialize_response) {
        return http_build_query($this->prepare_post_data($function, $params, $serialize_response));
    }

    private function prepare_post_data($function, & $params, $serialize_response) {
        return array(
            IClient::param_function => $function,
            IClient::param_installation_key => $this->license_key,
            IClient::param_client_version => IClient::client_version,
            IClient::param_serialize => $serialize_response,
            IClient::param_arguments => serialize($params),
            IClient::param_lang => Base_LangCommon::get_lang_code()
        );
    }

    protected function request_server(& $post_data, $force_fgc = false) {
		@set_time_limit(300);
        if ($this->is_curl_loaded() && !$force_fgc)
            return $this->curl_call($post_data);
        else
            return $this->fgc_call($post_data);
    }

    protected function return_response_value_handling_user_messages($serialized, &$response) {
        if ($serialized) {
            $unserialized_response = $this->unserialize_response($response);
            $this->extract_user_messages($unserialized_response);
            return $unserialized_response[IClient::return_value];
        }
        return $response;
    }

    protected function is_curl_loaded() {
        return false !== array_search('curl', get_loaded_extensions());
    }

    protected function unserialize_response(& $response) {
        // if unserialized response is false
        if ($response == serialize(false))
            return false;
        // check if there was an unserialize error
        $unserialized_response = @unserialize($response);
        if ($unserialized_response === false) {
            Base_EssClientCommon::add_client_message_error('<b>' . __('Remote server error') . '</b><br/>' . __('Your EPESI is fine. Our server had some problem. Please give us some time to fix this.'));
        }

        return $unserialized_response;
    }

    protected function extract_user_messages(& $response) {
        // format client messages
        if (isset($response[IClient::return_messages])) {
            Base_EssClientCommon::add_client_messages($response[IClient::return_messages]);
        }
    }

    protected function fgc_call($post_data) {
        if ($this->secure_connection() && !extension_loaded('openssl'))
            throw new SecureConnectionException("Your server doesn't support ssl connection. Please load extension 'openssl.'");

        $http['method'] = 'POST';
        $http['header'] = 'Content-Type: application/x-www-form-urlencoded';
        $http['content'] = $post_data;

        set_error_handler(create_function('$code, $message', 'throw new ErrorException($message);'));
        $exception = null;
        $output = false;
        try {
            $output = file_get_contents($this->server, false, stream_context_create(array('http' => $http)));
        } catch (ErrorException $e) {
            $exception = $e;
        }
        restore_error_handler();
        if ($output === false) {
            if ($exception)
                throw $exception;
            else
                throw new ErrorException("File get contents unknown error");
        }
        return $output;
    }

    protected function curl_call($post_data) {
        $ch = curl_init($this->server);
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        $output = curl_exec($ch);
        $errno = curl_error($ch);
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
        return $output;
    }
    
    static function get_log() {
        return self::$request_log;
    }
    
    private static function log($function, $params, $serialize_response) {
        self::$request_log[] = array('function' => $function, 'params' => $params, 'serialize_response' => $serialize_response);
    }

}

?>
