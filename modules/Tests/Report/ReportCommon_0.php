<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2007, Janusz Tylek
 * @version 1.0
 * @license MIT
 * @package epesi-tests
 * @subpackage Report
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_ReportCommon extends ModuleCommon {
	// AdminLTE-only: Base_BootstrapIcons::resolve() looks this up for this
	// module's icon (sidebar menu, ActionBar launcher, admin panels, module
	// indicator, etc.) instead of a central map - see
	// modules/Base/Theme/bootstrap_icons.php.
	public static function bootstrap_icon() { return 'bi-file-earmark-bar-graph'; }

	public static function menu() {
		return array('Tests'=>array('__submenu__'=>1, 'Reports - Companies'=>array()));	
	}

	public static function display_company($record, $nolink=false) {
		$def = Utils_RecordBrowserCommon::create_linked_label_r('company', 'Company Name', $record, $nolink);
		if (!$def) return '---';
		return $def;
	}
}

?>