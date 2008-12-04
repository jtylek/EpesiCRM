<?php
/**
 * Backup class.
 * 
 * This class provides functions for administrating the backup files.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage backup
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_BackupCommon extends ModuleCommon {
	public static function admin_access() {
		return Base_AclCommon::i_am_sa();
	}
	

	public static function admin_caption() {
		return "Backups";
	}
}

?>