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
						array('name'=>$this->lang->t('<b>Login</b> -> User Name'),'order'=>'b.user_login_id','width'=>20), 
						array('name'=>$this->lang->t('Start'), 'order'=>'b.start_time', 'width'=>15), 
						array('name'=>$this->lang->t('End'),'order'=>'b.end_time','width'=>15),
                        array('name'=>$this->lang->t('Duration'),'width'=>5),
                        array('name'=>$this->lang->t('IP Address'),'order'=>'b.ip_address','width'=>15),
                        array('name'=>$this->lang->t('Host Name'),'order'=>'b.host_name','width'=>35)));

        $gb->set_default_order(array($this->lang->t('End')=>'DESC'));

		$query = 'SELECT b.user_login_id, b.start_time, b.end_time, b.ip_address, b.host_name FROM base_login_audit b';
		$query_qty = 'SELECT count(b.id) FROM base_login_audit b';
        
		$ret = $gb->query_order_limit($query, $query_qty);
		
        if($ret)
			while(($row=$ret->FetchRow())) {
				$c = CRM_ContactsCommon::get_contact_by_user_id($row['user_login_id']);
                $ulogin = Base_UserCommon::get_user_login($row['user_login_id']);
                if($c) {
                        $uid = $c['First Name'].' '.$c['Last Name'];
                        }
                $offset=strtotime("1970-01-01 00:00:00");
                $sess_time=date("G:i:s",strtotime($row['end_time'])-strtotime($row['start_time'])+$offset);
                $gb->add_row('<b>'.$ulogin.'</b> -> '.$uid,$row['start_time'],$row['end_time'],$sess_time,$row['ip_address'],$row['host_name']);
			}
        		
		$this->display_module($gb);
        
        #Base_StatusBarCommon::Message('This is the test');
		
	}
	
	public function body() {
	}

    private function purge(){
        
        
    }
}

?>