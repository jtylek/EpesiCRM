<?php

/**
 * Lightweight Smarty bootstrap for setup.php/check.php - deliberately
 * independent of the module/theme rendering pipeline (Base_ThemeCommon::
 * init_smarty() etc.), since these scripts must still work before the app
 * is configured (no data/config.php yet) or before any module is installed.
 * Mirrors admin/AdminSmarty.php and update.php's own UpdateSmarty.
 *
 * @author  Janusz Tylek <j@epe.si>
 */
class SetupSmarty {

    private static $smarty;

    private static function instance() {
        if (!self::$smarty) {
            require_once('modules/Base/Theme/smarty/Smarty.class.php');
            $smarty = new Smarty();
            $smarty->template_dir = 'setuptheme';
            // TEMP_DIR isn't defined yet this early in setup.php (it comes from
            // include/config.php, required only once a DB connection exists) -
            // fall back to the OS temp dir so this works before that point too.
            $compile_dir = (defined('TEMP_DIR') ? TEMP_DIR : sys_get_temp_dir()) . '/Setup/compiled/';
            $smarty->compile_dir = $compile_dir;
            $smarty->compile_id = 'setup';
            if (!is_dir($smarty->compile_dir))
                mkdir($smarty->compile_dir, 0777, true);
            // Array callable, not a closure - Smarty 2's compiler embeds
            // registered modifier callbacks into the compiled template file
            // it caches to disk, which only works for a string/array
            // callable (see AdminSmarty::instance()'s identical comment).
            $smarty->register_modifier('t', array(__CLASS__, 'translate'));
            self::$smarty = $smarty;
        }
        return self::$smarty;
    }

    static function translate($s) {
        return function_exists('__') ? __($s) : $s;
    }

    static function render($template, array $vars = array()) {
        $smarty = self::instance();
        // message.tpl references several optional vars ($heading, $pre, $pre_collapsed,
        // $pre_label, $link_href, $link_text) via bare {if $var} - Smarty 2 compiles that
        // to a raw array access with no isset() guard, which is a PHP 8 "Undefined array
        // key" warning for any caller that only passes 'message'. Backfill them here so
        // every message.tpl caller doesn't have to enumerate the same defaults itself.
        if ($template === 'message.tpl')
            $vars += array('heading' => null, 'pre' => null, 'pre_collapsed' => false, 'pre_label' => null, 'link_href' => null, 'link_text' => null);
        foreach ($vars as $key => $value)
            $smarty->assign($key, $value);
        return $smarty->fetch($template);
    }

    // Wraps $body (already-rendered fragment HTML from render() above) in the
    // standalone page shell - kept separate from render() so a caller (like
    // check.php, embedded mid-setup) can render just the fragment and let its
    // own caller decide whether/how to wrap it.
    static function render_page($title, $body, array $vars = array()) {
        $vars['title'] = $title;
        $vars['body'] = $body;
        return self::render('shell.tpl', $vars);
    }

}

?>
