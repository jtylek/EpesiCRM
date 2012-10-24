<?php
/**
 * Provides login audit log
 * @author Paul Bukowski <pbukowski@telaxus.com> & Janusz Tylek <jtylek@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage loginaudit
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_LoginAudit extends Module {

	public function applet() {
		$query = 'SELECT b.user_login_id, b.start_time, b.end_time, b.ip_address, b.host_name FROM base_login_audit b WHERE b.user_login_id='.Acl::get_user().' ORDER BY b.start_time DESC';
		
		$ret = DB::SelectLimit($query, 1, 1);
		if($row = $ret->FetchRow()) {
			$ok1 = $row['ip_address'] == $_SERVER['REMOTE_ADDR']; 
			$ok2 = DB::GetOne('SELECT 1 FROM base_login_audit b WHERE (SELECT MIN(b2.start_time) FROM base_login_audit b2 WHERE b2.ip_address=%s)<b.start_time AND (SELECT MAX(b3.start_time) FROM base_login_audit b3 WHERE b3.ip_address=%s)>b.start_time AND b.ip_address!=%s',array($row['ip_address'],$row['ip_address'],$row['ip_address']));
			$ok = $ok1 || $ok2;
			print(($ok?'<div style="padding:7px;">':'<div style="padding:7px;background-color: red; color:white; font-weight:bold;">').__('On: %s',array($row['start_time'])).'<br />'.__('Host name: %s',array($row['host_name'])).'<br />'.__('IP address: %s',array( $row['ip_address'])).'</div>');
		}
	}
	
    public function admin() {
		if($this->is_back()) {
			if($this->parent->get_type()=='Base_Admin')
				$this->parent->reset();
			else
				location(array());
			return;
		}
		Base_ActionBarCommon::add('back',__('Back'),$this->create_back_href());

        $user = $this->get_module_variable('filter_user','');
        $form = $this->init_module('Libs/QuickForm',null,'filter');
        $form->setDefaults(array('users'=>$user));
		$count = DB::GetOne('SELECT COUNT(*) FROM user_login');
		if ($count > Base_User_SettingsCommon::get('Utils_RecordBrowser','enable_autocomplete')) {
			$f_callback = array('CRM_LoginAuditCommon', 'user_label');
			$form->addElement('autoselect', 'users', __('Select user'), array(), array(array('CRM_LoginAuditCommon','user_suggestbox'), array($f_callback)), $f_callback, array('onChange'=>$form->get_submit_form_js(), 'style'=>'width:200px'));
		} else {
			$ret = DB::Execute('SELECT id, active FROM user_login ORDER BY active DESC, login ASC');
			$el = $form->addElement('select','users',__('Select user'), array(), array('onChange'=>$form->get_submit_form_js(), 'style'=>'width:200px'));
			$el->addOption(__('All'),'');
			$contacts_raw = CRM_ContactsCommon::get_contacts(array('!login'=>''));
			$contacts = array();
			foreach ($contacts_raw as $c) {
				$contacts[$c['login']] = $c;
			}
			$active = array();
			$inactive = array();
			while($row = $ret->FetchRow()) {
				$label = '['.Base_UserCommon::get_user_login($row['id']).']';
				if (isset($contacts[$row['id']])) {
					$label = CRM_ContactsCommon::contact_format_no_company($contacts[$row['id']], true).' '.$label;
				}
				if ($row['active'])
					$active[$row['id']] = $label;
				else
					$inactive[$row['id']] = $label;
			}
			asort($active);
			asort($inactive);
			foreach ($active as $id=>$label)
				$el->addOption($label,$id);
			foreach ($inactive as $id=>$label)
				$el->addOption($label,$id,array('style'=>'background-color: lightgray;'));
		}
		$user = $form->exportValue('users');
        $form->display_as_row();
        $this->set_module_variable('filter_user',$user);

		$gb = $this->init_module('Utils/GenericBrowser',null,'login_audit');

		$gb->set_table_columns(array(
						array('name'=>__('<b>Login</b> [uid] -> User Name'),'order'=>'b.user_login_id','width'=>20),
						array('name'=>__('Start'), 'order'=>'b.start_time', 'width'=>15),
						array('name'=>__('End'),'order'=>'b.end_time','width'=>15),
                        array('name'=>__('Duration'),'width'=>10),
                        array('name'=>__('IP Address'),'order'=>'b.ip_address','width'=>10),
                        array('name'=>__('Host Name'),'order'=>'b.host_name','width'=>30)));

        $gb->set_default_order(array(__('End')=>'DESC'));

		if($user>0){
            $query = 'SELECT b.user_login_id, b.start_time, b.end_time, b.ip_address, b.host_name FROM base_login_audit b WHERE b.user_login_id='.$user;
            $query_qty = 'SELECT count(b.id) FROM base_login_audit b WHERE b.user_login_id='.$user;
        } else {
            $query = 'SELECT b.user_login_id, b.start_time, b.end_time, b.ip_address, b.host_name FROM base_login_audit b';
            $query_qty = 'SELECT count(b.id) FROM base_login_audit b';
        }

		$ret = $gb->query_order_limit($query, $query_qty);

        if($ret)
			while(($row=$ret->FetchRow())) {
				$c = CRM_ContactsCommon::get_contact_by_user_id($row['user_login_id']);
                $ulogin = Base_UserCommon::get_user_login($row['user_login_id']);
                $uid = __('Contact not set');
                if($c) {
                        $uid = $c['first_name'].' '.$c['last_name'];
                        }
                $offset=strtotime("1970-01-01 00:00:00");
                $sess_time=date("G:i:s",strtotime($row['end_time'])-strtotime($row['start_time'])+$offset);
                $gb->add_row('<b>'.$ulogin.' ['.$row['user_login_id'].']</b> -> '.$uid,$row['start_time'],$row['end_time'],$sess_time,$row['ip_address'],$row['host_name']);
			}

		$this->display_module($gb);

	if(!DEMO_MODE)
	        Base_ActionBarCommon::add('settings',__('Maintenance'),$this->create_callback_href(array($this, 'purge_log')));
        return true;
	}

    public function purge_log(){

        # Return to main body
        if($this->is_back()) return false;

        $form = $this->init_module('Libs/QuickForm',null,'purge_date');

        $form->addElement('header',null,__('Audit Log Maintenance'));
        $form -> addElement('html','<tr><td colspan=2><br />'.__('Purge log with records older than specified number of days:').'</td></tr>');
        $form->addElement('select','purge_date',__('Select number of days'), array(30=>30,90=>90,365=>365,1=>'All'));
		$purge_date = $form->exportValue('purge_date');
        $form->display();

        if (!$purge_date==null){
            $del_date=strtotime("-".$purge_date." days",time());
            $sql_date=date('Y-m-d H:i:s',$del_date);
            # print ('<br/> date: '.$sql_date.'<br />');
            if ($purge_date==1) {
                $sql_query = 'Delete FROM base_login_audit';
                $ret = DB::Execute($sql_query);
                print (__('Entire log was purged!'));
            } else {
                $sql_query = 'Delete FROM base_login_audit where start_time < \''.$sql_date.'\'';
                $ret = DB::Execute($sql_query);
                print (__('Records older than %s days (%s) were purged.', array($purge_date, date('Y-m-d',$del_date))));
            }
        }

        Base_ActionBarCommon::add('back',__('Back'),$this->create_back_href());
        Base_ActionBarCommon::add('delete',__('Purge Log File'),'href="javascript:void(0)" onClick="if(confirm(\''.Epesi::escapeJS(__('Log will be purged!')).'\')){'.$form->get_submit_form_js().'}"');
        return true;
    }
}

?>
