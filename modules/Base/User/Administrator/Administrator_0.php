<?php
/**
 * User_Administrator class.
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage user-administrator
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_User_Administrator extends Module implements Base_AdminInterface {
	public function body() {
		if(!Base_AclCommon::i_am_user()) {
			print($this->t('First log in to the system.'));
			return;
		}

		$form = & $this->init_module('Libs/QuickForm',$this->t('Saving settings'));

		//pass
		$form->addElement('header', null, $this->t('Change password'));
		$form->addElement('html','<tr><td colspan=2>'.$this->t('Leave password boxes empty if you prefer your current password').'</td></tr>');
		$form->addElement('password','new_pass',$this->t('New password'));
		$form->addElement('password','new_pass_c',$this->t('Confirm new password'));
		$form->addRule(array('new_pass', 'new_pass_c'), $this->t('Your passwords don\'t match'), 'compare');
		$form->addRule('new_pass', $this->t('Your password must be longer then 5 chars'), 'minlength', 5);

		//mail
		$form->addElement('header', null, $this->t('Change e-mail'));
		$form->addElement('text','mail', $this->t('New e-mail address'));
		$form->addRule('mail', $this->t('Field required'), 'required');
		$form->addRule('mail', $this->t('Not valid e-mail address'), 'email',true);

		//confirmation
		$form->addElement('header', null, $this->t('Confirmation'));
		$form->addElement('password','old_pass', $this->t('Old password'));
		$form->registerRule('check_old_pass', 'callback', 'check_old_pass', $this);
		$form->addRule('old_pass', $this->t('Old password incorrect'), 'check_old_pass');
		$form->addRule('old_pass', $this->t('Field required'), 'required');

		Base_ActionBarCommon::add('back','Back',$this->create_main_href('Base_User_Settings'));
		Base_ActionBarCommon::add('save','Save',$form->get_submit_form_href());
		#$form->addElement('submit', 'submit_button', $this->ht('OK'));

		if($form->validate_with_message('Setting saved','Problem encountered')) {
			if($form->process(array(&$this, 'submit_user_preferences'))){
				Base_BoxCommon::location('Base_User_Settings');
			}
		} else {
			//defaults
			$ret = DB::Execute('SELECT p.mail FROM user_password p  WHERE p.user_login_id=%d', Acl::get_user());
			if(($row = $ret->FetchRow())) $form->setDefaults(array('mail'=>$row[0]));

			$form->display();
		}
	}

	public function caption() {
		return "My settings: user";
	}

	public function submit_user_preferences($data) {
		$new_pass = $data['new_pass'];
		$mail = $data['mail'];

		$user_id = Acl::get_user();
		if($user_id===null) {
			print($this->t('Not logged in!'));
			return false;
		}

		return Base_User_LoginCommon::change_user_preferences($user_id, $mail, $new_pass);
	}


	public function check_old_pass($pass) {
		return Base_User_LoginCommon::check_login(Base_UserCommon::get_my_user_login(), $pass);
	}

	public function admin() {
		$edit = $this->get_unique_href_variable('edit_user');
		if($edit!=null) {
			$this->edit_user_form($edit);
			return;
		}

		$gb = & $this->init_module('Utils/GenericBrowser',null,'user_list');

		$gb->set_table_columns(array(
						array('name'=>$this->t('Login'), 'order'=>'u.login', 'width'=>30),
						array('name'=>$this->t('Active'), 'order'=>'u.active', 'width'=>5),
						array('name'=>$this->t('Mail'), 'order'=>'p.mail', 'width'=>35),
						array('name'=>$this->t('Access'),'width'=>30)));

		$query = 'SELECT u.login, p.mail, u.id, u.active FROM user_login u INNER JOIN user_password p on p.user_login_id=u.id';
		$query_qty = 'SELECT count(u.id) FROM user_login u INNER JOIN user_password p on p.user_login_id=u.id';

		$ret = $gb->query_order_limit($query, $query_qty);
		
		$yes = '<span style="color:green;">'.$this->t('yes').'</span>';
		$no = '<span style="color:red;">'.$this->t('no').'</span>';
		if($ret)
			while(($row=$ret->FetchRow())) {
				$uid = Base_AclCommon::get_acl_user_id($row['id']);
				if(!$uid) continue;
				$groups = Base_AclCommon::get_user_groups_names($uid);
				if($groups===false) continue; //skip if you don't have privileges

				$gb->add_row('<a '.$this->create_unique_href(array('edit_user'=>$row['id'])).'>'.$row['login'].'</a>',$row['active']?$yes:$no,$row['mail'],$groups);
			}

		$this->display_module($gb);

		$qf = $this->init_module('Libs/QuickForm',null,'ban');
		$qf->addElement('select','bantime',$this->t('Ban time after 3 failed logins'),array(0=>$this->ht('disabled'),10=>$this->ht('10 seconds'),30=>$this->ht('30 seconds'),60=>$this->ht('1 minute'),180=>$this->ht('3 minutes'),300=>$this->ht('5 minutes'),900=>$this->ht('15 minutes'),1800=>$this->ht('30 minutes'),3600=>$this->ht('1 hour'),(3600*6)=>$this->ht('6 hours'),(3600*24)=>$this->ht('1 day')),array('onChange'=>$qf->get_submit_form_js()));
		$qf->setDefaults(array('bantime'=>Variable::get('host_ban_time')));
		if($qf->validate()) {
			Variable::set('host_ban_time',$qf->exportValue('bantime'));
		}
		$qf->display();
//		print('<a '.$this->create_unique_href(array('edit_user'=>-1)).'>'.$this->t('Add new user').'</a>');
		Base_ActionBarCommon::add('add','New user',$this->create_unique_href(array('edit_user'=>-1)));
	}

	public function edit_user_form($edit_id) {
		$form = & $this->init_module('Libs/QuickForm',$this->ht(($edit_id>=0)?'Applying changes':'Creating new user'));

		//create new user
		$form->addElement('header', null, $this->t((($edit_id>=0)?'Edit user':'Create new user')));
		$form->addElement('hidden', $this->create_unique_key('edit_user'), $edit_id);

		$form->addElement('text', 'username', $this->t('Username'));
		// require a username
		$form->addRule('username', $this->t('A username must be between 3 and 32 chars'), 'rangelength', array(3,32));
		$form->addRule('username', $this->t('Field required'), 'required');

		$form->addElement('text', 'mail', $this->t('e-mail'));
		$form->addRule('mail', $this->t('Field required'), 'required');
		$form->addRule('mail', $this->t('This isn\'t valid e-mail address'), 'email',true);

		$sel = HTML_QuickForm::createElement('select', 'group', $this->t('Groups'), Base_AclCommon::get_groups());
		$sel->setMultiple(true);
		$form->addElement($sel);

		if($edit_id<0)
			$form -> addElement('html','<tr><td colspan=2><b>'.$this->t('If you leave password fields empty<br />random password is automatically generated<br />and e-mailed to the user.').'</b></td></tr>');
			//$form->addElement('header',null,$this->t('If you leave this fields empty, password is generated.'));
		else
			$form -> addElement('html','<tr><td colspan=2><b>'.$this->t('If you leave password fields empty, password is not changed.').'</b></td></tr>');
			//$form->addElement('header',null,$this->t('If you leave this fields empty, password is not changed.'));

		$form->addElement('password', 'pass', $this->t('Password'));
		$form->addElement('password', 'pass_c', $this->t('Confirm password'));
		$form->addRule(array('pass','pass_c'), $this->t('Passwords don\'t match'), 'compare');
		$form->addRule('pass', $this->t('Your password must be longer then 5 chars'), 'minlength', 5);

		if($edit_id>=0) {
			$form->addElement('select', 'active', $this->t('Active'), array(1=>$this->ht('Yes'), 0=>$this->ht('No')));

			//set defaults
			$ret = DB::Execute('SELECT u.login, p.mail, u.active FROM user_login u INNER JOIN user_password p ON (p.user_login_id=u.id) WHERE u.id=%d', $edit_id);
			if($ret && ($row = $ret->FetchRow())) {
				$form->setDefaults(array('username'=>$row['login'], 'mail'=>$row['mail'], 'active'=>$row['active']));
				$form->freeze('username');
			}

			$uid = Base_AclCommon::get_acl_user_id($edit_id);
			if($uid === false) {
				print('invalid user');
				return;
			}
			$sel->setSelected(Base_AclCommon::get_user_groups($uid));

		} else {
			$form->registerRule('check_username', 'callback', 'check_username_free', 'Base_User_LoginCommon');
			$form->addRule('username', $this->t('Username already taken'), 'check_username');
			$sel->setSelected(array(Base_AclCommon::get_group_id('User')));
		}

		/*
		$ok_b = HTML_QuickForm::createElement('submit', 'submit_button', $this->ht('OK'));
		$cancel_b = HTML_QuickForm::createElement('button', 'cancel_button', $this->ht('Cancel'), 'onClick="parent.location=\''.$this->create_href().'\'"');
		$form->addGroup(array($ok_b, $cancel_b));
		*/


		if($form->validate()) {
			if($form->process(array(&$this, 'submit_edit_user_form')))
				location(array());
		} else $form->display();

		Base_ActionBarCommon::add('back', 'Back', $this->create_back_href());
		Base_ActionBarCommon::add('save', 'Save', $form->get_submit_form_href());
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
			$user_id = Base_UserCommon::get_user_id($username);

			$groups_new = $data['group'];
			if(!Base_AclCommon::change_privileges($user_id, $groups_new)) {
				print($this->t('Unable to update account data (groups).'));
				return false;
			}
		} else {
			$user_id = Base_UserCommon::get_user_id($username);
			if($user_id === false || $user_id!=$edit_id) {
				print($this->t('Username doesn\'t match edited user.'));
				return false;
			}

			if(Base_User_LoginCommon::change_user_preferences($user_id, $mail, $pass)===false) {
				print($this->t('Unable to update account data (password and mail).'));
				return false;
			}

			if(!Base_UserCommon::change_active_state($user_id, $data['active'])) {
				print($this->t('Unable to update account data (active).'));
				return false;
			}

			$groups_new = $data['group'];
			if(!Base_AclCommon::change_privileges($user_id, $groups_new)) {
				print($this->t('Unable to update account data (groups).'));
				return false;
			}
		}
		return true;
	}

}
?>
