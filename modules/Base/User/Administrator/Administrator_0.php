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
            print(__('First log in to the system.'));
            return;
        }

        $form = $this->init_module('Libs/QuickForm',__('Saving settings'));

        //pass
        $form->addElement('header', null, __('Change password'));
        $form->addElement('html','<tr><td colspan=2>'.__('Leave password boxes empty if you prefer your current password').'</td></tr>');
        $form->addElement('password','new_pass',__('New password'));
        $form->addElement('password','new_pass_c',__('Confirm new password'));
        $form->addRule(array('new_pass', 'new_pass_c'), __('Your passwords don\'t match'), 'compare');
        $form->addRule('new_pass', __('Your password must be longer then 5 chars'), 'minlength', 6);

        //mail
        $form->addElement('header', null, __('Change e-mail'));
        $form->addElement('text','mail', __('New e-mail address'));
        $form->addRule('mail', __('Field required'), 'required');
        $form->addRule('mail', __('Invalid e-mail address'), 'email');

        //autologin
        $ret = DB::GetAll('SELECT autologin_id,description,last_log FROM user_autologin WHERE user_login_id=%d',array(Acl::get_user()));
        if($ret)
            $form->addElement('header', null, __('Delete autologin'));
        foreach($ret as $row)
            $form->addElement('checkbox','delete_autologin['.$row['autologin_id'].']',$row['description'],Base_RegionalSettingsCommon::time2reg($row['last_log']));

        //confirmation
        $form->addElement('header', null, __('Confirmation'));
        $form->addElement('password','old_pass', __('Old password'));
        $form->registerRule('check_old_pass', 'callback', 'check_old_pass', $this);
        $form->addRule('old_pass', __('Old password incorrect'), 'check_old_pass');
        $form->addRule('old_pass', __('Field required'), 'required');

		if (Base_AclCommon::check_permission('Advanced User Settings'))
			Base_ActionBarCommon::add('back',__('Back'),$this->create_main_href('Base_User_Settings'));
        Base_ActionBarCommon::add('save',__('Save'),$form->get_submit_form_href());
        #$form->addElement('submit', 'submit_button', __('OK'));

        if($form->validate_with_message('Setting saved',__('Problem encountered'))) {
            if($form->process(array(&$this, 'submit_user_preferences'))){
				if (Base_AclCommon::check_permission('Advanced User Settings'))
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
        return __('My settings: user');
    }

    public function submit_user_preferences($data) {
        if(DEMO_MODE && Base_UserCommon::get_my_user_login()=='admin') {
            print('You cannot change \'admin\' user password or e-mail in demo');
            return false;
        }
        $new_pass = $data['new_pass'];
        $mail = $data['mail'];
        
        if(isset($data['delete_autologin']))
        foreach($data['delete_autologin'] as $key=>$val)
            if($val) DB::Execute('DELETE from user_autologin WHERE autologin_id=%s AND user_login_id=%d',array($key,Acl::get_user()));

        $user_id = Acl::get_user();
        if($user_id===null) {
            print(__('Not logged in!'));
            return false;
        }

        return Base_User_LoginCommon::change_user_preferences($user_id, $mail, $new_pass);
    }


    public function check_old_pass($pass) {
        return Base_User_LoginCommon::check_login(Base_UserCommon::get_my_user_login(), $pass);
    }
    
    public function change_email_header() {
		if($this->is_back())
			return false;
	
		$form = $this->init_module('Libs/QuickForm',__('Saving settings'));
		
		//pass
		$form->addElement('header', null, __('Change e-mail header'));
		$form->addElement('textarea', 'emailHeader', __('Enter e-mail header:'), array('size' => 50, 'maxlength' => 255));
	
		
		if ($form->validate()) {
			$emailHeader = $form->exportValue('emailHeader');
			Variable::set('add_user_email_header',$emailHeader);
			$this->set_back_location();
			return false;
		}

		Base_ActionBarCommon::add('back',__('Back'),$this->create_back_href());
		Base_ActionBarCommon::add('save', __('Save'), $form->get_submit_form_href());
		
		$emailHeader = Variable::get('add_user_email_header','');
		$form->setDefaults(array('emailHeader'=>$emailHeader));
		$form->display();
		
		return true;
    } 

    public function admin() {
		if (ModuleManager::is_installed('CRM_Contacts')>=0) {
			$this->pack_module('CRM_Contacts', array(), 'user_admin');
			return;
		}
		if($this->is_back()) {
			if($this->parent->get_type()=='Base_Admin')
				$this->parent->reset();
			else
				location(array());
			return;
		}
		Base_ActionBarCommon::add('back',__('Back'),$this->create_back_href());
		Base_ActionBarCommon::add('edit',__('E-mail header'),$this->create_callback_href(array($this,'change_email_header')));

        $gb = $this->init_module('Utils/GenericBrowser',null,'user_list');
        //$gb->set_module_variable('adv_search',false);

        $cols = array();
    	$cols[] = array('name'=>__('ID'), 'order'=>'u.id', 'width'=>6,'search'=>'id');
    	$cols[] = array('name'=>__('Login'), 'order'=>'u.login', 'width'=>20,'search'=>'login');
        $is_contacts = ModuleManager::is_installed('CRM/Contacts')>=0;
        if($is_contacts)
            $cols[] = array('name'=>__('Contact'), 'width'=>27);
        $cols[] = array('name'=>__('Active'), 'order'=>'u.active', 'width'=>5);
        $cols[] = array('name'=>__('Mail'), 'order'=>'p.mail', 'width'=>20,'search'=>'mail');
        $cols[] = array('name'=>__('Access'),'width'=>'27');

        if(Base_AclCommon::i_am_sa())
            $cols[] = array('name'=>__('Actions'),'width'=>'80px');
        $gb->set_table_columns($cols);

        $gb->set_default_order(array(__('Login')=>'ASC'));

        $search = $gb->get_search_query();
        $query = 'SELECT u.login, p.mail, u.id, u.active, u.admin FROM user_login u INNER JOIN user_password p on p.user_login_id=u.id'.($search?' WHERE '.$search:'');
        $query_qty = 'SELECT count(u.id) FROM user_login u INNER JOIN user_password p on p.user_login_id=u.id'.($search?' WHERE '.$search:'');

        $ret = $gb->query_order_limit($query, $query_qty);

        $yes = '<span style="color:green;">'.__('Yes').'</span>';
        $no = '<span style="color:red;">'.__('No').'</span>';
        if($ret)
            while(($row=$ret->FetchRow())) {
                $gb_row = array();
                $gb_row[] = $row['id'];
                $gb_row[] = '<a '.$this->create_callback_href(array($this,'edit_user_form'),array($row['id'])).'>'.$row['login'].'</a>';
                if($is_contacts) {
                    $c = CRM_ContactsCommon::get_contact_by_user_id($row['id']);
                    $gb_row[] = $c?CRM_ContactsCommon::contact_format_default($c):'---';
                }
                $gb_row[] = $row['active']?$yes:$no;
                $gb_row[] = $row['mail'];
				switch ($row['admin']) {
					case 2: $admin = __('Super Administrator'); break;
					case 1: $admin = __('Administrator'); break;
					default: $admin = __('User'); break;
				}
                $gb_row[] = $admin;
                if(Base_AclCommon::i_am_sa())
                    $gb_row[] = '<a '.$this->create_callback_href(array($this,'log_as_user'),$row['id']).'>'.__('Log as user').'</a>';
                $gb->add_row_array($gb_row);
            }

        $this->display_module($gb);

        $qf = $this->init_module('Libs/QuickForm',null,'ban');
        $qf->addElement('select','bantime',__('Ban time after 3 failed logins'),array(0=>__('Disabled'),10=>__('10 seconds'),30=>__('30 seconds'),60=>__('1 minute'),180=>__('3 minutes'),300=>__('5 minutes'),900=>__('15 minutes'),1800=>__('30 minutes'),3600=>__('1 hour'),(3600*6)=>__('6 hours'),(3600*24)=>__('1 day')),array('onChange'=>$qf->get_submit_form_js()));
        $qf->setDefaults(array('bantime'=>Variable::get('host_ban_time')));
        if($qf->validate()) {
            Variable::set('host_ban_time',$qf->exportValue('bantime'));
        }
        $qf->display();
//      print('<a '.$this->create_unique_href(array('edit_user'=>-1)).'>'.__('Add new user').'</a>');
        Base_ActionBarCommon::add('add',__('New user'),$this->create_callback_href(array($this,'edit_user_form'), array(-1)));
    }

    public function log_as_user($id) {
        Acl::set_user($id, true); //tag who is logged
        Epesi::redirect();
    }

    public function edit_user_form($edit_id) {
		if ($this->is_back()) {
			if($this->parent->get_type()!='Base_Admin') {
				$x = ModuleManager::get_instance('/Base_Box|0');
				if (!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
				$x->pop_main();
			}
			return false;
		}
        $form = $this->init_module('Libs/QuickForm',($edit_id>=0)?__('Applying changes'):__('Creating new user'));
        
        //create new user
        $form->addElement('header', null, (($edit_id>=0)?__('Edit user'):__('Create new user')));
        $form->addElement('hidden', $this->create_unique_key('edit_user'), $edit_id);

        $form->addElement('text', 'username', __('Username'));
        // require a username
        $form->addRule('username', __('A username must be between 3 and 32 chars'), 'rangelength', array(3,32));
        $form->addRule('username', __('Field required'), 'required');

        $form->addElement('text', 'mail', __('E-mail'));
        $form->addRule('mail', __('Field required'), 'required');
        $form->addRule('mail', __('Invalid e-mail address'), 'email');

        $form->addElement('select', 'admin', __('Administrator'), array(0=>__('No'), 1=>__('Administrator'), 2=>__('Super Administrator')));

        if($edit_id<0)
            $form -> addElement('html','<tr><td colspan=2><b>'.__('If you leave password fields empty<br />random password is automatically generated<br />and e-mailed to the user.').'</b></td></tr>');
            //$form->addElement('header',null,__('If you leave this fields empty, password is generated.'));
        else
            $form -> addElement('html','<tr><td colspan=2><b>'.__('If you leave password fields empty, password is not changed.').'</b></td></tr>');
            //$form->addElement('header',null,__('If you leave this fields empty, password is not changed.'));

        $form->addElement('password', 'pass', __('Password'));
        $form->addElement('password', 'pass_c', __('Confirm Password'));
        $form->addRule(array('pass','pass_c'), __('Passwords don\'t match'), 'compare');
        $form->addRule('pass', __('Your password must be longer then 5 chars'), 'minlength', 5);

        if($edit_id>=0) {
            $form->addElement('select', 'active', __('Active'), array(1=>__('Yes'), 0=>__('No')));

            //set defaults
            $ret = DB::Execute('SELECT u.login, p.mail, u.active, u.admin FROM user_login u INNER JOIN user_password p ON (p.user_login_id=u.id) WHERE u.id=%d', $edit_id);
            if($ret && ($row = $ret->FetchRow())) {
                $form->setDefaults(array('username'=>$row['login'], 'mail'=>$row['mail'], 'active'=>$row['active'], 'admin'=>$row['admin']));
            }
        }
        $form->registerRule('check_username', 'callback', 'check_username_free', 'Base_User_LoginCommon');
        $form->addRule(array('username',$this->create_unique_key('edit_user')), __('Username already taken'), 'check_username');

        if($form->validate()) {
            if($form->process(array(&$this, 'submit_edit_user_form'))) {
				if($this->parent->get_type()!='Base_Admin') {
					$x = ModuleManager::get_instance('/Base_Box|0');
					if (!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
					$x->pop_main();
				}
                return false;
			}
        } else $form->display();

        Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
        Base_ActionBarCommon::add('save', __('Save'), $form->get_submit_form_href());
		if(Base_AclCommon::i_am_sa() && $edit_id>=0)
			Base_ActionBarCommon::add('settings', __('Log as user'), $this->create_callback_href(array($this,'log_as_user'),$edit_id));
			
		return true;
    }

    public function submit_edit_user_form($data) {
        $mail = $data['mail'];
        $username = $data['username'];

        if(DEMO_MODE) {
            print('You cannot change user password or e-mail address in demo');
            return false;
        }

        $pass = $data['pass'];
        $edit_id = $this->get_unique_href_variable('edit_user');

        if($edit_id<0) {
            if(!Base_User_LoginCommon::add_user($username, $mail, $pass)) {
                return false;
            }
            $edit_id = Base_UserCommon::get_user_id($username);
        } else {
            Base_UserCommon::rename_user($edit_id, $username);
            
            if(Base_User_LoginCommon::change_user_preferences($edit_id, $mail, $pass)===false) {
                print(__('Unable to update account data (password and mail).'));
                return false;
            }
            if(!Base_UserCommon::change_active_state($edit_id, $data['active'])) {
                print(__('Unable to update account data (active).'));
                return false;
            }
        }
		if(!Base_UserCommon::change_admin($edit_id, $data['admin'])) {
			print(__('Unable to update account data (admin).'));
			return false;
		}
        return true;
    }

}
?>
