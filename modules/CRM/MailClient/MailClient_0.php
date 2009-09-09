<?php
/**
 * Apps/MailClient and other CRM functions connector
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license MIT
 * @version 0.1
 * @package crm-mailclient
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_MailClient extends Module {
	public function body() {
	
	}

	private function addon($where) {
		$gb = $this->init_module('Utils/GenericBrowser',null,'addon');
		$cols = array();
		$cols[] = array('name'=>$this->t('Date'), 'order'=>'delivered_on','width'=>5);
		$cols[] = array('name'=>$this->t('From'), 'order'=>'from_contact_id','width'=>15);
		$cols[] = array('name'=>$this->t('To'), 'order'=>'to_contact_id','width'=>15);
		$cols[] = array('name'=>$this->t('Subject'), 'width'=>40);
		$cols[] = array('name'=>$this->t('Attachments'), 'order'=>'uaf.original','width'=>25);
		$gb->set_table_columns($cols);

		$query = 'SELECT sticky,id,delivered_on,subject,from_contact_id,to_contact_id FROM crm_mailclient_mails WHERE ('.$where.') AND deleted=0';
		$query_lim = 'SELECT count(id) FROM crm_mailclient_mails WHERE ('.$where.') AND deleted=0';
		$gb->set_default_order(array($this->t('Date')=>'DESC'));

		$query_order = $gb->get_query_order('sticky DESC');
		$qty = DB::GetOne($query_lim);
		$query_limits = $gb->get_limit($qty);
		$ret = DB::SelectLimit($query.$query_order,$query_limits['numrows'],$query_limits['offset']);

		while($row = $ret->FetchRow()) {
			$r = $gb->get_new_row();

			$delivered_on = Base_RegionalSettingsCommon::time2reg($row['delivered_on'],0);
			$delivered_on_time = Base_RegionalSettingsCommon::time2reg($row['delivered_on'],1);
			$text = $row['subject'];
			if($row['sticky']) $text = '<img src="'.Base_ThemeCommon::get_template_file($this->get_type(),'sticky.png').'" hspace=3 align="left"> '.$text;

			$arr = array();
			$arr[] = Utils_TooltipCommon::create($delivered_on,$delivered_on_time);
			$arr[] = CRM_ContactsCommon::contact_format_default(CRM_ContactsCommon::get_contact($row['from_contact_id']));
			$arr[] = CRM_ContactsCommon::contact_format_default(CRM_ContactsCommon::get_contact($row['to_contact_id']));
			$view_href = $this->create_callback_href(array('CRM_MailClient','show_message_cb'),array($row['id']));
			$arr[] = '<a '.$view_href.'>'.$text.'</a>';
			$arr[] = $this->get_attachments($row['id']);
			$r->add_data_array($arr);
			$r->add_action($view_href,'view');
			$r->add_action($this->create_callback_href(array($this,'sticky'),array($row['id'],$row['sticky'])),($row['sticky']?'unsticky':'sticky'));
		}

		$this->display_module($gb);
	}

	public function contact_addon($arg){
		$this->addon('from_contact_id='.DB::qstr($arg['id']).' OR to_contact_id='.DB::qstr($arg['id']).' OR (SELECT 1 FROM crm_mailclient_rb_mails rb WHERE rb.mail_id=id AND rb.tab="contact" AND rb.rec_id='.DB::qstr($arg['id']).' LIMIT 1) is not null');
	}
	
	public function rb_addon($arg,$rb){
		$this->addon('(SELECT 1 FROM crm_mailclient_rb_mails rb WHERE rb.mail_id=id AND rb.tab='.DB::qstr($rb->tab).' AND rb.rec_id='.DB::qstr($arg['id']).' LIMIT 1) is not null');
	}
	
	private function get_attachments($id) {
		$attachments = '';
		$ret2 = DB::Execute('SELECT * FROM crm_mailclient_attachments WHERE mail_id=%d',array($id));
		while($attach_row = $ret2->FetchRow()) {
			$href = array('msg_id'=>$id);
			if($attach_row['cid'])
				$href['attachment_cid']=$attach_row['cid'];
			else
				$href['attachment_name']=$attach_row['name'];
			$attachments .= '<a href="'.$this->get_module_dir().'preview.php?'.http_build_query($href).'" target="_blank">'.$attach_row['name'].'</a><br>';
		}
		return $attachments;
	}
	
	public function show_message($id) {
		if($this->is_back()) {
			CRM_MailClient::pop_box();
		}

		Utils_WatchdogCommon::notified('crm_mailclient',$id);
		
		$row = DB::GetRow('SELECT headers,subject,delivered_on,from_contact_id,to_contact_id FROM crm_mailclient_mails WHERE id=%d',array($id));

		$th = $this->init_module('Base/Theme');
		$th->assign('header',$this->t('Mail from %s to %s sent on %s',array(CRM_ContactsCommon::contact_format_default(CRM_ContactsCommon::get_contact($row['from_contact_id'])),CRM_ContactsCommon::contact_format_default(CRM_ContactsCommon::get_contact($row['to_contact_id'])),Base_RegionalSettingsCommon::time2reg($row['delivered_on']))));
		
		Libs_LeightboxCommon::display('headers_leightbox','<pre>'.$row['headers'].
							'</pre>','Message headers');

		$th->assign('info_tooltip','<a '.Libs_LeightboxCommon::get_open_href('headers_leightbox').'>headers</a>');

		$th->assign('subject',$row['subject']);
		$th->assign('subject_caption',$this->t('Subject'));
		$th->assign('attachments',$this->get_attachments($id));
		$th->assign('attachments_caption',$this->t('Attachments'));
		$th->assign('body','<iframe src="'.$this->get_module_dir().'/preview.php?'.http_build_query(array('msg_id'=>$id)).'" style="width: 90%; border:0" id="crm_mailclient_view" />');

		$th->display('view');
		
		Base_ActionBarCommon::add('back','Back',$this->create_back_href());
		Base_ActionBarCommon::add('reply','Reply',$this->create_callback_href(array($this,'edit_mail'),array($id,'reply')));
		Base_ActionBarCommon::add('forward','Forward',$this->create_callback_href(array($this,'edit_mail'),array($id,'forward')));
	}
	
	public function edit_mail($id,$type) {
		$row = DB::GetRow('SELECT * FROM crm_mailclient_mails WHERE id=%d',array($id));
		$x = ModuleManager::get_instance('/Base_Box|0');
		if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$x->push_main('Apps/MailClient','edit_mail_src',array($row['headers'],$row['body'],$row['body_type'],$row['body_ctype'],null,$type,array('CRM_MailClientCommon','drop_callback')));
	}
	
	public function sticky($id,$sticky) {
		DB::Execute('UPDATE crm_mailclient_mails SET sticky=%b WHERE id=%d',array(!$sticky,$id));
	}

	public static function show_message_cb($id) {
		$x = ModuleManager::get_instance('/Base_Box|0');
		if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$x->push_main('CRM/MailClient','show_message',array($id));
	}
	
	public static function pop_box() {
		$x = ModuleManager::get_instance('/Base_Box|0');
		if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$x->pop_main();	
	}
	
	public function notify($box,$dir,$id) {
		if($this->is_back()) {
			return CRM_MailClient::pop_box();
		}

		$theme = $this->init_module('Base/Theme');
		$qf = $this->init_module('Libs/QuickForm');
		$qf->addElement('header','notification_header',$this->t('Notify this contacts'));

		$fav2 = array();
		$fav = CRM_ContactsCommon::get_contacts(array(':Fav'=>true,'!login'=>''),array('id','first_name','last_name','company_name'));
		foreach($fav as $v)
			$fav2[$v['id']] = CRM_ContactsCommon::contact_format_default($v,true);
		$rb1 = $this->init_module('Utils/RecordBrowser/RecordPicker');
		$this->display_module($rb1, array('contact' ,'to_addr_ex',array('Apps_MailClientCommon','addressbook_rp_mail'),array('!login'=>''),array('work_phone'=>false,'mobile_phone'=>false,'email'=>true,'login'=>true)));
		$theme->assign('addressbook_add_button',$rb1->create_open_link('Add contact'));
		$qf->addElement('multiselect','to_addr_ex','',$fav2);

		$qf->assign_theme('form', $theme);
		
		if($qf->validate()) {
			$mid = null;

			if(CRM_MailClientCommon::move_to_contact_action($box, $dir, $id, $mid)) {
				$u = $qf->exportValue('to_addr_ex');
				foreach($u as $user) {
					$user_login = CRM_ContactsCommon::get_contact($user);
					$user_login = $user_login['login'];
					Utils_WatchdogCommon::user_subscribe($user_login, 'crm_mailclient', $mid);
				}
				Utils_WatchdogCommon::new_event('crm_mailclient',$mid,'Mail moved to contact');
				CRM_MailClient::pop_box();
				$x = ModuleManager::get_instance('/Base_Box|0');
				if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
				$x->push_main('CRM_MailClient','show_message',array($mid));
			}
		}

		$theme->display('notification');

		Base_ActionBarCommon::add('back','Back',$this->create_back_href());
		Base_ActionBarCommon::add('save','Move & Notify',$qf->get_submit_form_href());
	}

	public function rb($box,$dir,$id,$table) {
		if($this->is_back()) {
			return CRM_MailClient::pop_box();
		}
		
		$msg = Apps_MailClientCommon::parse_message($box,$dir,$id);

		$row = DB::GetRow('SELECT format_callback,crits FROM crm_mailclient_addons WHERE tab=%s',array($table));
		$format_callback = unserialize($row['format_callback']);
		$crits = unserialize($row['crits']);
		$theme = $this->init_module('Base/Theme');
		$qf = $this->init_module('Libs/QuickForm');
		$qf->addElement('static','subject',$this->t('Subject'),$msg['subject']);
		$qf->addElement('static','from',$this->t('From'),Apps_MailClientCommon::mime_header_decode($msg['headers']['from']));
		$qf->addElement('static','to',$this->t('To'),Apps_MailClientCommon::mime_header_decode($msg['headers']['to']));
		$qf->addElement('header','record_header',$this->t('Choose record'));

		if($table == 'contact') {
			$qf->addElement('automulti', 'records', '', array('CRM_ContactsCommon','automulti_contact_suggestbox'), array(array()), array('CRM_ContactsCommon','contact_format_default'));
		} else {
			$fav2 = array();
			$fav = Utils_RecordBrowserCommon::get_records($table,array_merge(array(':Fav'=>true),$crits));
			foreach($fav as $v)
				$fav2[$v['id']] = call_user_func($format_callback,$v,true);
			$rb2 = $this->init_module('Utils/RecordBrowser/RecordPicker');
			$this->display_module($rb2, array($table ,'records',$format_callback,$crits,array()));
			$theme->assign('record_add_button',$rb2->create_open_link('Add record'));
			$qf->addElement('multiselect','records','',$fav2);
		}
		$qf->addRule('records',$this->t('Field required'),'required');

		$qf->addElement('header','notification_header',$this->t('Notify this contacts'));

		$fav2 = array();
		$fav = CRM_ContactsCommon::get_contacts(array(':Fav'=>true,'!login'=>''),array('id','first_name','last_name','company_name'));
		foreach($fav as $v)
			$fav2[$v['id']] = CRM_ContactsCommon::contact_format_default($v,true);
		$rb1 = $this->init_module('Utils/RecordBrowser/RecordPicker');
		$this->display_module($rb1, array('contact' ,'to_addr_ex',array('Apps_MailClientCommon','addressbook_rp_mail'),array('!login'=>''),array('work_phone'=>false,'mobile_phone'=>false,'email'=>true,'login'=>true)));
		$theme->assign('addressbook_add_button',$rb1->create_open_link('Add contact'));
		$qf->addElement('multiselect','to_addr_ex','',$fav2);

		$qf->assign_theme('form', $theme);
		
		if($qf->validate()) {
			$sent = false;
			if(preg_match('/^(Drafts|Sent)/',$dir))
				$sent = true;

			if($sent) {
				$addr = Apps_MailClientCommon::mime_header_decode($msg['headers']['to']);
			} else {
				$addr = Apps_MailClientCommon::mime_header_decode($msg['headers']['from']);
			}
		
			$c = CRM_MailClientCommon::resolve_contact($addr,false);
//			if(!$c) return false;
		
			$headers = '';
			foreach($msg['headers'] as $cap=>$h)
				$headers .= $cap.': '.$h."\n";
			
			$data_dir = $this->get_data_dir();
			$my_rec = CRM_ContactsCommon::get_my_record();
			if($sent) {
				$to = $c?$c['id']:null;
				$from = $my_rec['id']>=0?$my_rec['id']:null;
			} else {
				$to = $my_rec['id']>=0?$my_rec['id']:null;
				$from = $c?$c['id']:null;
			}
			DB::Execute('INSERT INTO crm_mailclient_mails(from_contact_id,to_contact_id,subject,headers,body,body_type,body_ctype,delivered_on) VALUES(%d,%d,%s,%s,%s,%s,%s,%T)',array($from,$to,Apps_MailClientCommon::mime_header_decode($msg['subject']),$headers,$msg['body'],$msg['type'],$msg['ctype'],strtotime($msg['headers']['date'])));
			$mid = DB::Insert_ID('crm_mailclient_mails','id');
			foreach($msg['attachments'] as $k=>$a) {
				DB::Execute('INSERT INTO crm_mailclient_attachments(mail_id,name,type,cid,disposition) VALUES(%d,%s,%s,%s,%s)',array($mid,$k,$a['type'],$a['id'],$a['disposition']));
				$aid = DB::Insert_ID('crm_mailclient_mails','id');
				file_put_contents($data_dir.$aid,$a['body']);
			}
			$mail_id = $mid;
			if($to!==null) Utils_WatchdogCommon::new_event('contact',$to,'N_New mail');
			if($from!==null) Utils_WatchdogCommon::new_event('contact',$from,'N_New mail');
	
			$u = $qf->exportValue('to_addr_ex');
			$r = $qf->exportValue('records');
			foreach($r as $rec) {
				if($table=='contact' && ($rec==$to || $rec==$from)) continue;
				DB::Execute('INSERT INTO crm_mailclient_rb_mails(mail_id,rec_id,tab) VALUES(%d,%d,%s)',array($mid,$rec,$table));
				Utils_WatchdogCommon::new_event($table,$rec,'N_New mail');
			}
			foreach($u as $user) {
				$user_login = CRM_ContactsCommon::get_contact($user);
				$user_login = $user_login['login'];
				Utils_WatchdogCommon::user_subscribe($user_login, 'crm_mailclient', $mid);
			}
			$caption = DB::GetOne('SELECT rtb.caption FROM recordbrowser_table_properties rtb WHERE rtb.tab=%s',array($table));
			Utils_WatchdogCommon::new_event('crm_mailclient',$mid,'Mail moved to "'.$caption.'"');
			CRM_MailClient::pop_box();
//			$x = ModuleManager::get_instance('/Base_Box|0');
//			if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
//			$x->push_main('CRM_MailClient','show_message',array($mid));
			Apps_MailClientCommon::remove_msg($box,$dir,$id);
			return;
		}

		$theme->display('rb');

		Base_ActionBarCommon::add('back','Back',$this->create_back_href());
		Base_ActionBarCommon::add('save','Attach to record',$qf->get_submit_form_href());
	}
}

?>