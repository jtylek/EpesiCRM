<?php

class ThemeUp extends SteppedAdminModule {

    public function menu_entry() {
        return "Theme Updater Utility";
    }

    public function required_epesi_modules() {
        return array('Base_Theme');
    }    

    public function header() {
        return '<H1>Theme Updater Utility</H1>';
    }

    public function action() {
        set_time_limit(0);
        Cache::clear();
        ModuleManager::create_common_cache();
        Base_ThemeCommon::themeup();
        return true;
    }

    public function start_text() {
        return 'This utility will rebuild refresh Theme files.<br/><br/>'
                . 'After clicking Next button please wait.<br/>';
    }

    public function success_text() {
        $text = '<center><strong>Common Cache and Theme templates were successfully updated.</strong><br>'; 
        return $text;
    }

    public function failure_text() {
        return 'Failure';
    }

}

?>