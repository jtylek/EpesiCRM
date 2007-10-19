<?php
/**
 * Shows who is logged to epesi.
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package tools-whoisonline
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tools_WhoIsOnline extends Module {

	public function body() {
		DB::Execute('delete from tools_whoisonline_users where session_name not in (select session_name from session)');
		$ret = DB::Execute('SELECT ul.login FROM tools_whoisonline_users twu INNER JOIN user_login ul on ul.id=twu.user_login_id');
		$all = array();
		while($r = $ret->FetchRow())
			if($r['login']!=Acl::get_user()) $all[] = $r['login'];
			
		$th = $this->init_module('Base/Theme');
		$th->assign('users',$all);
		$th->display();
	}
	
	public function applet() {
		$this->body();
	}

}

?>