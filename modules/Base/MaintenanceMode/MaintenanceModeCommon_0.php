<?php
/**
 * MaintenanceMode class.
 * 
 * This class provides admin interface with maintenance mode toggle.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license EPL
 * @package epesi-base-extra
 * @subpackage maintenance-mode
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_MaintenanceModeCommon extends ModuleCommon {
	public static function set_mode($x) {
		if(is_bool($x))
			$_SESSION['maintenance_mode']=$x;
		$_SESSION['maintenance_mode_changed']=2;
	}
	
	public static function get_mode() {
		return isset($_SESSION['maintenance_mode']) && $_SESSION['maintenance_mode'];
	}
	
	public static function get_changed() {
		return (isset($_SESSION['maintenance_mode_changed']) && $_SESSION['maintenance_mode_changed']>0)?true:false;
	}
}

if(isset($_SESSION['maintenance_mode_changed']) && $_SESSION['maintenance_mode_changed']>0)
	$_SESSION['maintenance_mode_changed']--;
?>
