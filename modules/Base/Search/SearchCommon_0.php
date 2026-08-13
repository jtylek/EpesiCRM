<?php
/**
 * Search class.
 * 
 * Provides for search functionality in a module. 
 * 
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage search
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_SearchCommon extends ModuleCommon {
	// AdminLTE-only: Base_BootstrapIcons::resolve() looks this up for this
	// module's icon (sidebar menu, ActionBar launcher, admin panels, module
	// indicator, etc.) instead of a central map - see
	// modules/Base/Theme/bootstrap_icons.php.
	public static function bootstrap_icon() { return 'bi-search'; }

	public static function menu() {
		if (Base_AclCommon::check_permission('Search'))
			return array(_M('Search')=>array());
		return array();
	}

    public static function get_recordset_limit_records() {
        return 101;
    }
}
?>