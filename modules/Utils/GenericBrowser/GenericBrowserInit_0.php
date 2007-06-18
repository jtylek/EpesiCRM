<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>, Kuba Slawinski <kslawinski@telaxus.com> and Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @licence SPL
 * @package epesi-utils
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_GenericBrowserInit_0 extends ModuleInit {

	public static function requires() {
		return array(
			array('name'=>'Base/Acl','version'=>0),
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Base/MaintenanceMode','version'=>0),
			array('name'=>'Base/User/Settings','version'=>0),
			array('name'=>'Base/Theme','version'=>0));
	}
	
	public static function provides() {
		return array();
	}

}

?>