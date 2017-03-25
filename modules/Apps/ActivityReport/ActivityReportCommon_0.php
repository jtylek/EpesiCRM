<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-apps
 * @subpackage activityreport
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_ActivityReportCommon extends ModuleCommon {
	public static function menu(){
	    if (self::has_access_to_report())
    		return array(_M('Reports')=>array_merge(array('__submenu__'=>1,'__icon__'=>'bar-chart',_M('User Activity Report')=>array())));
	}

	public static function contact_addon_label($r)
	{
		if (self::has_access_to_report($r)) {
			return array('label' => __('Journal'), 'show' => true);
		}
		return array('show' => false);
	}

	public static function has_access_to_report($contact_record = null)
	{
		$has_permission = Base_AclCommon::check_permission('View Activity Report');
		if ($contact_record === null && $has_permission) {
			return true;
		}
		if (isset($contact_record['login']) && $contact_record['login']) {
			if ($has_permission) {
				return true;
			}
			$id = (isset($contact_record['id']) ? $contact_record['id'] : false);
			if (!$id) {
				return false;
			}
			$my_record = CRM_ContactsCommon::get_my_record();
			return $id == $my_record['id'];
		}
	}

}

?>
