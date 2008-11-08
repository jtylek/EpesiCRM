<?php
/**
 * Shows who is logged to epesi.
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license EPL
 * @version 0.1
 * @package tools-whoisonline
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tools_WhoIsOnline extends Module {

	public function body() {
		$all = Tools_WhoIsOnlineCommon::get();
		$th = $this->init_module('Base/Theme');
		$th->assign('users',$all);
		$th->display();
	}
	
	public function applet() {
		$this->body();
	}

}

?>