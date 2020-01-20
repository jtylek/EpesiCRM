<?php
/**
 * Activities history for Company and Contacts
 * @author Arkadiusz Bisaga, Janusz Tylek
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-crm
 * @subpackage contacts-activities
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Contacts_ActivitiesCommon extends ModuleCommon {
	public static function contact_activities_access() {
		return Utils_RecordBrowserCommon::get_access('contact','browse');
	}

}

?>