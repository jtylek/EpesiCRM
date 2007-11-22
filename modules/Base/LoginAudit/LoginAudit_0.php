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
				
		$gb = & $this->init_module('Utils/GenericBrowser',null,'login_audit');
		
		$gb->set_table_columns(array(
						array('name'=>$this->lang->t('Login'), 'order'=>'b.user_login_id', 'width'=>30), 
						array('name'=>$this->lang->t('Start'), 'order'=>'b.start_time', 'width'=>40), 
						array('name'=>$this->lang->t('End'),'width'=>30),
                        array('name'=>$this->lang->t('IP Address'),'width'=>40),
                        array('name'=>$this->lang->t('Host Name'),'width'=>40)));

		$query = 'SELECT b.user_login_id, b.start_time, b.end_time, b.ip_address, b.host_name FROM base_login_audit b';
		$query_qty = 'SELECT count(b.id) FROM base_login_audit b';
    	
		$ret = $gb->query_order_limit($query, $query_qty);
		
        if($ret)
			while(($row=$ret->FetchRow())) {
				# $uid = Base_AclCommon::get_acl_user_id($row['login']);
				# if(!$uid) continue;
				# $groups = Base_AclCommon::get_user_groups_names($uid);
				# if($groups===false) continue; //skip if you don't have privileges
				
				$gb->add_row($row['user_login_id'],$row['start_time'],$row['end_time'],$row['ip_address'],$row['host_name']);
			}
        		
		$this->display_module($gb);
        
        #Base_StatusBarCommon::Message('This is the test');
		
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