<?php
/**
 * Shows who is logged to epesi.
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
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