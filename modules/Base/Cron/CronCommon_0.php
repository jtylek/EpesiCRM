<?php
/**
 * Cron Epesi
 *
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-base
 * @subpackage about
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_CronCommon extends ModuleCommon
{
    public static function admin_caption()
    {
        return array('label' => __('Cron'), 'section' => __('Server Configuration'));
    }

    public static function get_cron_url()
    {
        $token = self::load_token();
        $url = get_epesi_url() . '/cron.php?token=' . $token;
        return $url;
    }

    public static function load_token()
    {
        $token_file = self::token_file();
        if (!file_exists($token_file)) {
            self::generate_token();
        }
        if (!defined('CRON_TOKEN')) {
            require_once $token_file;
        }
        $token = defined('CRON_TOKEN') ? CRON_TOKEN : '';
        return $token;
    }

    public static function generate_token()
    {
        $token = md5(time() . getcwd());
        $success = file_put_contents(self::token_file(), '<?php define("CRON_TOKEN", "' . $token . '");');
        if (!$success) {
            throw new ErrorException("Can't generate token file");
        }
        return $token;
    }

    private static function token_file()
    {
        return DATA_DIR . '/cron_token.php';
    }
}

?>
