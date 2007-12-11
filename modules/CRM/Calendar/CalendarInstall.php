<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_CalendarInstall extends ModuleInstall {
	public function install() {
		$ret = true;
		return $ret;
	}

	public function uninstall() {
		$ret = true;

		return $ret;
	}

	public function provides($v) {
		return array();
	}

	public function info() {
		return array('Author'=>'<a href="mailto:kslawinski@telaxus.com">Kuba Sławiński</a> (<a href="http://www.telaxus.com">Telaxus LLC</a>)', 'Licence'=>'TL', 'Description'=>'Simple calendar and organiser.');
	}

	public function simple_setup() {
		return true;
	}
	public function requires($v) {
		return array(
		);
	}
	public function version() {
		return array('0.1.0');
	}
}

?>
