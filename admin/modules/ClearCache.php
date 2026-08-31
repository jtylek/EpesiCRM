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
        return true;
    }

    public function start_text() {
        $this->set_button_text('Clear Cache');
        return '<center>This clears EPESI\'s internal cache (menus, common-method lookups, theme/module cache). It is rebuilt as it is needed. Continue?</center>';
    }

    public function success_text() {
        return '<center>Cache cleared.</center>';
    }

    public function failure_text() {
        return 'Failed to clear cache';
    }

}

?>
