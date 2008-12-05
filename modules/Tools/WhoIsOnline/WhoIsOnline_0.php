<?php
/**
 * Shows who is logged to epesi.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-tools
 * @subpackage WhoIsOnline
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