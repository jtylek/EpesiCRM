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

class CRM_MailClientCommon extends ModuleCommon {
	private static $my_rec;

	public static function resolve_contact($addr,$display_errors=true) {
		if(preg_match('/<(.+)>$/',$addr,$reqs))
			$addr = trim($reqs[1]);
		if(preg_match('/([0-9]+)@epesi_(contact|user)$/',$addr,$reqs)) {
			switch($reqs[2]) {
				case 'contact':
					$c = array(CRM_ContactsCommon::get_contact($reqs[1]));
					break;
				case 'user':
					$c = CRM_ContactsCommon::get_contacts(array('login'=>$reqs[1]));
			}
		} else
			$c = CRM_ContactsCommon::get_contacts(array('email'=>$addr));
		if(empty($c)) {
			if($display_errors)
				Epesi::alert(Base_LangCommon::ts('CRM_MailClient','Contact not found'));
			return false;
		}
		if(count($c)!==1) {
			if($display_errors)
				Epesi::alert(Base_LangCommon::ts('CRM_MailClient','Found more then one contact with specified mail address'));
			return false;
		}
		return array_pop($c);
	}

	public static function move_to_contact_action($box, $dir, $id, & $mail_id=null) {
		$sent = false;
		if(preg_match('/^(Drafts|Sent)/',$dir))
			$sent = true;

		$msg = Apps_MailClientCommon::parse_message($box,$dir,$id);
		if($sent) {
			$addr = Apps_MailClientCommon::mime_header_decode($msg['headers']['to']);
		} else {
			$addr = Apps_MailClientCommon::mime_header_decode($msg['headers']['from']);
		}
		
		$c = self::resolve_contact($addr);
		if(!$c) return false;
		
		$headers = '';
		foreach($msg['headers'] as $cap=>$h)
			$headers .= $cap.': '.$h."\n";
			
		$data_dir = self::Instance()->get_data_dir();
		if(!isset(self::$my_rec))
			self::$my_rec = CRM_ContactsCommon::get_my_record();
		if($sent) {
			$to = $c['id'];
			$from = self::$my_rec['id'];
		} else {
			$to = self::$my_rec['id'];
			$from = $c['id'];
		}
		DB::Execute('INSERT INTO crm_mailclient_mails(from_contact_id,to_contact_id,subject,headers,body,body_type,body_ctype,delivered_on) VALUES(%d,%d,%s,%s,%s,%s,%s,%T)',array($from,$to,Apps_MailClientCommon::mime_header_decode($msg['subject']),$headers,$msg['body'],$msg['type'],$msg['ctype'],strtotime($msg['headers']['date'])));
		$mid = DB::Insert_ID('crm_mailclient_mails','id');
		foreach($msg['attachments'] as $k=>$a) {
			DB::Execute('INSERT INTO crm_mailclient_attachments(mail_id,name,type,cid,disposition) VALUES(%d,%s,%s,%s,%s)',array($mid,$k,$a['type'],$a['id'],$a['disposition']));
			$aid = DB::Insert_ID('crm_mailclient_mails','id');
			file_put_contents($data_dir.$aid,$a['body']);
		}
		$mail_id = $mid;
		Utils_WatchdogCommon::new_event('contact',$to,'N_New mail');
		Utils_WatchdogCommon::new_event('contact',$from,'N_New mail');
		return true;
	}
	
	public static function move_to_rb_action($box, $dir, $id, $table) {
		$x = ModuleManager::get_instance('/Base_Box|0');
		if (!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$x->push_main('CRM/MailClient','rb',array($box,$dir,$id,$table));
		return true;
	}
	
	public static function goto_action($box,$dir,$id) {
		$sent = false;
		if(preg_match('/^(Drafts|Sent)/',$dir))
			$sent = true;

		$msg = Apps_MailClientCommon::parse_message($box,$dir,$id);
		if($sent)
			$addr = Apps_MailClientCommon::mime_header_decode($msg['headers']['to']);
		else
			$addr = Apps_MailClientCommon::mime_header_decode($msg['headers']['from']);

		$c = self::resolve_contact($addr);
		if(!$c) return false;

		$x = ModuleManager::get_instance('/Base_Box|0');
		if (!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$x->push_main('Utils/RecordBrowser','view_entry',array('view', $c['id']),array('contact'));
		return true;
	}
	
	public static function move_to_contact_and_notify_action($box,$dir,$id) {
		$sent = false;
		if(preg_match('/^(Drafts|Sent)/',$dir))
			$sent = true;

		$msg = Apps_MailClientCommon::parse_message($box,$dir,$id);
		if($sent) {
			$addr = Apps_MailClientCommon::mime_header_decode($msg['headers']['to']);
		} else {
			$addr = Apps_MailClientCommon::mime_header_decode($msg['headers']['from']);
		}
		
		$c = self::resolve_contact($addr);
		if(!$c) return false;

		$x = ModuleManager::get_instance('/Base_Box|0');
		if (!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$x->push_main('CRM/MailClient','notify',array($box,$dir,$id));
		return true;
	}

	public static function mail_actions() {
		$ret = array('Go to contact'=>array('func'=>array('CRM_MailClientCommon','goto_action'),'delete'=>0));
		if(!isset(self::$my_rec))
			self::$my_rec = CRM_ContactsCommon::get_my_record();
		if(self::$my_rec['id']!==-1) {
			$ret['Move to associated contact']=array('func'=>array('CRM_MailClientCommon','move_to_contact_action'),'delete'=>1);
			$ret['Move to associated contact & notify employees']=array('func'=>array('CRM_MailClientCommon','move_to_contact_and_notify_action'));
		}
		$rbs = DB::GetAssoc('SELECT rtb.tab,rtb.caption FROM recordbrowser_table_properties rtb INNER JOIN crm_mailclient_addons x ON x.tab=rtb.tab');
		foreach($rbs as $table=>$cap) {
			$ret['Move to "'.$cap.'"']=array('func'=>array('CRM_MailClientCommon','move_to_rb_action'),'args'=>$table);
		}

		return $ret;
	}

	public static function watchdog_label($rid = null, $events = array()) {
		$ret = array('category'=>Base_LangCommon::ts('CRM_MailClient', 'Mails'));
		if ($rid!==null) {
			$title = DB::GetOne('SELECT subject FROM crm_mailclient_mails WHERE id=%d',array($rid));
			if ($title===false || $title===null)
				return null;
			$ret['view_href'] = Module::create_href(array('crm_mailclient_watchdog_view_event'=>$rid));
			if (isset($_REQUEST['crm_mailclient_watchdog_view_event'])
				&& $_REQUEST['crm_mailclient_watchdog_view_event']==$rid) {
				unset($_REQUEST['crm_mailclient_watchdog_view_event']);
				$x = ModuleManager::get_instance('/Base_Box|0');
				if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
				$x->push_main('CRM_MailClient','show_message',array($rid));
			}
			$ret['title'] = '<a '.$ret['view_href'].'>'.$title.'</a>';
			$events_display = array();
			foreach ($events as $v) {
				$events_display[] = '<b>'.Base_LangCommon::ts('CRM_MailClient',$v).'</b>';	
			}
			$ret['events'] = implode('<hr>',array_reverse($events_display));
		}
		return $ret;
	}

	public static function drop_callback($dest_id,$dir,$mid) {
		$new_id = null;
		$msg = Apps_MailClientCommon::parse_message($dest_id,$dir,$mid);
		$ret = CRM_MailClientCommon::move_to_contact_action($msg,$dir,$new_id);
		if($ret)
			Apps_MailClientCommon::remove_msg($dest_id, $dir, $mid); //TODO: cos nie usuwa
		$x = ModuleManager::get_instance('/Base_Box|0');
		if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$x->pop_main(2);	
	}
	
	public static function new_addon($tab,$format_callback,$crits=array()) {
		DB::Execute('INSERT INTO crm_mailclient_addons(tab,format_callback,crits) VALUES (%s,%s,%s)',array($tab,serialize($format_callback),serialize($crits)));
		Utils_RecordBrowserCommon::new_addon($tab, 'CRM/MailClient', 'rb_addon', 'Mails');
	}

	public static function delete_addon($tab) {
		DB::Execute('DELETE FROM crm_mailclient_addons WHERE tab=%s',array($tab));
		Utils_RecordBrowserCommon::delete_addon($tab, 'CRM/MailClient', 'rb_addon', 'Mails');
	}
}

?>