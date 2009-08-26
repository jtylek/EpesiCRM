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

	private static function resolve_contact($addr) {
		if(ereg('<(.+)>$',$addr,$reqs))
			$addr = trim($reqs[1]);
		if(ereg('([0-9]+)@epesi_(contact|user)$',$addr,$reqs)) {
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
			Epesi::alert(Base_LangCommon::ts('CRM_MailClient','Contact not found'));
			return false;
		}
		if(count($c)!==1) {
			Epesi::alert(Base_LangCommon::ts('CRM_MailClient','Found more then one contact with specified mail address'));
			return false;
		}
		return array_pop($c);
	}

	public static function move_action($msg, $dir, & $mail_id=null) {
		$sent = false;
		if(ereg('^(Drafts|Sent)',$dir))
			$sent = true;

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
	
	public static function goto_action($msg,$dir) {
		$sent = false;
		if(ereg('^(Drafts|Sent)',$dir))
			$sent = true;

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
	
	public static function move_and_notify_action($msg,$dir) {
		$mid = null;
		if(!self::move_action($msg, $dir, $mid)) return false;
		$x = ModuleManager::get_instance('/Base_Box|0');
		if (!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$x->push_main('CRM/MailClient','notify',array($mid));
		return true;
	}

	public static function mail_actions() {
		$ret = array('Go to contact'=>array('func'=>array('CRM_MailClientCommon','goto_action'),'delete'=>0));
		if(!isset(self::$my_rec))
			self::$my_rec = CRM_ContactsCommon::get_my_record();
		if(self::$my_rec['id']!==-1) {
			$ret['Move to CRM']=array('func'=>array('CRM_MailClientCommon','move_action'),'delete'=>1);
			$ret['Move to CRM & notify']=array('func'=>array('CRM_MailClientCommon','move_and_notify_action'),'delete'=>1);
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
		$ret = CRM_MailClientCommon::move_action($msg,$dir,$new_id);
		if($ret)
			Apps_MailClientCommon::remove_msg($dest_id, $dir, $mid); //TODO: cos nie usuwa
		$x = ModuleManager::get_instance('/Base_Box|0');
		if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$x->pop_main(2);	
	}
}

?>