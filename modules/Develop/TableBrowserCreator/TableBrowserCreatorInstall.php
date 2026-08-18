<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.5
 * @license MIT
 * @package epesi-develop
 * @subpackage tablebrowsercreator
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Develop_TableBrowserCreatorInstall extends ModuleInstall {
	public function install() {
		return true;
	}
	
	public function uninstall() {
		return true;
	}
	
	public function version () {
		return array('0.5');
	}
	public function requires($v) {
		return array(	array('name'=>'Base/Lang','version'=>0),
					array('name'=>'Utils/GenericBrowser','version'=>0));
	}

	public static function simple_setup() {
        return false;
	}
}

?>
