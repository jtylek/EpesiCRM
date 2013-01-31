<?php

class LangUp extends SteppedAdminModule {

    public function menu_entry() {
        return "Rebuild language files";
    }

    public function required_epesi_modules() {
        return array('Base_Lang');
    }

    public function header() {
        return 'Language Updater Utility';
    }

    public function action() {
        Base_LangCommon::update_translations();
        return true;
    }

    public function start_text() {
        return '<center>This utility will rebuild language files.<br/><br/>'
                . 'After clicking Next button please wait...<br/>'
                . 'Rebuilding language files may take a while.</center>';
    }

    public function success_text() {
        return '<center><strong>Language files were successfully updated.</strong></center>';
    }

    public function failure_text() {
        return 'Failure';
    }

}

?>