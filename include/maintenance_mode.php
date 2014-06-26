<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class MaintenanceMode
{
    const COOKIE_NAME = 'maintenance_mode_cookie';

    public static function can_access()
    {
        if (self::is_on()) {
            if (self::is_cli()) {
                return true;
            } else {
                return self::has_access($_COOKIE);
            }
        }
        return true;
    }

    public static function is_cli()
    {
        return php_sapi_name() == 'cli';
    }

    public static function is_on()
    {
        return file_exists(self::get_file());
    }

    private static function get_file()
    {
        $file = DATA_DIR . '/maintenance_mode.php';
        return $file;
    }

    public static function has_access($cookies)
    {
        $key = self::get_key();
        if ($key) {
            if (isset($cookies[self::COOKIE_NAME])) {
                $key_client = $cookies[self::COOKIE_NAME];
                if ($key == $key_client) {
                    return true;
                }
            }
            return false;
        } else {
            return false;
        }
    }

    public static function get_key()
    {
        global $maintenance_mode_key;
        global $maintenance_mode_message;
        $maintenance_mode_key = '';
        include self::get_file();
        return $maintenance_mode_key;
    }

    public static function turn_on($message = null, $key = null)
    {
        if (!$key) {
            $key = generate_password(16);
        }
        self::turn_off();
        self::generate_file($key, $message);
        return $key;
    }

    public static function turn_on_with_cookie($message = null, $key = null)
    {
        $key = self::turn_on($message, $key);
        setcookie(self::COOKIE_NAME, $key, time() + 5 * 365 * 24 * 60 * 60, EPESI_DIR);
    }

    public static function turn_off()
    {
        if (self::is_on()) {
            unlink(self::get_file());
        }
    }

    public static function generate_file($key, $message = null)
    {
        $user = Base_UserCommon::get_my_user_login();
        $date = date('Y-m-d H:i:s');
        $str = "<?php\n";
        $str .= "// by $user on $date\n";
        $str .= '$maintenance_mode_key = ' . var_export($key, true);
        $str .= ";\n";
        $str .= '$maintenance_mode_message = ' . var_export($message, true);
        $str .= ";\n";
        file_put_contents(self::get_file(), $str);
    }

}

if (!MaintenanceMode::can_access()) {
    if (defined('JS_OUTPUT') && JS_OUTPUT) {
        header("Content-type: text/javascript");
        header("Cache-Control: no-cache, must-revalidate");
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // date in the past

        die ('window.location = "index.php";');
    } else {
        global $maintenance_mode_message;
        $msg = isset($maintenance_mode_message)
            ? $maintenance_mode_message
            : "System is in the maintenance mode. Please wait until your system administrator will turn it off.";
        die ($msg);
    }
}
