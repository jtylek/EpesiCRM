<?php

class ThemeUp extends SteppedAdminModule {

    public function menu_entry() {
        return "Theme Updater Utility";
    }

    public function required_epesi_modules() {
        return array('Base_Theme');
    }

    public function header() {
        return 'Theme Updater Utility';
    }

    public function action() {
        set_time_limit(0);
        Cache::clear();
        ModuleManager::create_common_cache();
        Base_ThemeCommon::themeup();
        return true;
    }

    public function start_text() {
        return '<center>This utility will rebuild Theme Cache files.<br/><br/>'
                . 'After clicking Next button please wait...<br/>'
                . 'Rebuilding theme files may take a while.</center>';
    }

    public function success_text() {
        $text = '<center><strong>Theme templates cache was successfully updated.</strong><br>'; 
        return $text;
    }

    public function failure_text() {
        return 'Failure';
    }

}

?>