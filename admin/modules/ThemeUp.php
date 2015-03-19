<?php

class ThemeUp extends SteppedAdminModule {

    public function menu_entry() {
        return "Rebuild common cache & default theme";
    }

    public function required_epesi_modules() {
        return array('Base_Theme');
    }

    public function header() {
        return 'Theme Updater Utility';
    }

    public function action() {
        set_time_limit(0);
        if(function_exists('xcache_clear_cache') && function_exists('xcache_count')) {
            $count = xcache_count(XC_TYPE_PHP);
            for($cache_id=0; $cache_id<$count; $cache_id++)
                xcache_clear_cache(XC_TYPE_PHP,$cache_id);
        }
        ModuleManager::create_common_cache();
        Base_ThemeCommon::themeup();
        return true;
    }

    public function start_text() {
        return '<center>This utility will rebuild Common Cache and refresh Theme files.<br/><br/>'
                . 'After clicking Next button please wait...<br/>'
                . 'Rebuilding theme files may take a while.</center>';
    }

    public function success_text() {
        return '<center><strong>Common Cache and Theme templates were successfully updated.</strong></center>';
    }

    public function failure_text() {
        return 'Failure';
    }

}

?>