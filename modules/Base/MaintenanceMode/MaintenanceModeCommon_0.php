<?php
/**
 * MaintenanceMode class.
 * 
 * This class provides admin interface with maintenance mode toggle.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides admin interface with maintenance mode toggle.
 * @package epesi-base-extra
 * @subpackage maintenance-mode
 */
class Base_MaintenanceModeCommon {
	public static function set_mode($x) {
		if(is_bool($x))
			$_SESSION['maintenance_mode']=$x;
		$_SESSION['maintenance_mode_changed']=2;
	}
	
	public static function get_mode() {
		return $_SESSION['maintenance_mode'];
	}
	
	public static function get_changed() {
		return ($_SESSION['maintenance_mode_changed']>0)?true:false;
	}
}

if($_SESSION['maintenance_mode_changed']>0)
	$_SESSION['maintenance_mode_changed']--;
?>
