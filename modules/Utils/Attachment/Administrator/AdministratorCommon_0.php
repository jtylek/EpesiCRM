<?php
/**
 * 
 * @author shacky@poczta.fm
 * @copyright shacky@poczta.fm
 * @license EPL
 * @version 0.1
 * @package utils-attachment-administrator
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