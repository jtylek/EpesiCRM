<?php

/**
 * 
 * @author  Janusz Tylek <j@epe.si>
 * @copyright Janusz Tylek
 * @license MIT
 * @version 1.9.0
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
            array('name' => Base_AdminInstall::module_name(), 'version' => 0),
            array('name' => Base_LangInstall::module_name(), 'version' => 0),
			array('name' => Base_MenuInstall::module_name(), 'version' => 0),
  			array('name' => Utils_FrontPageInstall::module_name(),'version' => 0),
            array('name' => Libs_QuickFormInstall::module_name(), 'version' => 0));
    }

    public static function info() {
        return array(
            'Description' => 'Perform requests to Epesi Services Servers. Allows installation registration.',
            'Author' => 'j@epe.si',
            'License' => 'MIT');
    }

    public static function simple_setup() {
		return __('EPESI Core');
    }

}

?>