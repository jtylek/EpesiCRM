<?php
/**
 * Fax abstraction layer module
 * @author pbukowski@telaxus.com
 * @copyright Telaxus LLC
 * @license MIT
 * @version 0.1
 * @package epesi-CRM
 * @subpackage Fax
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Fax extends Module {

	public function body() {
		$qf = $this->init_module('Libs/QuickForm',null,'provider');
		
		list($providers,$providers_arr) = self::get_providers();

		if(empty($providers)) {
			print($this->t('No fax providers installed or configured.'));
			return;
		}
		$provider = & $this->get_module_variable('provider',current(array_keys($providers)));

		$qf->addElement('select','provider',$this->t('Provider'),$providers,array('onChange'=>$qf->get_submit_form_js()));
		$qf->setDefaults(array('provider'=>$provider));
		
		if($qf->validate()) {
			$provider = $qf->exportValue('provider');
		}
		$qf->display();

		if(!isset($providers_arr[$provider])) {
			print($this->t('Invalid fax provider.'));
			return;
		}
		
		set_time_limit(0);
		$tb = & $this->init_module('Utils/TabbedBrowser');
		if(isset($providers_arr[$provider]['get_received_count_func']) && isset($providers_arr[$provider]['get_received_func']))
			$tb->set_tab('Received', array($this,'received_tab'),array(array($provider.'Common',$providers_arr[$provider]['get_received_count_func']),array($provider.'Common',$providers_arr[$provider]['get_received_func'])));
		if(isset($providers_arr[$provider]['get_queue_count_func']) && isset($providers_arr[$provider]['get_queue_func']) && isset($providers_arr[$provider]['queue_statuses']))
			$tb->set_tab('Current Queue', array($this,'queue_tab'),array(array($provider.'Common',$providers_arr[$provider]['get_queue_count_func']),array($provider.'Common',$providers_arr[$provider]['get_queue_func']),$providers_arr[$provider]['queue_statuses']));
		if(isset($providers_arr[$provider]['get_sent_count_func']) && isset($providers_arr[$provider]['get_sent_func']) && isset($providers_arr[$provider]['sent_statuses']))
			$tb->set_tab('Sent', array($this,'sent_tab'),array(array($provider.'Common',$providers_arr[$provider]['get_sent_count_func']),array($provider.'Common',$providers_arr[$provider]['get_sent_func']),$providers_arr[$provider]['sent_statuses']));
		$this->display_module($tb);
		$tb->tag();
		
		Base_ActionBarCommon::add('send','Send file',$this->create_callback_href(array($this,'send_file_tab')));
	}
	
	public function received_tab($count_f,$get_f) {
		$t = time();
		$start = & $this->get_module_variable('start',date('Y-m-d', $t - (7 * 24 * 60 * 60)));
		$end = & $this->get_module_variable('end',date('Y-m-d',$t));
		$offset = & $this->get_module_variable('rec_offset',0);
		
		$form = $this->init_module('Libs/QuickForm');
		$theme =  $this->pack_module('Base/Theme',null,null,null,'rec');
		
		$form->addElement('datepicker', 'start', $this->t('From'));
		$form->addElement('datepicker', 'end', $this->t('To'));
		$form->addElement('submit', 'submit_button', $this->ht('Show'));
		$form->addRule('start', 'Field required', 'required');
		$form->addRule('end', 'Field required', 'required');
		$form->setDefaults(array('start'=>$start, 'end'=>$end));

		if($form->validate()) {
			$data = $form->exportValues();
			$start = $data['start'];
			$end = $data['end'];
			$end = date('Y-m-d',strtotime($end)+86400);
			$offset = 0;
		}

		$form->assign_theme('form', $theme);

		$m = & $this->init_module('Utils/GenericBrowser',null,'rec');
 		$m->set_table_columns(array(
							  array('name'=>'From','width'=>30,'order'=>'fromNumber'),
							  array('name'=>'To','width'=>30,'order'=>'toNumber'),
							  array('name'=>'Date','width'=>10,'order'=>'receivedDate'),
							  array('name'=>'File','width'=>30)
							  ));
		$m->set_default_order(array('Date'=>'DESC'));

		$count = call_user_func($count_f,$start,$end);
		if($count===false) {
			$count = 0;
		}

		$limits = $m->get_limit($count);
		$order = $m->get_order();
		if($count!=0) {
			$data = call_user_func($get_f,$start,$end,$order[0]['order'],$order[0]['direction'],$limits['numrows'],(string)($limits['offset']+1));
		} 
		if($count==0 || $data===false) {
			$data = array();		
		}
		foreach($data as $row) {
			$from_rec = CRM_ContactsCommon::get_contacts(array('fax'=>$row['fromNumber']));
			foreach($from_rec as &$rec)
				$rec = CRM_ContactsCommon::contact_format_default($rec);
			$from_rec_comp = CRM_ContactsCommon::get_companies(array('fax'=>$row['fromNumber']));
			foreach($from_rec_comp as $rec)
				$from_rec[] = Utils_RecordBrowserCommon::create_linked_label('company', 'Company Name', $rec);

			$to_rec = CRM_ContactsCommon::get_contacts(array('fax'=>$row['toNumber']));
			foreach($to_rec as &$rec)
				$rec = CRM_ContactsCommon::contact_format_default($rec);
			$to_rec_comp = CRM_ContactsCommon::get_companies(array('fax'=>$row['toNumber']));
			foreach($to_rec_comp as $rec)
				$to_rec[] = Utils_RecordBrowserCommon::create_linked_label('company', 'Company Name', $rec);
			
	 		$m->add_row((empty($from_rec)?$row['fromNumber']:' ('.implode(', ',$from_rec).')'),
				    (empty($to_rec)?$row['toNumber']:' ('.implode(', ',$to_rec).')'),
				    Base_RegionalSettingsCommon::time2reg($row['receivedDate']),
				    '<a href="'.$row['fileUrl'].'" target="_blank">'.basename($row['fileUrl']).'</a>');
		}
 		$theme->assign('table_data',$this->get_html_of_module($m));

		$theme->display();
	

	}

	public function sent_tab($count_f,$get_f,$statuses) {
		$t = time();
		$start = & $this->get_module_variable('start',date('Y-m-d', $t - (7 * 24 * 60 * 60)));
		$end = & $this->get_module_variable('end',date('Y-m-d',$t));
		$status = & $this->get_module_variable('sent_status',current(array_keys($statuses)));
		$offset = & $this->get_module_variable('sent_offset',0);
		
		$form = $this->init_module('Libs/QuickForm');
		$theme =  $this->pack_module('Base/Theme');
		
		$form->addElement('select','status',$this->t('Status'),$statuses);
		
		$form->addElement('datepicker', 'start', $this->t('From'));
		$form->addElement('datepicker', 'end', $this->t('To'));
		$form->addElement('submit', 'submit_button', $this->ht('Show'));
		$form->addRule('start', 'Field required', 'required');
		$form->addRule('status', 'Field required', 'required');
		$form->addRule('end', 'Field required', 'required');
		$form->setDefaults(array('status'=>$status, 'start'=>$start, 'end'=>$end));

		if($form->validate()) {
			$data = $form->exportValues();
			$start = $data['start'];
			$end = $data['end'];
			$end = date('Y-m-d',strtotime($end)+86400);
			if(array_key_exists($data['status'],$statuses))
				$status = $data['status'];
			$offset = 0;
		}

		$form->assign_theme('form', $theme);

		$m = & $this->init_module('Utils/GenericBrowser',null,'sent');
 		$m->set_table_columns(array(
							  array('name'=>'To','width'=>30,'order'=>'toNumber'),
							  array('name'=>'Status','width'=>10),
							  array('name'=>'Date','width'=>10,'order'=>'sentDate'),
							  array('name'=>'Pages','width'=>10),
							  array('name'=>'Cost','width'=>10),
							  array('name'=>'File','width'=>30)
							  ));
		$m->set_default_order(array('Date'=>'DESC'));

		$count = call_user_func($count_f,$start,$end,$status);
		if($count===false) {
			$count = 0;
		}

		$limits = $m->get_limit($count);
		$order = $m->get_order();
		if($count!=0) {
			$data = call_user_func($get_f,$start,$end,$status,$order[0]['order'],$order[0]['direction'],$limits['numrows'],(string)($limits['offset']+1));
		}
		if($count==0 || $data===false) {
			$data = array();
		}
		foreach($data as $row) {
			$from_rec = CRM_ContactsCommon::get_contacts(array('fax'=>$row['toNumber']));
			foreach($from_rec as &$rec)
				$rec = CRM_ContactsCommon::contact_format_default($rec);
			$from_rec_comp = CRM_ContactsCommon::get_companies(array('fax'=>$row['toNumber']));
			foreach($from_rec_comp as $rec)
				$from_rec[] = Utils_RecordBrowserCommon::create_linked_label('company', 'Company Name', $rec);
			
	 		$m->add_row((empty($from_rec)?$row['toNumber']:' ('.implode(', ',$from_rec).')'),
				    Utils_TooltipCommon::create($statuses[$row['faxStatus']],$row['faxStatusDetails']),
				    Base_RegionalSettingsCommon::time2reg($row['sentDate']),
				    $row['noPages'],$row['sentCost'],'<a href="'.$row['fileUrl'].'" target="_blank">'.$row['fileName'].'</a>');
		}
 		$theme->assign('table_data',$this->get_html_of_module($m));

		$theme->display();
	
	}
	
	public function queue_tab($count_f,$get_f,$statuses) {
		$t = time();
		$status = & $this->get_module_variable('queue_status',current(array_keys($statuses)));
		$offset = & $this->get_module_variable('queue_offset',0);
		
		$form = $this->init_module('Libs/QuickForm');
		$theme =  $this->pack_module('Base/Theme');
		
		$form->addElement('select','status',$this->t('Status'),$statuses);
		
		$form->addElement('submit', 'submit_button', $this->ht('Show'));
		$form->addRule('status', 'Field required', 'required');
		$form->setDefaults(array('status'=>$status));

		if($form->validate()) {
			$data = $form->exportValues();
			if(array_key_exists($data['status'],$statuses))
				$status = $data['status'];
			$offset = 0;
		}

		$form->assign_theme('form', $theme);

		$m = & $this->init_module('Utils/GenericBrowser',null,'queue');
 		$m->set_table_columns(array(
							  array('name'=>'To','width'=>30,'order'=>'toNumber'),
							  array('name'=>'Status','width'=>10),
							  array('name'=>'Date','width'=>10,'order'=>'creationDate'),
							  array('name'=>'File','width'=>30,'order'=>'fileName')
							  ));
		$m->set_default_order(array('Date'=>'DESC'));

		$count = call_user_func($count_f,$status);
		if($count===false) {
			$count = 0;
		}

		$limits = $m->get_limit($count);
		$order = $m->get_order();
		if($count!=0) {
			$data = call_user_func($get_f,$status,$order[0]['order'],$order[0]['direction'],$limits['numrows'],(string)($limits['offset']+1));
		}
		if($count==0 || $data===false) {
			$data = array();
		}
		foreach($data as $row) {
			$from_rec = CRM_ContactsCommon::get_contacts(array('fax'=>$row['toNumber']));
			foreach($from_rec as &$rec)
				$rec = CRM_ContactsCommon::contact_format_default($rec);
			$from_rec_comp = CRM_ContactsCommon::get_companies(array('fax'=>$row['toNumber']));
			foreach($from_rec_comp as $rec)
				$from_rec[] = Utils_RecordBrowserCommon::create_linked_label('company', 'Company Name', $rec);
			
	 		$m->add_row((empty($from_rec)?$row['toNumber']:' ('.implode(', ',$from_rec).')'),
				    $statuses[$row['faxStatus']],
				    Base_RegionalSettingsCommon::time2reg($row['creationDate']),
				    '<a href="'.$row['fileUrl'].'" target="_blank">'.$row['fileName'].'</a>');
		}
 		$theme->assign('table_data',$this->get_html_of_module($m));

		$theme->display();
	
	
	}
	
	private $back_from_send_file = false;
	public function send_file_tab() {
		if($this->is_back()) return false;
	
		$form = & $this->init_module('Utils/FileUpload',array(false));
		$form->addElement('header', 'upload', $this->t('Select file'));

		$form->add_upload_element();

		$s = HTML_QuickForm::createElement('button',null,$this->t('Send fax'),$form->get_submit_form_href());
		$c = HTML_QuickForm::createElement('button',null,$this->t('Cancel'),$this->create_back_href());
		$form->addGroup(array($s,$c));

		$this->display_module($form, array( array($this,'submit_fax_file') ));
		if($this->back_from_send_file) return false;
		return true;
	}
	
	public function submit_fax_file($file,$oryg,$data) {
		CRM_FaxCommon::fax_file($file,$oryg);
		$this->back_from_send_file = true;
	}
	
	private static function get_providers($file=null) {
		$providers_arr = ModuleManager::call_common_methods('fax_provider',true,array($file));
		$providers = array();
		foreach($providers_arr as $module=>$arr) {
			if(!$arr) {
				unset($providers_arr[$module]);
				continue;
			}
			$providers[$module] = $arr['name'];
		}
		return array($providers,$providers_arr);
	}
	
	public function send($file) {
		if($this->is_back()) {
		        return $this->go_back($file);
		}
		$qf = $this->init_module('Libs/QuickForm',null,'send_fax');
		
		list($providers,$providers_arr) = self::get_providers($file);
		if(empty($providers)) {
			$this->go_back($file);
			Epesi::alert($this->ht('No fax providers installed or configured for this type of file.'));
			return;
		}
		$qf->addElement('header',null,$this->t('Faxing file: %s',array(basename($file))));
		$qf->addElement('select','provider',$this->t('Provider'),$providers);
		
		$qf->addElement('header',null,$this->t('Contact'));
		$fav_contact = CRM_ContactsCommon::get_contacts(array(':Fav'=>true,'!fax'=>''),array('id','login','first_name','last_name','company_name'));
		$fav_contact2 = array();
		foreach($fav_contact as $v)
			$fav_contact2[$v['id']] = CRM_ContactsCommon::contact_format_default($v,true);
		$rb_contact = $this->init_module('Utils/RecordBrowser/RecordPicker');
		$this->display_module($rb_contact, array('contact' ,'dest_contact',array('CRM_FaxCommon','rpicker_contact_format'),array('!fax'=>''),array('fax'=>true)));
		$qf->addElement('multiselect','dest_contact','',$fav_contact2);
		$qf->addElement('static',null,$rb_contact->create_open_link('Add contact'));

		$qf->addElement('header',null,$this->t('Company'));
		$fav_company = CRM_ContactsCommon::get_companies(array(':Fav'=>true,'!fax'=>''),array('id','company_name'));
		$fav_company2 = array();
		foreach($fav_company as $v)
			$fav_company2[$v['id']] = $v['company_name'];
		$rb_company = $this->init_module('Utils/RecordBrowser/RecordPicker');
		$this->display_module($rb_company, array('company' ,'dest_company',array('CRM_FaxCommon','rpicker_company_format'),array('!fax'=>''),array('fax'=>true)));
		$qf->addElement('multiselect','dest_company','',$fav_company2);
		$qf->addElement('static',null,$rb_company->create_open_link('Add company'));

		$qf->addElement('header',null,$this->t('Other'));
		$qf->addElement('text','dest_other',$this->t('Other fax numbers (comma separated)'));

		$qf->addFormRule(array($this,'check_numbers'));
		
		if($qf->validate()) {
			$data = $qf->exportValues();
			if(!isset($providers_arr[$data['provider']]['send_func'])) {
				Epesi::alert($this->ht('Invalid fax provider.'));
			} else {
				$fax_func = array($data['provider'].'Common',$providers_arr[$data['provider']]['send_func']);
				$numbers = array();
				$contacts = Utils_RecordBrowserCommon::get_records('contact',array('id'=>$data['dest_contact']),array('fax'));
				foreach($contacts as $row)
					$numbers[] = $row['fax'];
					
				$companies = Utils_RecordBrowserCommon::get_records('company',array('id'=>$data['dest_company']),array('fax'));
				foreach($companies as $row)
					$numbers[] = $row['fax'];
				$numbers += explode(',',$data['dest_other']);
				$ret = call_user_func($fax_func,$file,$numbers);
				if($ret)
					return $this->go_back($file);
			}
		}
		$qf->display();
		
		Base_ActionBarCommon::add('send','Send',$qf->get_submit_form_href());
		Base_ActionBarCommon::add('back','Back',$this->create_back_href());
	}
	
	public function check_numbers($arg) {
		if((!isset($arg['dest_contact']) || empty($arg['dest_contact'])) && 
		    (!isset($arg['dest_company']) || empty($arg['dest_company'])) && 
		    (!isset($arg['dest_other']) || trim($arg['dest_other'])==''))
			return array('dest_contact'=>'Please select at least one fax number');
		return true;
	}
	
	public function go_back($file) {
		unlink($file);
		
		$x = ModuleManager::get_instance('/Base_Box|0');
		if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$x->pop_main();
	}

}

?>