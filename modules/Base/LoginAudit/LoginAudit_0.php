<?php
/**
 * Provides login audit log
 * @author pbukowski@telaxus.com & jtylek@telaxus.com
 * @copyright pbukowski@telaxus.com & jtylek@telaxus.com
 * @license SPL
 * @version 0.1
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_LoginAudit extends Module {

	public function body() {
		#$all = 'Login audit: '.$_SESSION['base_login_audit'];
		$all = 'test';
		$th = $this->init_module('Base/Theme');
		$th->assign('users',$all);
		$th->assign('test_text','User: '.$_SESSION['base_login_audit_user']);
		$th->display();
	}
	
	public function applet() {
		$this->body();
	}

}

?>