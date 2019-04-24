<?php
/**
 * CRM Contacts class.
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage contacts
 */

defined("_VALID_ACCESS") || die();

class CRM_Contacts extends Module {
	private $rb = null;

	public function applet($conf, & $opts) { //available applet options: toggle,href,title,go,go_function,go_arguments,go_contruct_arguments
		$opts['go'] = 1;
		$mode = 'contact';
		$rb = $this->init_module(Utils_RecordBrowser::module_name(),$mode,$mode);
		$conds = array(
									array(	array('field'=>'last_name', 'width'=>10),
											array('field'=>'first_name', 'width'=>10),
											array('field'=>'company_name', 'width'=>10)
										),
									array(':Recent'=>1),
									array(':Visited_on'=>'DESC','last_name'=>'ASC','first_name'=>'ASC','company_name'=>'ASC'),
									array('CRM_ContactsCommon','applet_info_format'),
									15,
									$conf,
									& $opts
				);
		
		$opts['actions'][] = Utils_RecordBrowserCommon::applet_new_record_button('contact',array(	'country'=>Base_User_SettingsCommon::get('Base_RegionalSettings','default_country'),
								'zone'=>Base_User_SettingsCommon::get('Base_RegionalSettings','default_state'),
								'permission'=>'0','home_country'=>Base_User_SettingsCommon::get('Base_RegionalSettings','default_country'),
								'home_zone'=>Base_User_SettingsCommon::get('Base_RegionalSettings','default_state')));
		$this->display_module($rb, $conds, 'mini_view');
	}

	public function body($mode='contact') {
		if (isset($_REQUEST['mode'])) $this->set_module_variable('mode', $_REQUEST['mode']);
		$mode = $this->get_module_variable('mode',$mode);
		if ($mode=='my_contact') {
			$this->rb = $this->init_module(Utils_RecordBrowser::module_name(),'contact','contact');
			$me = CRM_ContactsCommon::get_my_record();
			$this->display_module($this->rb, array('view', $me['id'], array(), array('back'=>false)), 'view_entry');
			return;
		}
		if ($mode=='main_company') {
			$this->rb = $this->init_module(Utils_RecordBrowser::module_name(),'company','company');
			$me = CRM_ContactsCommon::get_main_company();
			$this->display_module($this->rb, array('view', $me, array(), array('back'=>false)), 'view_entry');
			return;
		}
		if ($mode!='contact' && $mode!='company') trigger_error('Unknown mode.');

		$this->rb = $this->init_module(Utils_RecordBrowser::module_name(),$mode,$mode);
		$this->rb->set_defaults(array(	'country'=>Base_User_SettingsCommon::get('Base_RegionalSettings','default_country'),
										'zone'=>Base_User_SettingsCommon::get('Base_RegionalSettings','default_state'),
										'permission'=>Base_User_SettingsCommon::get('CRM_Common','default_record_permission')));
		if ($mode=='contact') {
			$fcallback = array('CRM_ContactsCommon','company_format_default');
			$this->rb->set_custom_filter('company_name', array('type'=>'autoselect','label'=>__('Company Name'),'args'=>array(), 'args_2'=>array(array('CRM_ContactsCommon','autoselect_company_suggestbox'), array(array(), $fcallback)), 'args_3'=>$fcallback, 'trans_callback'=>array('CRM_ContactsCommon','autoselect_company_filter_trans')));
			$this->rb->set_defaults(array(	'home_country'=>Base_User_SettingsCommon::get('Base_RegionalSettings','default_country'),
											'home_zone'=>Base_User_SettingsCommon::get('Base_RegionalSettings','default_state')));
			$this->rb->set_default_order(array('last_name'=>'ASC', 'first_name'=>'ASC'));
			$this->rb->set_additional_actions_method(array($this, 'contacts_actions'));
		} else {
			$this->rb->set_default_order(array('company_name'=>'ASC'));
			$this->rb->set_additional_actions_method(array($this, 'companies_actions'));
		}
		$this->display_module($this->rb);
	}

	public function user_admin(){
		if($this->is_back()) {
			if($this->parent->parent->get_type()=='Base_Admin')
				$this->parent->parent->reset();
			else
				location(array());
			return;
		}
		if (!Base_AclCommon::i_am_admin()) return false;
		Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());

		$this->rb = $this->init_module(Utils_RecordBrowser::module_name(),'contact','contact');
		$logins = DB::GetAll('SELECT * FROM user_login');
		$active_logins = array();
		$inactive_logins = array();
		$user_logins = array();
		$admin_logins = array();
		$sa_logins = array();
		foreach ($logins as $i) {
			if ($i['active']) $active_logins[] = $i['id'];
			else $inactive_logins[] = $i['id'];
			if ($i['admin']==0) $user_logins[] = $i['id'];
			elseif ($i['admin']==1) $admin_logins[] = $i['id'];
			else $sa_logins[] = $i['id'];
		}
		$this->rb->set_custom_filter('username', array('type'=>'select','label'=>__('Active'),'args'=>array('__NULL__'=>'---', 1=>__('Yes'), 2=>__('No')), 'trans'=>array('__NULL__' => array(), 1=>array('login'=>$active_logins), 2=>array('login'=>$inactive_logins))));
		$this->rb->set_custom_filter('admin', array('type'=>'select','label'=>__('Admin'),'args'=>array('__NULL__'=>'---', 0=>__('No'), 1=>__('Administrator'), 2=>__('Super Administrator')), 'trans'=>array('__NULL__' => array(), 0=>array('login'=>$user_logins), 1=>array('login'=>$admin_logins), 2=>array('login'=>$sa_logins))));
		$this->rb->set_defaults(array(	'country'=>Base_User_SettingsCommon::get('Base_RegionalSettings','default_country'),
										'zone'=>Base_User_SettingsCommon::get('Base_RegionalSettings','default_state'),
										'permission'=>Base_User_SettingsCommon::get('CRM_Common','default_record_permission'),
										'home_country'=>Base_User_SettingsCommon::get('Base_RegionalSettings','default_country'),
										'home_zone'=>Base_User_SettingsCommon::get('Base_RegionalSettings','default_state'),
										'login'=>'new'));
		$this->rb->set_default_order(array('last_name'=>'ASC', 'first_name'=>'ASC'));
		$this->rb->set_additional_actions_method(array($this, 'user_actions'));
		$this->rb->set_additional_caption(__('Users'));
		$this->rb->disable_pdf();
		$this->rb->disable_export();
		$this->display_module($this->rb, array(array(), array('!login'=>''), array('work_phone'=>false, 'admin'=>true, 'mobile_phone'=>false, 'city'=>false, 'zone'=>false, 'login'=>true, 'access'=>true, 'email'=>true), array('username'=>true, 'admin'=>true, 'access'=>true, 'related_companies'=>false)));

		Base_ActionBarCommon::add('edit',__('E-mail header'),$this->create_callback_href(array('Base_BoxCommon', 'push_module'), array($this->get_type(), 'change_email_header')),__('Edit the header of the message that is sent to each newly created user'));
	}

    public function change_email_header() {
		$adm = $this->init_module('Base_User_Administrator');
		if ($adm->is_back()) {
            return Base_BoxCommon::pop_main();
        }
		$this->display_module($adm, array(), 'change_email_header');
		print('<span style="display:none;">'.microtime(true).'</span>');
		return true;
	}
	
	public function user_actions($contact, $gb_row) {
        if (!Base_User_AdministratorCommon::get_log_as_user_access($contact['login'])) return;
        
        if (Base_UserCommon::is_active($contact['login'])) {
        	$gb_row->add_action($this->create_callback_href(array($this, 'change_user_active_state'), array($contact['login'], false)), __('Deactivate user'), null, Base_ThemeCommon::get_template_file('Utils_GenericBrowser', 'active-on.png'));
            $gb_row->add_action(Module::create_href(array('log_as_user' => $contact['login'])), __('Log as user'), null, Base_ThemeCommon::get_template_file('Utils_GenericBrowser', 'restore.png'));
            // action!
            if (isset($_REQUEST['log_as_user']) && $_REQUEST['log_as_user'] == $contact['login']) {
            	Acl::set_user($contact['login'], true);
                Epesi::redirect();
                return;
            }
        } else {
            $gb_row->add_action($this->create_callback_href(array($this, 'change_user_active_state'), array($contact['login'], true)), 'Activate user', null, Base_ThemeCommon::get_template_file('Utils_GenericBrowser', 'active-off.png'));
        }
    }
	public function change_user_active_state($user, $state) {
		Base_UserCommon::change_active_state($user, $state);
		return false;
	}
	
	public function company_addon($arg){
		$rb = $this->init_module(Utils_RecordBrowser::module_name(),'contact','contact_addon');
		$rb->set_additional_actions_method(array($this, 'contacts_actions'));
		if(Utils_RecordBrowserCommon::get_access('contact','add'))
			Base_ActionBarCommon::add('add',__('Add contact'), $this->create_callback_href(array($this, 'company_addon_new_contact'), array($arg['id'])));
		$rb->set_button($this->create_callback_href(array($this, 'company_addon_new_contact'), array($arg['id'])));
		$this->add_associate_button($rb, 'add', $arg['id']);
		$this->add_associate_button($rb, 'remove', $arg['id']);
		$rb->set_defaults(array('company_name'=>$arg['id']));
		$this->display_module($rb, array(array('(company_name'=>$arg['id'],'|related_companies'=>array($arg['id'])), array('company_name'=>false), array('last_name'=>'ASC','first_name'=>'ASC')), 'show_data');
        $uid = Base_AclCommon::get_clearance();
        if (in_array('ACCESS:manager', $uid) && in_array('ACCESS:employee', $uid)) {
            $prompt_id = "contacts_address_fix";
            $content = $this->update_contacts_address_prompt($arg, $prompt_id);
            Libs_LeightboxCommon::display($prompt_id, $content, __('Update Contacts'));
            Base_ActionBarCommon::add('all', __('Update Contacts'), Libs_LeightboxCommon::get_open_href($prompt_id));
        }
    }
    
    public function add_associate_button($rb, $mode, $company_id) {
    	switch ($mode) {
    		case 'add':
    			$crits = array('!company_name'=>$company_id, '!related_companies'=>$company_id);
    			$label = __('Associate');
    			$icon = 'add';
    			$tooltip = Utils_TooltipCommon::open_tag_attrs(__('Click to associate contacts with this company (Add company to contact related companies)'));
    			break;
    		case 'remove':
    			$crits = array('related_companies'=>$company_id);
    			$label = __('Remove Associate');
    			$icon = 'delete';
    			$tooltip = Utils_TooltipCommon::open_tag_attrs(__('Click to remove company association from selected contacts'));
    			break;
    		default:
    			return;
    			break;
    	}
    	
    	if (!Utils_RecordBrowserCommon::get_records_count('contact', $crits)) return;
    
    	$record_picker = $this->pack_module(Utils_RecordBrowser_RecordPickerFS::module_name(), null, null, array('contact', $crits));
    
    	$rb->new_button($icon, $label, 'style="cursor:pointer;" ' . $tooltip . ' ' . $record_picker->create_open_href());
    
    	$selected = $record_picker->get_selected();
    	if (empty($selected)) return;
    
    	foreach ($selected as $contact_id) {
    		$contact = CRM_ContactsCommon::get_contact($contact_id);
    		
    		$related_companies = $contact['related_companies'];
    		
    		switch ($mode) {
    			case 'add':
    				$related_companies = array_unique(array_merge($related_companies, array($company_id)));
    				break;
    			case 'remove':
    				if(($key = array_search($company_id, $related_companies)) !== false) {
    					unset($related_companies[$key]);
    				}
    				break;
    			default:
    				return;
    				break;
    		}
    			
    		Utils_RecordBrowserCommon::update_record('contact', $contact_id, array('related_companies'=>$related_companies));
    		
    		Base_StatusBarCommon::message(__('Company associations updated'));
    	}
    
    	$record_picker->clear_selected();
    }

	public function contacts_actions($r, $gb_row) {
		$is_employee = false;
		if (!isset($r['company_name'])) return;
		if (is_array($r['company_name']) && in_array(CRM_ContactsCommon::get_main_company(), $r['company_name'])) $is_employee = true;
		$me = CRM_ContactsCommon::get_my_record();
		$emp = array($me['id']);
		$cus = array();
		if ($is_employee) $emp[] = $r['id'];
		else $cus[] = 'contact/'.$r['id'];
		if (CRM_MeetingInstall::is_installed() && Utils_RecordBrowserCommon::get_access('crm_meeting','add')) $gb_row->add_action(Utils_RecordBrowserCommon::create_new_record_href('crm_meeting', array('employees'=>$emp,'customers'=>$cus,'status'=>0, 'priority'=>1, 'permission'=>0)), __('New Meeting'), null, Base_ThemeCommon::get_template_file('CRM_Calendar','icon-small.png'));
		if (CRM_TasksInstall::is_installed() && Utils_RecordBrowserCommon::get_access('task','add')) $gb_row->add_action(Utils_RecordBrowserCommon::create_new_record_href('task', array('employees'=>$emp,'customers'=>$cus,'status'=>0, 'priority'=>1, 'permission'=>0)), __('New Task'), null, Base_ThemeCommon::get_template_file('CRM_Tasks','icon-small.png'));
		if (CRM_PhoneCallInstall::is_installed() && Utils_RecordBrowserCommon::get_access('phonecall','add')) $gb_row->add_action(Utils_RecordBrowserCommon::create_new_record_href('phonecall', array('date_and_time'=>date('Y-m-d H:i:s'),'customer'=>'contact/'.$r['id'],'employees'=>$me['id'],'status'=>0, 'permission'=>0, 'priority'=>1),'none',array('date_and_time')), __('New Phonecall'), null, Base_ThemeCommon::get_template_file('CRM_PhoneCall','icon-small.png'));
		$gb_row->add_action(Utils_RecordBrowser::$rb_obj->add_note_button_href('contact/'.$r['id']), __('New Note'), null, Base_ThemeCommon::get_template_file('Utils_Attachment','icon_small.png'));
	}

	public function companies_actions($r, $gb_row) {
		$me = CRM_ContactsCommon::get_my_record();
		$emp = array($me['id']);
		$cus = array();
		$cus[] = 'company/'.$r['id'];
		if (CRM_MeetingInstall::is_installed() && Utils_RecordBrowserCommon::get_access('crm_meeting','add')) $gb_row->add_action(Utils_RecordBrowserCommon::create_new_record_href('crm_meeting', array('employees'=>$emp,'customers'=>$cus,'status'=>0, 'priority'=>1, 'permission'=>0)), __('New Meeting'), null, Base_ThemeCommon::get_template_file('CRM_Calendar','icon-small.png'));
		if (CRM_TasksInstall::is_installed() && Utils_RecordBrowserCommon::get_access('task','add')) $gb_row->add_action(Utils_RecordBrowserCommon::create_new_record_href('task', array('employees'=>$emp,'customers'=>$cus,'status'=>0, 'priority'=>1, 'permission'=>0)), __('New Task'), null, Base_ThemeCommon::get_template_file('CRM_Tasks','icon-small.png'));
		if (CRM_PhoneCallInstall::is_installed() && Utils_RecordBrowserCommon::get_access('phonecall','add')) $gb_row->add_action(Utils_RecordBrowserCommon::create_new_record_href('phonecall', array('date_and_time'=>date('Y-m-d H:i:s'),'customer'=>'company/'.$r['id'],'employees'=>$me['id'],'status'=>0, 'permission'=>0, 'priority'=>1),'none',array('date_and_time')), __('New Phonecall'), null, Base_ThemeCommon::get_template_file('CRM_PhoneCall','icon-small.png'));
		$gb_row->add_action(Utils_RecordBrowser::$rb_obj->add_note_button_href('company/'.$r['id']), __('New Note'), null, Base_ThemeCommon::get_template_file('Utils_Attachment','icon_small.png'));
	}

	public function company_addon_new_contact($id){
		return Base_BoxCommon::push_module(CRM_Contacts::module_name(),'new_contact',$id,array());
	}

    public function update_contacts_address_prompt($company, $lid) {
        $html = '<br/>'.__('This action will update all contacts within this company with values copied from company record.').'<br/><br/>'.__('Please check which data would you like to copy to company contacts:');
        $form = $this->init_module(Libs_QuickForm::module_name());

        $data = array( /* Source ID, Target ID, Text, Checked state */
            array('sid'=>'address_1', 'tid'=>'address_1', 'text'=>__('Address 1'), 'checked'=>true),
            array('sid'=>'address_2', 'tid'=>'address_2', 'text'=>__('Address 2'), 'checked'=>true),
            array('sid'=>'city', 'tid'=>'city', 'text'=>__('City'), 'checked'=>true),
            array('sid'=>'country', 'tid'=>'country', 'text'=>__('Country'), 'checked'=>true),
            array('sid'=>'zone', 'tid'=>'zone', 'text'=>__('Zone'), 'checked'=>true),
            array('sid'=>'postal_code', 'tid'=>'postal_code', 'text'=>__('Postal Code'), 'checked'=>true),
            array('sid'=>'phone', 'tid'=>'work_phone', 'text'=>__('Phone as Work Phone'), 'checked'=>false),
            array('sid'=>'fax', 'tid'=>'fax', 'text'=>__('Fax'), 'checked'=>false),
        );
        foreach($data as $row) {
			if (!isset($company[$row['sid']])) continue;
            $form->addElement('checkbox', $row['sid'], $row['text'], '&nbsp;&nbsp;<span style="color: gray">'.$company[$row['sid']].'</span>', $row['checked'] ? array('checked'=>'checked'): array());
        }

        $ok = $form->createElement('submit', 'submit', __('Confirm'), array('onclick'=>'leightbox_deactivate("'.$lid.'")'));
        $cancel = $form->createElement('button', 'cancel', __('Cancel'), array('onclick'=>'leightbox_deactivate("'.$lid.'")'));
        $form->addGroup(array($ok, $cancel));

        if($form->validate()) {
            $values = $form->exportValues();
            $fields = array();
            foreach($data as $row) {
                if(array_key_exists($row['sid'], $values)) {
                    $fields[$row['tid']] = $row['sid'];
                }
            }
            $this->update_contacts_address($company, $fields);
            location(array());
        }

        $html .= $form->toHtml();

        return $html;
    }

    public function update_contacts_address($company, $fields) {
        $recs = CRM_ContactsCommon::get_contacts(array('company_name' => $company['id']), array('id'));
        $new_data = array();
        foreach($fields as $k => $v) {
            $new_data[$k] = $company[$v];
        }
        foreach($recs as $contact) {
            Utils_RecordBrowserCommon::update_record('contact', $contact['id'], $new_data);
        }
    }

	public function new_contact($company){
		CRM_ContactsCommon::$paste_or_new = $company;
		$rb = $this->init_module(Utils_RecordBrowser::module_name(),'contact','contact');
		$this->rb = $rb;
		$this->display_module($rb, array('add', null, array('company_name'=>$company,
												'country'=>Base_User_SettingsCommon::get('Base_RegionalSettings','default_country'),
												'zone'=>Base_User_SettingsCommon::get('Base_RegionalSettings','default_state'),											
												'permission'=>'0')), 'view_entry');
		$this->set_module_variable('view_or_add', 'add');
	}

	public function caption(){
		if (isset($this->rb)) return $this->rb->caption();
	}
}
?>
