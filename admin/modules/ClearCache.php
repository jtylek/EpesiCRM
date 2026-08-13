<?php

class ClearCache extends SteppedAdminModule {

    public function menu_entry() {
        return 'Clear Cache';
    }

    public function icon() {
        return 'bi-arrow-clockwise';
    }

    public function required_epesi_modules() {
        return array();
    }

    public function header() {
        return 'Clear Cache';
    }

    public function action() {
        Cache::clear();
        ModuleManager::create_common_cache();
        return true;
    }

    public function start_text() {
        $this->set_button_text('Clear Cache');
        return '<center>This clears EPESI\'s internal cache (menus, common-method lookups, theme/module cache) and rebuilds it. Continue?</center>';
    }

    public function success_text() {
        return '<center>Cache cleared and rebuilt.</center>';
    }

    public function failure_text() {
        return 'Failed to clear cache';
    }

}

?>
