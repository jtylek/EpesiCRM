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
        setcookie(self::COOKIE_NAME, $key, ['expires' => time() + 7 * 24 * 60 * 60, 'path' => EPESI_DIR]);
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
        $msg = $maintenance_mode_message ?? "System is in the maintenance mode. Please wait until your system administrator will turn it off.";
        // This can fire for ANY entry point (root index.php, admin/index.php,
        // ...) before Smarty/the module system/DB are even loaded (see this
        // file's own require order in include.php) - so, same reasoning as
        // theme/index.tpl's #epesiStatus boot splash, this is fully self-
        // contained inline CSS with no external stylesheet/webfont dependency
        // (no reliable relative path to libs/ exists from an unknown depth,
        // and no reliable app context to link one from). $msg is admin-
        // supplied (MaintenanceMode::turn_on()'s $message, persisted via
        // var_export()) but now rendered inside real HTML instead of as bare
        // text, so it's escaped here rather than trusted raw.
        die('<!DOCTYPE html><html lang="en"><head><meta charset="utf-8" />'
            . '<meta name="viewport" content="width=device-width, initial-scale=1" />'
            . '<title>Maintenance Mode</title><style>'
            . 'body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;'
            . 'background-color:#f4f6f9;font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;color:#212529;}'
            . '.card{background:#fff;border-radius:0.5rem;box-shadow:0 0.5rem 1.5rem rgba(0,0,0,0.15);'
            . 'padding:2.5rem 2rem;max-width:26rem;width:90%;text-align:center;}'
            . '.icon{width:4rem;height:4rem;margin:0 auto 1.25rem;border-radius:50%;background-color:#e7f1ff;'
            . 'display:flex;align-items:center;justify-content:center;font-size:1.75rem;line-height:1;}'
            . 'h1{font-size:1.35rem;font-weight:600;margin:0 0 0.75rem;}'
            . 'p{margin:0;line-height:1.5;color:#495057;}'
            . '</style></head><body><div class="card">'
            . '<div class="icon">&#128295;</div>'
            . '<h1>Under maintenance</h1>'
            . '<p>' . htmlspecialchars($msg) . '</p>'
            . '</div></body></html>');
    }
}
