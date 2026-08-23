<?php

class ModulesUp extends SteppedAdminModule {

    public function menu_entry() {
        return "Update load priority array";
    }

    public function icon() {
        return 'bi-arrow-repeat';
    }

    public function header() {
        return 'Update load priority array';
    }

    public function action() {
        Cache::clear();
        ModuleManager::create_load_priority_array();
        return true;
    }

    public function start_text() {
        return '<center>This utility will rebuild load priority array.<br/><br/>'
                . 'After clicking Next button please wait...</center>';
    }

    public function success_text() {
        $text = '<p class="text-center"><strong>Load priority array was successfully updated.</strong></p>';
        $text .= '<div class="text-center"><a href="./index.php" class="btn btn-outline-secondary btn-sm">MAIN MENU</a></div>';
        return $text;
    }

    public function failure_text() {
        return '';
    }

}

?>