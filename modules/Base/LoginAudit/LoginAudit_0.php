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
		$all = Tools_WhoIsOnlineCommon::get();
		//print_r ($all);
		foreach($all as &$x) {
			//print ('<br/> logged:'.$x)
		}
		$th = $this->init_module('Base/Theme');
		$th->assign('users',$all);
		$th->assign('test_text',print_r ($all));
		$th->display();
	}
	
	public function applet() {
		$this->body();
	}

}

?>