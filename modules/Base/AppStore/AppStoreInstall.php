<?php

/**
 * 
 * @author pbukowski@telaxus.com
 * @copyright Telaxus LLC
 * @license MIT
 * @version 0.1
 * @package epesi-Base
 * @subpackage AppStore
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_AppStoreInstall extends ModuleInstall {

    public function install() {
        if ($this->create_data_dir())
            return true;
        return false;
    }

    public function uninstall() {
        $this->remove_data_dir();
        return true;
    }

    public function version() {
        return array("0.1");
    }

    public function requires($v) {
        return array(
            array('name' => 'Base/Admin', 'version' => 0),
            array('name' => 'Base/Lang', 'version' => 0),
            array('name' => 'Base/Menu', 'version' => 0),
            array('name' => 'Libs/QuickForm', 'version' => 0),
            array('name' => 'Base/EssClient', 'version' => 0));
    }

    public static function info() {
        return array(
            'Description' => '',
            'Author' => 'pbukowski@telaxus.com',
            'License' => 'MIT');
    }

    public static function simple_setup() {
        return true;
    }

}

?>