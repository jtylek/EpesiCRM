<?php
/** 
 * @author Kuba Slawinski <kslawinski@telaxus.com> and Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC 
 * @version 0.9
 * @license SPL 
 * @package epesi-utils 
 * @subpackage tooltip
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_ShortcutInstall extends ModuleInstall {
	public function install() {
		return true;
	}
	
	public function uninstall() {
		return true;
	}
	
	public function version() {
		return array('1.0.0');
	}
	public function requires($v) {
		return array(
			array('name'=>'Base/User/Settings', 'version'=>0)
		    );
	}
}

?>
