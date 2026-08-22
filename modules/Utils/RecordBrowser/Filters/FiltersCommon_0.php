<?php
/**
 * @author Georgi Hristov <ghristov@gmx.de>
 * @copyright Copyright &copy; 2016, Georgi Hristov
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage RecordBrowser-Filters
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_FiltersCommon extends ModuleCommon {
	// AdminLTE-only: Base_BootstrapIcons::resolve() looks this up for this
	// module's icon (sidebar menu, ActionBar launcher, admin panels, module
	// indicator, etc.) instead of a central map - see
	// modules/Base/Theme/bootstrap_icons.php.
	public static function bootstrap_icon() { return 'bi-funnel'; }

	public static function set_filters_visibility($tab, $visible) {
		Base_User_SettingsCommon::save(self::module_name(), $tab . '_show_filters', $visible);
	}
	
	public static function get_filters_visibility($tab) {
		$ret = Base_User_SettingsCommon::get(self::module_name(), $tab . '_show_filters');
			
		return $ret? true: false;
	}
	
	public static function user_settings(){
		return array(
			__('Browsing records')=>array(
				array('name'=>'save_filters','label'=>__('Save filters'),'type'=>'checkbox','default'=>0)
			)
		);
	}
}

?>