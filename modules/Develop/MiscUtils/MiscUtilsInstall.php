<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Develop_MiscUtilsInstall extends ModuleInstall {

    public function install() {
        return true;
    }

    public function uninstall() {
        return true;
    }

    public function version() {
        return array('1.0');
    }

    public function requires($v) {
        return array();
    }

    public static function simple_setup() {
        return false;
    }

}

?>