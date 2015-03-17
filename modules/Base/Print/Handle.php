<?php


class Base_Print_Handle
{
    private $handler;

    private static $epesi_loaded = false;

    public function handle_request()
    {
        $this->load_epesi();
        $this->init_handler();
        $this->handler->handle_request();
    }

    protected function load_epesi()
    {
        if (self::$epesi_loaded) return;

        define('CID', false);
        define('READ_ONLY_SESSION', true);
        require_once '../../../include.php';

        ModuleManager::load_modules();
        self::$epesi_loaded = true;

        if (!Base_AclCommon::is_user()) {
            throw new ErrorException('Not logged in');
        }
    }

    protected function init_handler()
    {
        $handler_class = self::request_param('handler');
        if (!$handler_class) {
            $handler_class = 'Base_Print_PrintingHandler';
        }
        if (!class_exists($handler_class)) {
            throw new ErrorException('Wrong usage');
        }
        $handler_obj = new $handler_class();
        if (!($handler_obj instanceof Base_Print_PrintingHandler)) {
            throw new ErrorException('Wrong printing handler');
        }
        $this->handler = $handler_obj;
    }

    protected static function request_param($name, $required = false)
    {
        $val = null;
        if (!isset($_REQUEST[$name])) {
            if ($required) {
                throw new Exception('Invalid usage - missing param');
            }
        } else {
            $val = $_REQUEST[$name];
        }
        return $val;
    }

}

if (!defined('_VALID_ACCESS')) {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    
    $handler = new Base_Print_Handle();
    try {
        $handler->handle_request();
    } catch (Exception $ex) {
        die($ex->getMessage());
    }
}