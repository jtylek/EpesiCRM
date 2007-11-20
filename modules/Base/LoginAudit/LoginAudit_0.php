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

	public function admin() {
		$this->lang = & $this->init_module('Base/Lang');
		print ('Login Audit');
		
	/*	$edit = $this->get_unique_href_variable('edit_user');
		if($edit!=null) {
			$this->edit_user_form($edit);
			return;
		}
		
		$gb = & $this->init_module('Utils/GenericBrowser',null,'user_list');
		
		$gb->set_table_columns(array(
						array('name'=>$this->lang->t('Login'), 'order'=>'u.login', 'width'=>30), 
						array('name'=>$this->lang->t('Mail'), 'order'=>'p.mail', 'width'=>40), 
						array('name'=>$this->lang->t('Access'),'width'=>30)));

		$query = 'SELECT u.login, p.mail, u.id FROM user_login u INNER JOIN user_password p on p.user_login_id=u.id';
		$query_qty = 'SELECT count(u.id) FROM user_login u INNER JOIN user_password p on p.user_login_id=u.id';
    	
		$ret = $gb->query_order_limit($query, $query_qty);
		if($ret)
			while(($row=$ret->FetchRow())) {
				$uid = Base_AclCommon::get_acl_user_id($row['login']);
				if(!$uid) continue;
				$groups = Base_AclCommon::get_user_groups_names($uid);
				if($groups===false) continue; //skip if you don't have privileges
				
				$gb->add_row('<a '.$this->create_unique_href(array('edit_user'=>$row['id'])).'>'.$row['login'].'</a>',$row['mail'],$groups);
			}
		
		$this->display_module($gb);
			
		
//		print('<a '.$this->create_unique_href(array('edit_user'=>-1)).'>'.$this->lang->t('Add new user').'</a>');
		Base_ActionBarCommon::add('add','New user',$this->create_unique_href(array('edit_user'=>-1)));
	*/
	}
	
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