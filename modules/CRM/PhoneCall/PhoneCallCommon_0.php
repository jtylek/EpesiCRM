<?php
/**
 * CRM Phone Call Class
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage phonecall
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_PhoneCallCommon extends ModuleCommon {
	public static function applet_caption() {
		if (Utils_RecordBrowserCommon::get_access('phonecall','browse'))
			return __('Phonecalls');
	}
	public static function applet_info() {
		return __('List of phone calls to do');
	}
	public static function applet_settings() {
		return Utils_RecordBrowserCommon::applet_settings(array(
			array('label'=>__('Include missed (past) calls'),'name'=>'past','type'=>'checkbox','default'=>1),
			array('label'=>__('Include today calls'),'name'=>'today','type'=>'checkbox','default'=>1),
			array('label'=>__('Include future calls'),'name'=>'future','type'=>'select','values'=>array(0=>__('No'),1=>__('Tomorrow'),2=>__('2 days forward'),7=>__('1 week forward'),-1=>__('All')),'default'=>0)
			));
	}
	public static function applet_info_format($r){
		if (isset($r['customer']) && $r['customer']!='') {
			$customer = CRM_ContactsCommon::autoselect_company_contact_format($r['customer']);
			list($rset, $id) = explode(':',$r['customer']);
			$cus = Utils_RecordBrowserCommon::get_record($rset=='P'?'contact':'company', $id);
			if (isset($r['phone']) && $r['phone']!='') {
				$num = $r['phone'];
				switch ($num) {
					case 1: $id = 'mobile_phone'; 	$nr = 'Mobile Phone'; break;
					case 2: $id = 'work_phone'; 	$nr = 'Work Phone'; break;
					case 3: $id = 'home_phone'; 	$nr = 'Home Phone'; break;
					case 4: $id = 'phone'; 			$nr = 'Phone'; break;
				}
				$phone = $nr.': '.(isset($cus[$id])?$cus[$id]:'error');
			} else $phone = __('Other').': '.$r['other_phone_number'];
		} else {
			$customer = $r['other_customer_name'];
			$phone = $r['other_phone_number'];
			$company = '---';
		}

		// Build array representing 2-column tooltip
		// Format: array (Label,value)
		$access = Utils_CommonDataCommon::get_translated_array('CRM/Access');
		$priority = Utils_CommonDataCommon::get_translated_array('CRM/Priority');
		$status = Utils_CommonDataCommon::get_translated_array('CRM/Status');

		$args=array(
					__('Call')=>'<b>'.$phone.'</b>',
					__('Customer')=>$customer,
					__('Subject')=>'<b>'.$r['subject'].'</b>',
					__('Description')=>$r['description'],
					__('Assigned to')=>CRM_ContactsCommon::display_contact(array('id'=>$r['employees']),true,array('id'=>'id', 'param'=>'::;CRM_ContactsCommon::contact_format_no_company')),
					__('Date and Time')=>Base_RegionalSettingsCommon::time2reg($r['date_and_time']),
					__('Status')=>$status[$r['status']],
					__('Permission')=>$access[$r['permission']],
					__('Priority')=>$priority[$r['priority']]
					);

		// Pass 2 arguments: array containing pairs: label/value
		// and the name of the group for translation
		$bg_color = '';
		switch ($r['priority']) {
			case 1: $bg_color = '#FFFFD5'; break;
			case 2: $bg_color = '#FFD5D5'; break;
		}
		$ret = array('notes'=>Utils_TooltipCommon::format_info_tooltip($args));
		if ($bg_color) $ret['row_attrs'] = 'style="background:'.$bg_color.';"';
		return $ret;

	}

	public static function get_phonecalls($crits = array(), $cols = array(), $order = array()) {
		return Utils_RecordBrowserCommon::get_records('phonecall', $crits, $cols, $order);
	}
	public static function get_phonecall($id) {
		return Utils_RecordBrowserCommon::get_record('phonecall', $id);
	}
	/*--------------------------------------------------------------------*/
	public static function employees_crits(){
		// Select only main company contacts and only office staff employees
		return array('(company_name'=>CRM_ContactsCommon::get_main_company(),'|related_companies'=>array(CRM_ContactsCommon::get_main_company()));
	}
	
	public static function company_crits(){
		return array('_no_company_option'=>true);
	}
	public static function menu() {
		if (Utils_RecordBrowserCommon::get_access('phonecall','browse'))
			return array(_M('CRM')=>array('__submenu__'=>1,_M('Phonecalls')=>array()));
		else
			return array();
	}
	public static function caption() {
		return __('Phonecalls');
	}
	public static function QFfield_other_phone(&$form, $field, $label, $mode, $default, $desc) {
		if ($mode=='add' || $mode=='edit') {
			$js =
					'Event.observe(\'other_phone\',\'change\', onchange_other_phone);'.
					'function enable_disable_phone(arg) {'.
					'phone = document.forms[\''.$form->getAttribute('name').'\'].phone;'.
					'o_phone = document.forms[\''.$form->getAttribute('name').'\'].other_phone_number;'.
					'if (arg) {phone.disabled=true;o_phone.disabled=false;} else {if(phone.length!=0)phone.disabled=false;o_phone.disabled=true;}'.
					'};'.
					'function onchange_other_phone() {'.
					'c_phone = document.forms[\''.$form->getAttribute('name').'\'].other_phone;'.
					'enable_disable_phone(c_phone.checked);'.
					'};'.
					'c_phone = document.forms[\''.$form->getAttribute('name').'\'].other_phone;'.
					'enable_disable_phone('.($default?'1':'0').' || c_phone.checked);';
			eval_js($js);
			$form->addElement('checkbox', $field, $label, null, array('id'=>$field));
			if ($mode=='edit') $form->setDefaults(array($field=>$default));
		} else {
			$form->addElement('checkbox', $field, $label);
			$form->setDefaults(array($field=>$default));//self::display_phone(array($desc['id']=>$default), null, false, $desc)));
		}
	}
	public static function QFfield_other_contact(&$form, $field, $label, $mode, $default, $desc) {
		if ($mode=='add' || $mode=='edit') {
			$js =
					'Event.observe(\'other_customer\',\'change\', onchange_other_customer);'.
					'function enable_disable_customer(arg) {'.
					'customer = document.forms[\''.$form->getAttribute('name').'\'].customer;'.
					'customer_s = document.forms[\''.$form->getAttribute('name').'\'].customer__search;'.
					'o_customer = document.forms[\''.$form->getAttribute('name').'\'].other_customer_name;'.
					'c_phone = document.forms[\''.$form->getAttribute('name').'\'].other_phone;'.
					'if (arg) {c_phone.disabled=true;customer_s.disabled=customer.disabled=true;o_customer.disabled=false;} else {c_phone.disabled=false;if(customer.length!=0)customer_s.disabled=customer.disabled=false;o_customer.disabled=true;}'.
					'if (arg) c_phone.checked=true;'.
					'phone = document.forms[\''.$form->getAttribute('name').'\'].phone;'.
					'o_phone = document.forms[\''.$form->getAttribute('name').'\'].other_phone_number;'.
					'if (arg) {phone.disabled=true;o_phone.disabled=false;} else {if(phone.length!=0)phone.disabled=false;o_phone.disabled=true;}'.
					'};'.
					'function onchange_other_customer() {'.
					'c_customer = document.forms[\''.$form->getAttribute('name').'\'].other_customer;'.
					'c_phone = document.forms[\''.$form->getAttribute('name').'\'].other_phone;'.
					'c_phone.checked = c_customer.checked;'.
					'enable_disable_customer(c_customer.checked);'.
					'};'.
					'c_customer = document.forms[\''.$form->getAttribute('name').'\'].other_customer;'.
					'enable_disable_customer('.($default?'1':'0').' || c_customer.checked);';
			eval_js($js);
			$form->addElement('checkbox', $field, $label, null, array('id'=>$field));
			if ($mode=='edit') $form->setDefaults(array($field=>$default));
		} else {
			$form->addElement('checkbox', $field, $label);
			$form->setDefaults(array($field=>$default));//self::display_phone(array($desc['id']=>$default), null, false, $desc)));
		}
	}
	public static function check_contact_not_empty($v) {
		$ret = array();
		if ((isset($v['other_phone']) && $v['other_phone']) || (isset($v['other_customer']) && $v['other_customer'])) {
			if (!isset($v['other_phone_number']) || !$v['other_phone_number'])
				$ret['other_phone_number'] = __('Field required');
		} else {
			if (!isset($v['phone']) || !$v['phone'])
				$ret['phone'] = __('Field required');
		}
		if (!isset($v['other_customer']) || !$v['other_customer']) {
			if (!isset($v['customer']) || !$v['customer'])
				$ret['customer'] = __('Field required');
		} else {
			if (!isset($v['other_customer_name']) || !$v['other_customer_name'])
				$ret['other_customer_name'] = __('Field required');
		}
		return empty($ret)?true:$ret;
	}
	public static function QFfield_phone(&$form, $field, $label, $mode, $default, $desc) {
		if ($mode=='add' || $mode=='edit') {
			$form->addElement('select', $field, $label, array(), array('id'=>$field));
			if ($mode=='edit') $form->setDefaults(array($field=>$default));
			Utils_ChainedSelectCommon::create($field, array('customer'),'modules/CRM/PhoneCall/update_phones.php',null,$form->exportValue($field));
			$form->addFormRule(array('CRM_PhoneCallCommon','check_contact_not_empty'));
		} else {
			$form->addElement('static', $field, $label);
			$form->setDefaults(array($field=>self::display_phone(Utils_RecordBrowser::$last_record, false, $desc)));
		}
	}
    public static function display_subject($record, $nolink = false) {
		$ret = Utils_RecordBrowserCommon::create_linked_label_r('phonecall', 'Subject', $record, $nolink);
		if (!$nolink && isset($record['description']) && $record['description']!='') $ret = '<span '.Utils_TooltipCommon::open_tag_attrs(Utils_RecordBrowserCommon::format_long_text($record['description']), false).'>'.$ret.'</span>';
		return $ret;
	}
	public static function display_phone_number($record, $nolink) {
		if ($record['other_phone']) {
			if(MOBILE_DEVICE && IPHONE && !$nolink && preg_match('/^([0-9\t\+-]+)/',$record['other_phone_number'],$args))
				return '<a href="tel:'.$args[1].'">'.__('O').': '.$record['other_phone_number'].'</a>';
			return __('O').': '.CRM_CommonCommon::get_dial_code($record['other_phone_number']);
		} else return self::display_phone($record,false,array('id'=>'phone'));
	}
	public static function display_contact_name($record, $nolink) {
		if ($record['other_customer']) return $record['other_customer_name'];
		if ($record['customer']=='') return '---';
		$ret = CRM_ContactsCommon::autoselect_company_contact_format($record['customer'], $nolink);
/*		if (!$nolink) $ret .= Utils_RecordBrowserCommon::record_link_open_tag('contact', $record['customer']);
		$cont = CRM_ContactsCommon::get_contact($record['customer']);
		$ret .= $cont['last_name'].(($cont['first_name']!=='')?' '.$cont['first_name']:'');
		if (!$nolink) $ret .= Utils_RecordBrowserCommon::record_link_close_tag();*/
		return $ret;
	}
	public static function display_phone($record, $nolink, $desc) {
		if ($record[$desc['id']]=='') return '';
		$num = $record[$desc['id']];
		if (!isset($record['customer']) || !$record['customer']) return '---';
		list($r,$id) = explode(':',$record['customer']);
		if ($r=='P')
			$contact = CRM_ContactsCommon::get_contact($id);
		else
			$contact = CRM_ContactsCommon::get_company($id);
		$nr = '';
		switch ($num) {
			case 1: $id = 'mobile_phone'; 	$nr = 'Mobile Phone'; break;
			case 2: $id = 'work_phone'; 	$nr = 'Work Phone'; break;
			case 3: $id = 'home_phone'; 	$nr = 'Home Phone'; break;
			case 4: $id = 'phone'; 			$nr = 'Phone'; break;
		}
		if (!$nr) return '';

		if(!isset($contact[$id])) return '---';
		$number = $contact[$id];
		if($number && strpos($number,'+')===false) {
			if($contact['country']) {
				$calling_code = Utils_CommonDataCommon::get_value('Calling_Codes/'.$contact['country']);
				if($calling_code)
					$number = $calling_code.' '.$number;
			}
		}

		if(MOBILE_DEVICE && IPHONE)
			return $nr[0].': '.'<a href="tel:'.$number.'">'.$number.'</a>';
		if($nolink)
			return $nr[0].': '.$number;
		return $nr[0].': '.CRM_CommonCommon::get_dial_code($number);
	}
	public static function display_status($record, $nolink, $desc) {
		$prefix = 'crm_phonecall_leightbox';
		$v = $record[$desc['id']];
		if (!$v) $v = 0;
		$status = Utils_CommonDataCommon::get_translated_array('CRM/Status');
		if ($v>=2 || $nolink) return $status[$v];
		if (!Utils_RecordBrowserCommon::get_access('phonecall', 'edit', $record) && !Base_AclCommon::i_am_admin()) return $status[$v];
		CRM_FollowupCommon::drawLeightbox($prefix);
		if (isset($_REQUEST['form_name']) && $_REQUEST['form_name']==$prefix.'_follow_up_form' && $_REQUEST['id']==$record['id']) {
			unset($_REQUEST['form_name']);
			$v = $_REQUEST['closecancel'];
			$action  = $_REQUEST['action'];

			$note = $_REQUEST['note'];
			if ($note) {
				if (get_magic_quotes_gpc())
					$note = stripslashes($note);
				$note = str_replace("\n",'<br />',$note);
				Utils_AttachmentCommon::add('phonecall/'.$record['id'],0,Acl::get_user(),$note);
			}

			if ($action == 'set_in_progress') $v = 1;
			Utils_RecordBrowserCommon::update_record('phonecall', $record['id'], array('status'=>$v));
			if ($action == 'set_in_progress') location(array());

			$values = $record;
			$values['date_and_time'] = date('Y-m-d H:i:s');
			$values['title'] = __('Follow-up').': '.$values['subject'];
			$values['status'] = 0;

			if ($action != 'none') {
				$values['subject'] = __('Follow-up').': '.$values['subject'];
				$values['follow_up'] = array('phonecall',$record['id'],$record['subject']);
				$x = ModuleManager::get_instance('/Base_Box|0');
				if ($action == 'new_task') $x->push_main('Utils/RecordBrowser','view_entry',array('add', null, array('title'=>$values['subject'],'permission'=>$values['permission'],'priority'=>$values['priority'],'description'=>$values['description'],'deadline'=>date('Y-m-d H:i:s', strtotime('+1 day')),'employees'=>$values['employees'], 'customers'=>$values['customer'],'status'=>0,'follow_up'=>$values['follow_up'])), array('task'));
				if ($action == 'new_phonecall') $x->push_main('Utils/RecordBrowser','view_entry',array('add', null, $values), array('phonecall'));
				if ($action == 'new_meeting') $x->push_main('Utils/RecordBrowser','view_entry',array('add', null, array('title'=>$values['subject'],'permission'=>$values['permission'],'priority'=>$values['priority'],'description'=>$values['description'],'date'=>date('Y-m-d'),'time'=>date('H:i:s'),'duration'=>3600,'status'=>0,'employees'=>$values['employees'], 'customers'=>$values['customer'], 'follow_up'=>$values['follow_up'])), array('crm_meeting'));
				return false;
			}

			location(array());
		}
		if ($v==0) {
			return '<a href="javascript:void(0)" onclick="'.$prefix.'_set_action(\'set_in_progress\');'.$prefix.'_set_id(\''.$record['id'].'\');'.$prefix.'_submit_form();">'.$status[$v].'</a>';
		}
		return '<a href="javascript:void(0)" class="lbOn" rel="'.$prefix.'_followups_leightbox" onMouseDown="'.$prefix.'_set_id('.$record['id'].');">'.$status[$v].'</a>';
	}

	public static function phone_bbcode($text, $param, $opt) {
		return Utils_RecordBrowserCommon::record_bbcode('phonecall', array('subject'), $text, $param, $opt);
	}

	public static function submit_phonecall($values, $mode) {
		switch ($mode) {
		case 'display':
			$values['date_and_time'] = date('Y-m-d H:i:s');
			$values['subject'] = __('Follow-up').': '.$values['subject'];
			$values['status'] = 0;
			$ret = array();
			if (ModuleManager::is_installed('CRM/Meeting')>=0) $ret['new']['event'] = '<a '.Utils_TooltipCommon::open_tag_attrs(__('New Event')).' '.Utils_RecordBrowserCommon::create_new_record_href('crm_meeting', array('title'=>$values['subject'],'permission'=>$values['permission'],'priority'=>$values['priority'],'description'=>$values['description'],'date'=>date('Y-m-d'),'time'=>date('H:i:s'),'duration'=>3600,'employees'=>$values['employees'], 'customers'=>$values['customer'],'status'=>0), 'none', false).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_Calendar','icon-small.png').'" /></a>';
			if (ModuleManager::is_installed('CRM/Tasks')>=0) $ret['new']['task'] = '<a '.Utils_TooltipCommon::open_tag_attrs(__('New Task')).' '.Utils_RecordBrowserCommon::create_new_record_href('task', array('title'=>$values['subject'],'permission'=>$values['permission'],'priority'=>$values['priority'],'description'=>$values['description'],'employees'=>$values['employees'], 'customers'=>$values['customer'],'status'=>0,'deadline'=>date('Y-m-d', strtotime('+1 day')))).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_Tasks','icon-small.png').'"></a>';
			$ret['new']['phonecall'] = '<a '.Utils_TooltipCommon::open_tag_attrs(__('New Phonecall')).' '.Utils_RecordBrowserCommon::create_new_record_href('phonecall', $values, 'none', false).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_PhoneCall','icon-small.png').'"></a>';
			$ret['new']['note'] = Utils_RecordBrowser::$rb_obj->add_note_button('phonecall/'.$values['id']);
			return $ret;
		case 'adding':
			$values['permission'] = Base_User_SettingsCommon::get('CRM_Common','default_record_permission');
			break;
		case 'edit':
			$old_vals = Utils_RecordBrowserCommon::get_record('phonecall',$values['id']);
			$old_related = $old_vals['employees'];
			if (!isset($old_vals['other_customer'])) $old_related[] = $old_vals['customer'];
		case 'added':
			if (isset($values['follow_up']))
				CRM_FollowupCommon::add_tracing_notes($values['follow_up'][0], $values['follow_up'][1], $values['follow_up'][2], 'phonecall', $values['id'], $values['subject']);
			$related = $values['employees'];
			if (!isset($values['other_customer'])) $related[] = $values['customer'];
			foreach ($related as $v) {
				if ($mode==='edit' && in_array($v, $old_related)) continue;
				if (!is_numeric($v)) {
					list($t, $id) = explode(':', $v);
				} else {
					$t = 'P';
					$id = $v;
				}
				if ($t=='P') $t = 'contact'; else $t = 'company';
				$subs = Utils_WatchdogCommon::get_subscribers($t,$id);
				foreach($subs as $s)
					Utils_WatchdogCommon::user_subscribe($s, 'phonecall',$values['id']);
			}
			if ($mode=='added') break;
		case 'add':
			if(isset($values['phone']) && $values['phone']) {
				if($values['customer']{0}=='P' && $values['phone']=='4')
				    $values['phone'] == '1';
				elseif($values['customer']{0}=='C' && $values['phone']!='4')
				    $values['phone'] == '4';
			} 
			if (isset($values['other_customer']) && $values['other_customer']) {
				$values['other_phone']=1;
				$values['customer']='';
			} else {
				$values['other_customer_name']='';
			}
			if (isset($values['other_phone']) && $values['other_phone']) $values['phone']='';
			else $values['other_phone_number']='';
		}
		return $values;
	}

	public static function watchdog_label($rid = null, $events = array(), $details = true) {
		return Utils_RecordBrowserCommon::watchdog_label(
				'phonecall',
				__('Phonecalls'),
				$rid,
				$events,
				'subject',
				$details
			);
	}
	
	public static function search_format($id) {
		$phone = self::get_phonecall($id);
		if(!$phone) return false;
		return Utils_RecordBrowserCommon::record_link_open_tag('phonecall', $phone['id']).__( 'Phonecall (attachment) #%d, %s at %s', array($phone['id'], $phone['subject'], Base_RegionalSettingsCommon::time2reg($phone['date_and_time']))).Utils_RecordBrowserCommon::record_link_close_tag();
	}

	public static function get_alarm($id) {
		$a = Utils_RecordBrowserCommon::get_record('phonecall',$id);

		if (!$a) return __('Private record');

		$ret = __('Date: %s',array(Base_RegionalSettingsCommon::time2reg($a['date_and_time'],2)))."\n";
		if($a['other_customer'])
			$contact = $a['other_customer_name'];
		else {
			list($r,$id) = explode(':',$a['customer']);
			if ($r=='P')
				$contact = CRM_ContactsCommon::contact_format_default($id,true);
			else {
				$contact = CRM_ContactsCommon::get_company($id);
				$contact = $contact['company_name'];
			}
		}
		$ret .= __('Contact: %s',array($contact))."\n";
		$ret .= __('Phone: %s',array(self::display_phone($a,true,array('id'=>'phone'))))."\n";

		return $ret.__('Subject: %s',array($a['subject']));
	}

	//////////////////////////
	// mobile devices
	public static function mobile_menu() {
		if(!Utils_RecordBrowserCommon::get_access('phonecall','browse'))
			return array();
		return array(__('Phonecalls')=>array('func'=>'mobile_phone_calls','color'=>'blue'));
	}

	public static function mobile_phone_calls() {
		$me = CRM_ContactsCommon::get_my_record();
		$defaults = array('date_and_time'=>date('Y-m-d H:i:s'), 'employees'=>array($me['id']), 'permission'=>'0', 'status'=>'0', 'priority'=>'1');
		Utils_RecordBrowserCommon::mobile_rb('phonecall',array('employees'=>array($me['id'])),array('status'=>'ASC', 'date_and_time'=>'ASC', 'subject'=>'ASC'),array(),$defaults);
	}

	public static function crm_calendar_handler($action) {
		$args = func_get_args();
		array_shift($args);
		$ret = null;
		switch ($action) {
			case 'get_all': $ret = call_user_func_array(array('CRM_PhoneCallCommon','crm_event_get_all'), $args);
							break;
			case 'update': $ret = call_user_func_array(array('CRM_PhoneCallCommon','crm_event_update'), $args);
							break;
			case 'get': $ret = call_user_func_array(array('CRM_PhoneCallCommon','crm_event_get'), $args);
							break;
			case 'delete': $ret = call_user_func_array(array('CRM_PhoneCallCommon','crm_event_delete'), $args);
							break;
			case 'new_event_types': $ret = array(array('label'=>__('Phonecall'),'icon'=>Base_ThemeCommon::get_template_file('CRM_PhoneCall','icon.png')));
							break;
			case 'new_event': $ret = call_user_func_array(array('CRM_PhoneCallCommon','crm_new_event'), $args);
							break;
			case 'view_event': $ret = call_user_func_array(array('CRM_PhoneCallCommon','crm_view_event'), $args);
							break;
			case 'edit_event': $ret = call_user_func_array(array('CRM_PhoneCallCommon','crm_edit_event'), $args);
							break;
			case 'recordset': $ret = 'phonecall';
		}
		return $ret;
	}
	public static function crm_view_event($id, $cal_obj) {
		$rb = $cal_obj->init_module('Utils_RecordBrowser', 'phonecall');
		$rb->view_entry('view', $id);
		return true;
	}
	public static function crm_edit_event($id, $cal_obj) {
		$rb = $cal_obj->init_module('Utils_RecordBrowser', 'phonecall');
		$rb->view_entry('edit', $id);
		return true;
	}
	public static function crm_new_event($timestamp, $timeless, $id, $cal_obj) {
		$x = ModuleManager::get_instance('/Base_Box|0');
		if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$me = CRM_ContactsCommon::get_my_record();
		$defaults = array('employees'=>$me['id'], 'priority'=>1, 'permission'=>0, 'status'=>0);
		$defaults['date_and_time'] = date('Y-m-d H:i:s', $timestamp);
		$x->push_main('Utils_RecordBrowser','view_entry',array('add', null, $defaults), 'phonecall');
	}

	public static function crm_event_delete($id) {
		if (!Utils_RecordBrowserCommon::get_access('phonecall','delete', self::get_phonecall($id))) return false;
		Utils_RecordBrowserCommon::delete_record('phonecall',$id);
		return true;
	}
	public static function crm_event_update($id, $start, $duration, $timeless) {
		if ($timeless) return false;
		if (!Utils_RecordBrowserCommon::get_access('phonecall','edit', self::get_phonecall($id))) return false;
		$values = array('date_and_time'=>date('Y-m-d H:i:s', $start));
		Utils_RecordBrowserCommon::update_record('phonecall', $id, $values);
		return true;
	}
	public static function crm_event_get_all($start, $end, $filter=null, $customers=null) {
		$start = date('Y-m-d',Base_RegionalSettingsCommon::reg2time($start));
		$crits = array();
		if ($filter===null) $filter = CRM_FiltersCommon::get();
		$f_array = explode(',',trim($filter,'()'));
		if($filter!='()' && $filter)
			$crits['('.'employees'] = $f_array;
		if ($customers && !empty($customers)) 
			$crits['|customer'] = $customers;
		elseif($filter!='()' && $filter) {
			$crits['|customer'] = $f_array;
			foreach ($crits['|customer'] as $k=>$v)
				$crits['|customer'][$k] = 'P:'.$v;
		}
		$crits['<=date_and_time'] = $end;
		$crits['>=date_and_time'] = $start;
		
		$ret = Utils_RecordBrowserCommon::get_records('phonecall', $crits, array(), array(), CRM_CalendarCommon::$events_limit);

		$result = array();
		foreach ($ret as $r)
			$result[] = self::crm_event_get($r);

		return $result;
	}

	public static function crm_event_get($id) {
		if (!is_array($id)) {
			$r = Utils_RecordBrowserCommon::get_record('phonecall', $id);
		} else {
			$r = $id;
			$id = $r['id'];
		}

		$next = array('type'=>__('Phonecall'));
		
		$next['id'] = $r['id'];

		$next['start'] = strtotime($r['date_and_time']);
		$next['end'] = strtotime($r['date_and_time'])+15*60;

		$next['duration'] = intval(15*60);

		$next['title'] = (string)$r['subject'];
		$next['description'] = (string)$r['description'];
		$next['color'] = 'gray';
		if ($r['status']==0 || $r['status']==1)
			switch ($r['priority']) {
				case 0: $next['color'] = 'green'; break;
				case 1: $next['color'] = 'yellow'; break;
				case 2: $next['color'] = 'red'; break;
			}
		if ($r['status']==2)
			$next['color'] = 'blue';
		if ($r['status']==3)
			$next['color'] = 'gray';

		$next['view_action'] = Utils_RecordBrowserCommon::create_record_href('phonecall', $r['id'], 'view');

		if (Utils_RecordBrowserCommon::get_access('phonecall','edit', $r)!==false)
			$next['edit_action'] = Utils_RecordBrowserCommon::create_record_href('phonecall', $r['id'], 'edit');
		else {
			$next['edit_action'] = false;
			$next['move_action'] = false;
		}
		if (Utils_RecordBrowserCommon::get_access('phonecall','delete', $r)==false)
			$next['delete_action'] = false;


/*		$r_new = $r;
		if ($r['status']==0) $r_new['status'] = 1;
		if ($r['status']<=1) $next['actions'] = array(
			array('icon'=>Base_ThemeCommon::get_template_file('CRM/Meeting', 'close_event.png'), 'href'=>self::get_status_change_leightbox_href($r_new, false, array('id'=>'status')))
		);*/

        $start_time = Base_RegionalSettingsCommon::time2reg($next['start'],2,false,false);
        $event_date = Base_RegionalSettingsCommon::time2reg($next['start'],false,3,false);

        $inf2 = array(
            __('Date')=>'<b>'.$event_date.'</b>');

		$emps = array();
		foreach ($r['employees'] as $e) {
			$e = CRM_ContactsCommon::contact_format_no_company($e, true);
			$e = str_replace('&nbsp;',' ',$e);
			if (mb_strlen($e,'UTF-8')>33) $e = mb_substr($e , 0, 30, 'UTF-8').'...';
			$emps[] = $e;
		}
		$cuss = array();
		$c = CRM_ContactsCommon::display_company_contact(array('customer'=>$r['customer']), true, array('id'=>'customer'));
		$c = str_replace('&nbsp;',' ',$c);
		if (mb_strlen($c,'UTF-8')>33) $c = mb_substr($c, 0, 30, 'UTF-8').'...';
		$cuss[] = $c;

		$inf2 += array(	__('Phonecall') => '<b>'.$next['title'].'</b>',
						__('Description')=> $next['description'],
						__('Assigned to')=> implode('<br>',$emps),
						__('Contacts')=> implode('<br>',$cuss),
						__('Status')=> Utils_CommonDataCommon::get_value('CRM/Status/'.$r['status'],true),
						__('Access')=> Utils_CommonDataCommon::get_value('CRM/Access/'.$r['permission'],true),
						__('Priority')=> Utils_CommonDataCommon::get_value('CRM/Priority/'.$r['priority'],true),
						__('Notes')=> Utils_AttachmentCommon::count('phonecall/'.$r['id'])
					);

		$next['employees'] = $r['employees'];
		$next['customer'] = $r['customer'];
		$next['status'] = $r['status']<=2?'active':'closed';
		$next['custom_tooltip'] = 
									'<center><b>'.
										__('Phonecall').
									'</b></center><br>'.
									Utils_TooltipCommon::format_info_tooltip($inf2).'<hr>'.
									CRM_ContactsCommon::get_html_record_info($r['created_by'],$r['created_on'],null,null);
		return $next;
	}
}
?>
