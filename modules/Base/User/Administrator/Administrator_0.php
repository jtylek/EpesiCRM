<?php
/**
 * User_Administrator class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-base-extra
 * @subpackage user-administrator
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_User_Administrator extends Module implements Base_AdminInterface {
	
	public function body() {
		$this->lang = & $this->init_module('Base/Lang');
		
		if(!Base_AclCommon::i_am_user()) {
			print($this->lang->t('First log in to the system.'));
			return;
		}

		$form = & $this->init_module('Libs/QuickForm',$this->lang->t('Saving settings'));
		
		//pass
		$form->addElement('header', null, $this->lang->t('Change password (leave empty if you prefer your current password)'));
		$form->addElement('password','new_pass',$this->lang->t('New password'));
		$form->addElement('password','new_pass_c',$this->lang->t('Confirm new password'));
		$form->addRule(array('new_pass', 'new_pass_c'), $this->lang->t('Your passwords don\'t match'), 'compare');
		$form->addRule('new_pass', $this->lang->t('Your password must be longer then 5 chars'), 'minlength', 5);
		
		//mail
		$form->addElement('header', null, $this->lang->t('Change e-mail'));
		$form->addElement('text','mail', $this->lang->t('New e-mail address'));
		$form->addRule('mail', $this->lang->t('Field required'), 'required');
		$form->addRule('mail', $this->lang->t('Not valid e-mail address'), 'email');
		
		//confirmation
		$form->addElement('header', null, $this->lang->t('Confirmation'));
		$form->addElement('password','old_pass', $this->lang->t('Old password'));
		$form->registerRule('check_old_pass', 'callback', 'check_old_pass', $this);
		$form->addRule('old_pass', $this->lang->t('Old password incorrect'), 'check_old_pass');
		$form->addRule('old_pass', $this->lang->t('Field required'), 'required');
		
		$form->addElement('submit', 'submit_button', $this->lang->ht('OK'));
		
		if($form->validate_with_message('Setting saved','Problem encountered')) {
			if($form->process(array(&$this, 'submit_user_preferences'))){
				location(array('box_main_module'=>'Base_User_Settings'));
			}
		} else {
			//defaults
			$ret = DB::Execute('SELECT p.mail FROM user_password p JOIN user_login u ON p.user_login_id=u.id WHERE u.login=%s', Acl::get_user());
			if(($row = $ret->FetchRow())) $form->setDefaults(array('mail'=>$row[0]));
			
			$form->display();		
		}
	}
	
	public function submit_user_preferences($data) {
		$new_pass = $data['new_pass'];
		$mail = $data['mail'];
		
		$user_id = Base_UserCommon::get_user_id(Acl::get_user());
		if($user_id===false) {
			print($this->lang->t('No such user! Your account has been deleted after you logged in...'));
			return false;
		}
		
		return Base_User_LoginCommon::change_user_preferences($user_id, $mail, $new_pass);
	}

	
	public function check_old_pass($pass) {
		return Base_User_LoginCommon::check_login(Acl::get_user(), $pass);
	}
	
	public function admin() {
		$this->lang = & $this->init_module('Base/Lang');
		
		$edit = $this->get_unique_href_variable('edit_user');
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
	}
	
	public function edit_user_form($edit_id) {
		$form = & $this->init_module('Libs/QuickForm',$this->lang->ht(($edit_id>=0)?'Applying changes':'Creating new user'));
		
		//create new user
		$form->addElement('header', null, $this->lang->t((($edit_id>=0)?'Edit user':'Create new user')));
		$form->addElement('hidden', $this->create_unique_key('edit_user'), $edit_id);
		
		$form->addElement('text', 'username', $this->lang->t('Username'));
		// require a username
		$form->addRule('username', $this->lang->t('A username must be between 3 and 32 chars'), 'rangelength', array(3,32));
		$form->addRule('username', $this->lang->t('Field required'), 'required');
		
		$form->addElement('text', 'mail', $this->lang->t('e-mail'));
		$form->addRule('mail', $this->lang->t('Field required'), 'required');
		$form->addRule('mail', $this->lang->t('This isn\'t valid e-mail address'), 'email');
		
		$sel = HTML_QuickForm::createElement('select', 'group', $this->lang->t('Groups'), Base_AclCommon::get_groups());
		$sel->setMultiple(true);
		$form->addElement($sel);
		
		if($edit_id<0)
			$form->addElement('header',null,$this->lang->t('If you leave this fields empty, password is generated.'));
		else
			$form->addElement('header',null,$this->lang->t('If you leave this fields empty, password is not changed.'));
		
		$form->addElement('password', 'pass', $this->lang->t('Password'));
		$form->addElement('password', 'pass_c', $this->lang->t('Confirm password'));
		$form->addRule(array('pass','pass_c'), $this->lang->t('Passwords don\'t match'), 'compare');
		$form->addRule('pass', $this->lang->t('Your password must be longer then 5 chars'), 'minlength', 5);
		
		if($edit_id>=0) {
			$form->addElement('select', 'active', $this->lang->t('Active'), array(1=>$this->lang->ht('Yes'), 0=>$this->lang->ht('No')));
		
			//set defaults
			$ret = DB::Execute('SELECT u.login, p.mail, u.active FROM user_login u INNER JOIN user_password p ON (p.user_login_id=u.id) WHERE u.id=%d', $edit_id);
			$username = '';
			if($ret && ($row = $ret->FetchRow())) {
				$form->setDefaults(array('username'=>$row['login'], 'mail'=>$row['mail'], 'active'=>$row['active']));
				$form->freeze('username');
				$username = $row['login'];
			}
			
			$uid = Base_AclCommon::get_acl_user_id($username);
			if($uid === false) {
				print('invalid user');
				return;
			}
			$sel->setSelected(Base_AclCommon::get_user_groups($uid));
		
		} else {
			$form->registerRule('check_username', 'callback', 'check_username_free', 'Base_User_LoginCommon');
			$form->addRule('username', $this->lang->t('Username already taken'), 'check_username');
			$sel->setSelected(array(Base_AclCommon::get_group_id('User')));
		}
		
		$ok_b = HTML_QuickForm::createElement('submit', 'submit_button', $this->lang->ht('OK'));
		$cancel_b = HTML_QuickForm::createElement('button', 'cancel_button', $this->lang->ht('Cancel'), 'onClick="parent.location=\''.$this->create_href().'\'"');
		$form->addGroup(array($ok_b, $cancel_b));
		
		
		if($form->validate()) {
			if($form->process(array(&$this, 'submit_edit_user_form')))
				location(array());
		} else $form->display();
	}
	
	public function submit_edit_user_form($data) {
		$mail = $data['mail'];
		$username = $data['username'];
		$pass = $data['pass'];
		$edit_id = $this->get_unique_href_variable('edit_user');
		
		if($edit_id<0) {
			if(!Base_User_LoginCommon::add_user($username, $mail, $pass)) {
				return false;
			}
		
			$groups_new = $data['group'];
			if(!Base_AclCommon::change_privileges($username, $groups_new)) {
				print($this->lang->t('Unable to update account data (groups).'));
				return false;
			}
		} else {
			$user_id = Base_UserCommon::get_user_id($username);
			if($user_id === false || $user_id!=$edit_id) {
				print($this->lang->t('Username doesn\'t match edited user.'));
				return false;
			}
			
			if(Base_User_LoginCommon::change_user_preferences($user_id, $mail, $pass)===false) {
				print($this->lang->t('Unable to update account data (password and mail).'));
				return false;
			}
			
			if(!Base_UserCommon::change_active_state($user_id, $data['active'])) {
				print($this->lang->t('Unable to update account data (active).'));
				return false;
			}
			
			$groups_new = $data['group'];
			if(!Base_AclCommon::change_privileges($username, $groups_new)) {
				print($this->lang->t('Unable to update account data (groups).'));
				return false;
			}
		}
		return true;
	}	

}
?>
