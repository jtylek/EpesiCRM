<?php
/**
 * BaseInstall class.
 *
 * This class initialization data for Base pack of module.
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage baseinstall
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class BaseInstall extends ModuleInstall {
	public function install() {
		return true;
	}

	public function uninstall() {
		return true;
	}

	public static function info() {
		return array('Author'=>'<a href="mailto:pbukowski@telaxus.com">Paul Bukowski</a> and <a href="mailto:abisaga@telaxus.com">Arkadiusz Bisaga</a> (<a href="http://www.telaxus.com">Telaxus LLC</a>)', 'License'=>'TL', 'Description'=>'Base EPESI modules pack');
	}

	public static function simple_setup() {
		return __('EPESI Core');
	}

	public function version() {
		return array('1.0');
	}

	public function requires($v) {
		return array(
		    array('name'=>'Base/Admin','version'=>0),
		    array('name'=>'Base/About','version'=>0),
		    array('name'=>'Base/ActionBar','version'=>0),
		    array('name'=>'Base/Dashboard','version'=>0),
		    array('name'=>'Base/Setup','version'=>0),
		    array('name'=>'Base/EpesiStore','version'=>0),
		    array('name'=>'Base/Lang/Administrator','version'=>0),
		    array('name'=>'Base/Mail/ContactUs','version'=>0),
		    array('name'=>'Base/Menu/QuickAccess','version'=>0),
		    array('name'=>'Base/MainModuleIndicator','version'=>0),
		    array('name'=>'Base/Menu','version'=>0),
		    array('name'=>'Base/RegionalSettings','version'=>0),
		    array('name'=>'Base/StatusBar','version'=>0),
		    array('name'=>'Base/Search','version'=>0),
		    array('name'=>'Base/HomePage','version'=>0),
		    array('name'=>'Base/Theme/Administrator','version'=>0),
		    array('name'=>'Base/User/Administrator','version'=>0));
	}
}

?>
