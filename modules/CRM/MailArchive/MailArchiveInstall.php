<?php
/**
 * Mail archive applet etc.
 * @author pbukowski@telaxus.com
 * @copyright Telaxus LLC
 * @license MIT
 * @version 0.1
 * @package epesi-CRM
 * @subpackage MailArchive
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_MailArchiveInstall extends ModuleInstall {

	public function install() {
		return true;
	}

	public function uninstall() {
		return true;
	}

	public function version() {
		return array("0.1");
	}

	public function requires($v) {
		return array(
			array('name'=>'CRM/Mail','version'=>0));
	}

	public static function info() {
		return array(
			'Description'=>'Mail archive applet etc.',
			'Author'=>'pbukowski@telaxus.com',
			'License'=>'MIT');
	}

	public static function simple_setup() {
        return 'CRM';
    }

}

?>
