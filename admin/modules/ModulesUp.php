<?php

class ModulesUp extends SteppedAdminModule {

    public function menu_entry() {
        return "Update load priority array";
    }

    public function header() {
        return 'Update load priority array';
    }

    public function action() {
        ModuleManager::create_load_priority_array();
        return true;
    }

    public function start_text() {
        return '<center>This utility will rebuild load priority array.<br/><br/>'
                . 'After clicking Next button please wait...</center>';
    }

    public function success_text() {
        return '<center><strong>Load priority array was successfully updated.</strong></center>';
    }

    public function failure_text() {
        return '';
    }

}

?>