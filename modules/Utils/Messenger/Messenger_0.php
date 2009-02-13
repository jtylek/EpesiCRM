<?php
/**
 * Popup message to the user
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license MIT
 * @version 1.0
 * @package epesi-Utils
 * @subpackage Messenger
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_Messenger extends Module {
	private $mid;
	private $callback_method;
	private $callback_args;
	private $users;
	private $def_date;
	private $real_id;

	public function pop_box0() {
		$x = ModuleManager::get_instance('/Base_Box|0');
		if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$x->pop_main();
	}

	public function push_box0($func,$args,$const_args) {
		$x = ModuleManager::get_instance('/Base_Box|0');
		if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$x->push_main('Utils/Messenger',$func,$args,$const_args);
	}

	public function construct($id=null,$callback_method=null,$callback_args=null,$def_date=null,$users=null) {
		if(!isset($id))
			//applet mode
			return;
		if(!isset($callback_method))
			trigger_error('Callback not set.',E_USER_ERROR);
			
		$this->mid = md5($id);
		$this->real_id = $id;
		$this->users = (isset($users) && (is_numeric($users) || (is_array($users) && !empty($users))))?$users:Acl::get_user();
		$this->callback_method = $callback_method;
		$this->callback_args = isset($callback_args)?((is_array($callback_args))?$callback_args:array($callback_args)):array();
		$this->def_date = ($def_date!=null)?$def_date:time();
	}
	
	public function edit($row) {
		if($this->is_back())
			$this->pop_box0();

		$f = &$this->init_module('Libs/QuickForm');
		
		if($row) {
			$a = Base_RegionalSettingsCommon::time2reg($row['alert_on'],true,true,true,false);
			$f->setDefaults(array_merge($row,array('alert_date'=>$a,'alert_time'=>$a)));
		} else {
			$tt = $this->def_date;
			$tt = $tt-$tt%300;
			$f->setDefaults(array('alert_date'=>$tt,'alert_time'=>$tt));
		}

		$f->addElement('textarea', 'message', $this->t('Message'));
		$f->addElement('datepicker', 'alert_date', $this->t('Alert date'));
		$lang_code = Base_LangCommon::get_lang_code();
		$time_format = Base_RegionalSettingsCommon::time_12h()?'h:i a':'H:i';
		$f->addElement('date', 'alert_time', $this->t('Alert time'), array('format'=>$time_format, 'optionIncrement'  => array('i' => 5), 'language'=>$lang_code));
		
		if(is_array($this->users)) {
			foreach($this->users as $k=>$r) {
				if(!Base_User_SettingsCommon::get($this->get_type(),'allow_other',$k) && Acl::get_user()!=$k)
					unset($this->users[$k]);
			}
			$f->addElement('multiselect', 'users', $this->t('Assigned users'), $this->users);
			$f->addRule('users', $this->t('At least one user must be assigned to an alarm.'), 'required');
			$f->setDefaults(array('users'=>array_keys($this->users)));
		}

		if($f->validate()) {
			$ret = $f->exportValues();
			if($row)
				$ret = array_merge($row,$ret);
			if(Base_RegionalSettingsCommon::time_12h())
				$ret['alert_on'] = strtotime($ret['alert_date'])+($ret['alert_time']['h']%12)*3600+(($ret['alert_time']['a']=='pm')?(3600*12):0)+$ret['alert_time']['i']*60;
			else
				$ret['alert_on'] = strtotime($ret['alert_date'])+$ret['alert_time']['H']*3600+$ret['alert_time']['i']*60;
			$ret['alert_on'] = Base_RegionalSettingsCommon::reg2time(date('Y-m-d H:i:s',$ret['alert_on']));
			if($row) {
				DB::Execute('UPDATE utils_messenger_message SET message=%s,alert_on=%T WHERE page_id=\''.$this->mid.'\' AND id=%d',array($ret['message'],$ret['alert_on'],$row['id']));
				$id = $row['id'];
				DB::Execute('DELETE FROM utils_messenger_users WHERE message_id=%d',array($id));
			} else {
				DB::Execute('INSERT INTO utils_messenger_message(page_id,parent_module,message,callback_method,callback_args,created_on,created_by,alert_on) VALUES(%s,%s,%s,%s,%s,%T,%d,%T)',array($this->mid,$this->parent->get_type(),$ret['message'],serialize($this->callback_method),serialize($this->callback_args),time(),Acl::get_user(),$ret['alert_on']));
				$id = DB::Insert_ID('utils_messenger_message','id');
			}
			if(is_array($this->users)) {
				foreach($ret['users'] as $r)
					DB::Execute('INSERT INTO utils_messenger_users(message_id,user_login_id) VALUES (%d,%d)',array($id,$r));
			} else
				DB::Execute('INSERT INTO utils_messenger_users(message_id,user_login_id) VALUES (%d,%d)',array($id,$this->users));
			$this->pop_box0();
		}
		
		Base_ActionBarCommon::add('save','Save',$f->get_submit_form_href());
		Base_ActionBarCommon::add('back','Back',$this->create_back_href());
		$f->display();
	}
	
	public function delete_entry($id) {
		DB::Execute('DELETE FROM utils_messenger_users WHERE message_id=%d',array($id));
		DB::Execute('DELETE FROM utils_messenger_message WHERE page_id=%s AND id=%d',array($this->mid,$id));
		location(array());
	}

	public function body() {
		$gb = & $this->init_module('Utils/GenericBrowser',null,'messages');
		$gb->set_table_columns(array(
			array('name'=>$this->t('Alert on'), 'width'=>20),
			array('name'=>$this->t('Message'), 'width'=>50),
			array('name'=>$this->t('Users'), 'width'=>30)
				));
		$data = DB::GetAll('SELECT * FROM utils_messenger_message WHERE page_id=\''.$this->mid.'\'');
		foreach($data as & $row) {
			$row['users'] = DB::GetCol('SELECT user_login_id FROM utils_messenger_users WHERE message_id=\''.$row['id'].'\'');
			$r = & $gb->get_new_row();
			if(is_array($this->users)) {
				$us = '';
				foreach($row['users'] as $rr)
					if(isset($this->users[$rr])) 
						$us .= $this->users[$rr].'<br>';
			} else
				$us = Base_UserCommon::get_user_login($this->users);
				
			$r->add_data(Base_RegionalSettingsCommon::time2reg($row['alert_on']),$row['message'],$us);
			$r->add_action($this->create_callback_href(array($this,'push_box0'),array('edit',array($row),array($this->real_id,$this->callback_method,$this->callback_args,$this->def_date,$this->users))),'Edit');
			$r->add_action($this->create_confirm_callback_href($this->ht('Are you sure?'),array($this,'delete_entry'),$row['id']),'Delete');
		}
		$this->display_module($gb);
		
		Base_ActionBarCommon::add('add','New alert',$this->create_callback_href(array($this,'push_box0'),array('edit',array(false),array($this->real_id,$this->callback_method,$this->callback_args,$this->def_date,$this->users))));	
	}

	public function purge_old() {
		DB::Execute('DELETE FROM utils_messenger_users WHERE user_login_id=%d AND done=1',array(Acl::get_user()));
		$this->orphan();
	}
	
	private function orphan() {
		DB::Execute('DELETE FROM utils_messenger_message WHERE (SELECT 1 FROM utils_messenger_users u WHERE u.message_id=id) is null');
	}
	
	public function delete_user_entry($id) {
		DB::Execute('DELETE FROM utils_messenger_users WHERE message_id=%d AND user_login_id=%d',array($id,Acl::get_user()));
		$this->orphan();
	}

	public function browse() {
		$gb = $this->init_module('Utils/GenericBrowser', null, 'agenda');
		$columns = array(
			array('name'=>$this->t('Done'), 'order'=>'done', 'width'=>5),
			array('name'=>$this->t('Start'), 'order'=>'alert_on', 'width'=>15),
			array('name'=>$this->t('Info'), 'width'=>80)
		);
		$gb->set_table_columns($columns);

		$gb->set_default_order(array($this->t('Start')=>'ASC'));

		$t = time();
		$ret = DB::Execute('SELECT u.done,m.* FROM utils_messenger_message m INNER JOIN utils_messenger_users u ON u.message_id=m.id WHERE u.user_login_id=%d'.$gb->get_query_order(),array(Acl::get_user()));

		while($row = $ret->FetchRow()) {
			$info = call_user_func_array(unserialize($row['callback_method']),unserialize($row['callback_args']));
			$info = str_replace("\n",'<br>',$info);
			$r = & $gb->get_new_row();
			$r->add_data('<span class="'.($row['done']?'checkbox_on':'checkbox_off').'" />',Base_RegionalSettingsCommon::time2reg($row['alert_on']),$info.'<br>'.($row['message']?$this->t("Alarm comment: %s",array($row['message'])):''));
			$r->add_action($this->create_confirm_callback_href($this->ht('Are you sure?'),array($this,'delete_user_entry'),$row['id']),'Delete');
		}

		$this->display_module($gb);
		
		Base_ActionBarCommon::add('delete','Purge old alerts',$this->create_confirm_callback_href($this->t('Purge all done alerts?'),array($this,'purge_old')));	
	}

	/////////////////////////////////////////////////////////////
	public function applet() {

		$gb = $this->init_module('Utils/GenericBrowser', null, 'agenda');
		$columns = array(
			array('name'=>$this->t('Done'), 'order'=>'done', 'width'=>5),
			array('name'=>$this->t('Start'), 'order'=>'alert_on', 'width'=>15),
			array('name'=>$this->t('Info'), 'width'=>80)
		);
		$gb->set_table_columns($columns);

		$gb->set_default_order(array($this->t('Start')=>'ASC'));

		$t = time();
		$ret = DB::Execute('(SELECT u.done,m.* FROM utils_messenger_message m INNER JOIN utils_messenger_users u ON u.message_id=m.id WHERE u.user_login_id=%d AND u.done=0 AND m.alert_on<%T)'.
					' UNION '.
				'(SELECT u.done,m.* FROM utils_messenger_message m INNER JOIN utils_messenger_users u ON u.message_id=m.id WHERE u.user_login_id=%d AND m.alert_on<%T AND u.done_on>=%T-INTERVAL 1 hour AND u.done=1 ORDER BY m.alert_on DESC LIMIT 3)'.
					' UNION '.
				'(SELECT 0 as done,m.* FROM utils_messenger_message m INNER JOIN utils_messenger_users u ON u.message_id=m.id WHERE u.user_login_id=%d AND m.alert_on>=%T ORDER BY m.alert_on ASC LIMIT 5)'.$gb->get_query_order(),array(Acl::get_user(),$t,Acl::get_user(),$t,$t,Acl::get_user(),$t));

		while($row = $ret->FetchRow()) {
			$info = call_user_func_array(unserialize($row['callback_method']),unserialize($row['callback_args']));
			$info = str_replace("\n",'<br>',$info);
			$gb->add_row('<span class="'.($row['done']?'checkbox_on':'checkbox_off').'" />',Base_RegionalSettingsCommon::time2reg($row['alert_on']),$info.'<br>'.($row['message']?$this->t("Alarm comment: %s",array($row['message'])):''));
		}

		$this->display_module($gb);
	}
}

?>