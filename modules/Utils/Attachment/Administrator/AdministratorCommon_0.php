<?php
/**
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage attachment-administrator
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_Attachment_AdministratorCommon extends ModuleCommon {
	public static function admin_access() {
		return Base_AclCommon::i_am_sa();
	}
	

	public static function admin_caption() {
		return "Notes & Attachments";
	}

}

?>