<?php
/**
 * Shows who is logged to epesi.
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package CRM-whoisonline
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