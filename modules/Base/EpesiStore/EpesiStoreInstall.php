<?php

/**
 * 
 * @author abukowski@telaxus.com
 * @copyright Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-Base
 * @subpackage EpesiStore
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_EpesiStoreInstall extends ModuleInstall {

    public function install() {
		Base_ThemeCommon::install_default_theme($this->get_type());
        $ret = true;
        $ret &= DB::CreateTable('epesi_store_modules', '
            module_id I4 PRIMARY KEY,
            version C(10),
            module_license_id I4 NOTNULL,
            file C(20)');
        if (!$ret) {
            print('Unable to create table epesi_store_modules.<br>');
            return false;
        }
        if ($this->create_data_dir())
            return true;
        return false;
    }

    public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme($this->get_type());
        DB::DropTable('epesi_store_modules');
        $this->remove_data_dir();
        return true;
    }

    public function version() {
        return array("1.0");
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
            'Description' => 'Epesi store allows administrator to buy and download additional modules and updates',
            'Author' => 'abukowski@telaxus.com',
            'License' => 'MIT');
    }

    public static function simple_setup() {
        return array('package'=>__('EPESI Core'));
    }

}

?>