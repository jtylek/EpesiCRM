<?php
/**
 * Shows who is logged to epesi.
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage whoisonline
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_WhoIsOnline extends Module {

	public function body() {
		$all = Tools_WhoIsOnlineCommon::get();
		foreach($all as &$x) {
			$c = CRM_ContactsCommon::get_contact_by_user_id(Base_UserCommon::get_user_id($x));
			if($c)
				$x = $c['first_name'].' '.$c['last_name'];
		}
		$th = $this->init_module('Base/Theme');
		$th->assign('users',$all);
		$th->display();
	}
	
	public function applet() {
		$this->body();
	}

}

?>