<?php
/**
 * CRMCalendarInstall class.
 * 
 * This class provides initialization data for CRMHR module.
 * 
 * @author Kuba Sławiński <ruud@o2.pl>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-extra
 */
defined("_VALID_ACCESS") || die();   

/**
 * This class provides initialization data for Test module.
 * @package tcms-extra
 * @subpackage test
 */
class CRM_Calendar_View_WeekInstall extends ModuleInstall {
	public function install() {
		Base_ThemeCommon::install_default_theme('CRM/Calendar/View/Week');
		return true;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('CRM/Calendar/View/Week');
		return true;
	}
	public function requires($v) {
		return array(
			array('name'=>'Utils/Tooltip', 'version'=>0)
		);
	}
}

?>
