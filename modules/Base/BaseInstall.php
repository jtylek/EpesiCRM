<?php
/**
 * BaseInstall class.
 * 
 * This class initialization data for Base pack of module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @licence SPL
 * @package epesi-base-extra
 * @subpackage base-installer
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class BaseInstall extends ModuleInstall {
	public static function install() {
		if(!Variable::set('default_module','Base_Box')) return false;			
		return true;
	}
	
	public static function uninstall() {
		return true;
	}
	
	public static function info() {
		return array('Author'=>'<a href="mailto:pbukowski@telaxus.com">Paul Bukowski</a> and <a href="mailto:abisaga@telaxus.com">Arkadiusz Bisaga</a> (<a href="http://www.telaxus.com">Telaxus LLC</a>)', 'Licence'=>'TL', 'Description'=>'Base EPESI modules pack');
	}
	
	public static function simple_setup() {
		return true;
	}
	
	public static function version() {
		return array('0.9.9');
	}

}

?>
