<?php

/**
 * Lightweight Smarty bootstrap for admin/ - deliberately independent of the
 * module/theme rendering pipeline (Base_ThemeCommon::init_smarty() etc.),
 * since admin/ must still work before the app is configured or logged in.
 * Mirrors how the root index.php bootstraps its own Smarty instance.
 *
 * @author  Janusz Tylek <j@epe.si>
 */
class AdminSmarty {

    private static $smarty;

    private static function instance() {
        if (!self::$smarty) {
            require_once('modules/Base/Theme/smarty/Smarty.class.php');
            $smarty = new Smarty();
            $smarty->template_dir = 'admin/templates';
            $smarty->compile_dir = TEMP_DIR . '/Admin/compiled/';
            $smarty->compile_id = 'admin';
            if (!is_dir($smarty->compile_dir))
                mkdir($smarty->compile_dir, 0777, true);
            // An array callable, not a closure: Smarty 2's compiler embeds
            // registered modifier callbacks into the compiled template file
            // it caches to disk, which only works for a string/array
            // callable - matches Base_ThemeCommon::init_smarty()'s identical
            // registration of this same 't' convention.
            $smarty->register_modifier('t', array(__CLASS__, 'translate'));
            self::$smarty = $smarty;
        }
        return self::$smarty;
    }

    static function translate($s) {
        return __($s);
    }

    static function render($template, array $vars = array()) {
        $smarty = self::instance();
        foreach ($vars as $key => $value)
            $smarty->assign($key, $value);
        return $smarty->fetch($template);
    }

}

?>
