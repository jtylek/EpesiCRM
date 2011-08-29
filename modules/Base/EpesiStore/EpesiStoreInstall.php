<?php

/**
 * 
 * @author pbukowski@telaxus.com
 * @copyright Telaxus LLC
 * @license MIT
 * @version 0.1
 * @package epesi-Base
 * @subpackage EpesiStore
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_EpesiStoreInstall extends ModuleInstall {

    public function install() {
		$ret = true;
		$ret &= DB::CreateTable('epesi_store_modules','
			module_id I4 PRIMARY KEY,
			version I4,
			order_id I4 NOTNULL');
		if(!$ret){
			print('Unable to create table epesi_store_modules.<br>');
			return false;
		}
        if ($this->create_data_dir())
            return true;
        return false;
    }

    public function uninstall() {
        DB::DropTable('epesi_store_modules');
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
            'Description' => 'Epesi store allows administrator to buy and download additional modules and updates',
            'Author' => 'abukowski@telaxus.com',
            'License' => 'MIT');
    }

    public static function simple_setup() {
        return true;
    }

}

?>