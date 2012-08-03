<?php

/**
 * 
 * @author abukowski@telaxus.com
 * @copyright Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-Base
 * @subpackage EssClient
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_EssClientInstall extends ModuleInstall {

    public function install() {
		Base_ThemeCommon::install_default_theme($this->get_type());
        return true;
    }

    public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme($this->get_type());
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
  			array('name' => 'Utils/FrontPage','version' => 0),
            array('name' => 'Libs/QuickForm', 'version' => 0));
    }

    public static function info() {
        return array(
            'Description' => 'Perform requests to Epesi Services Servers. Allows installation registration.',
            'Author' => 'abukowski@telaxus.com',
            'License' => 'MIT');
    }

    public static function simple_setup() {
		return __('EPESI Core');
    }

}

?>