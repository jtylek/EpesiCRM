<?php
/**
 * 
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Arkadiusz Bisaga <abisaga@telaxus.com>
 * @license SPL
 * @version 0.1
 * @package tests-report
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_ReportCommon extends ModuleCommon {
	public function menu() {
		return array('Tests'=>array('__submenu__'=>1, 'Reports - Companies'=>array()));	
	}

	public static function display_company($record, $nolink=false) {
		$def = Utils_RecordBrowserCommon::create_linked_label_r('company', 'Company Name', $record, $nolink);
		if (!$def) return '---';
		return $def;
	}
}

?>