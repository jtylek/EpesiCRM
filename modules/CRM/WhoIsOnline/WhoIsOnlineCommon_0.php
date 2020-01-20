<?php
/**
 * Shows who is logged to epesi.
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-crm
 * @subpackage whoisonline
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_WhoIsOnlineCommon extends ModuleCommon {
	public static function applet_caption() {
		return __('Who is online');
	}

	public static function applet_info() {
		return __('Shows online users with name and surname');
	}
	
	public static function body_access() {
		return Acl::is_user();
	}
}
?>