<?php
/**
 * Apps/MailClient and other CRM functions connector
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package crm-mailclient
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_MailClient extends Module {

	public function construct() {
		$this->lang = $this->init_module('Base/Lang');
	}

	public function body() {
	
	}

	public function contact_addon($arg){
		$gb = $this->init_module('Utils/GenericBrowser',null,'addon');
		$cols = array();
		$cols[] = array('name'=>$this->lang->t('Date'), 'order'=>'delivered_on','width'=>5);
		$cols[] = array('name'=>$this->lang->t('From'), 'order'=>'from_contact_id','width'=>15);
		$cols[] = array('name'=>$this->lang->t('To'), 'order'=>'to_contact_id','width'=>15);
		$cols[] = array('name'=>$this->lang->t('Subject'), 'width'=>40);
		$cols[] = array('name'=>$this->lang->t('Attachments'), 'order'=>'uaf.original','width'=>25);
		$gb->set_table_columns($cols);

		$query = 'SELECT sticky,id,delivered_on,subject,from_contact_id,to_contact_id FROM crm_mailclient_mails WHERE (from_contact_id='.DB::qstr($arg['id']).' OR to_contact_id='.DB::qstr($arg['id']).') AND deleted=0';
		$query_lim = 'SELECT count(id) FROM crm_mailclient_mails WHERE (from_contact_id='.DB::qstr($arg['id']).' OR to_contact_id='.DB::qstr($arg['id']).') AND deleted=0';
		$gb->set_default_order(array($this->lang->t('Date')=>'DESC'));

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
			$view_href = $this->create_callback_href(array($this,'show_message_cb'),array($row['id'],$arg));
			$arr[] = '<a '.$view_href.'>'.$text.'</a>';
			$arr[] = $this->get_attachments($row['id']);
			$r->add_data_array($arr);
			$r->add_action($view_href,'view');
			$r->add_action($this->create_callback_href(array($this,'sticky'),array($row['id'],$row['sticky'])),($row['sticky']?'unsticky':'sticky'));
		}

		$this->display_module($gb);
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
	
	public function show_message_cb($id,$contact) {
		$x = ModuleManager::get_instance('/Base_Box|0');
		if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$x->push_main('CRM/MailClient','show_message',array($id,$contact));
	}
	
	public function show_message($id,$contact) {
		if($this->is_back()) {
			$x = ModuleManager::get_instance('/Base_Box|0');
			if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
			$x->pop_main();
		}
		
		$row = DB::GetRow('SELECT headers,subject,delivered_on,from_contact_id,to_contact_id FROM crm_mailclient_mails WHERE id=%d',array($id));

		$th = $this->init_module('Base/Theme');
		$th->assign('header',$this->lang->t('Mail from %s to %s sent on %s',array(CRM_ContactsCommon::contact_format_default(CRM_ContactsCommon::get_contact($row['from_contact_id'])),CRM_ContactsCommon::contact_format_default(CRM_ContactsCommon::get_contact($row['to_contact_id'])),Base_RegionalSettingsCommon::time2reg($row['delivered_on']))));
		
		Libs_LeightboxCommon::display('headers_leightbox','<pre>'.$row['headers'].
							'</pre>','Message headers');

		$th->assign('info_tooltip','<a '.Libs_LeightboxCommon::get_open_href('headers_leightbox').'>headers</a>');

		$th->assign('subject',$row['subject']);
		$th->assign('subject_caption',$this->lang->t('Subject'));
		$th->assign('attachments',$this->get_attachments($id));
		$th->assign('attachments_caption',$this->lang->t('Attachments'));
		$th->assign('body','<iframe src="'.$this->get_module_dir().'/preview.php?'.http_build_query(array('msg_id'=>$id)).'" style="width: 90%; border:0" id="crm_mailclient_view" />');

		$th->display('view');
		
		Base_ActionBarCommon::add('back','Back',$this->create_back_href());
	}

	public function sticky($id,$sticky) {
		DB::Execute('UPDATE crm_mailclient_mails SET sticky=%b WHERE id=%d',array(!$sticky,$id));
	}
}

?>