<?php
/**
 * Shows who is logged to epesi.
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license EPL
 * @version 0.1
 * @package CRM-whoisonline
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_WhoIsOnlineCommon extends ModuleCommon {
	public static function applet_caption() {
		return "CRM - Who is online";
	}

	public static function applet_info() {
		return "Shows online users with name and surname";
	}
	
	public static function body_access() {
		return Acl::is_user();
	}
}
?>