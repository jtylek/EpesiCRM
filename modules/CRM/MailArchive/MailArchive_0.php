<?php
/**
 * Mail archive applet etc.
 * @author pbukowski@telaxus.com
 * @copyright Telaxus LLC
 * @license MIT
 * @version 0.1
 * @package epesi-CRM
 * @subpackage MailArchive
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_MailArchive extends Module {

	public function body() {
		$ret = Utils_RecordBrowserCommon::get_records('rc_accounts',array('epesi_user'=>Acl::get_user()));
		$conf = array();
		foreach($ret as $row) {
			$conf['period_'.$row['id']] = 6;
			$folders = CRM_MailCommon::get_folders($row);
			foreach($folders as $name)
				$conf['account_'.$row['id'].'_'.md5($name)] = 1;
		}
		$opts = array('id'=>'0');
        $this->applet($conf,$opts);
	}

	public function applet($conf, & $opts) {
		if($this->isset_unique_href_variable('archive_message') && $this->isset_unique_href_variable('account') && $this->isset_unique_href_variable('folder')) {
			$this->navigate('archive_message',array($this->get_unique_href_variable('account'),$this->get_unique_href_variable('folder'),$this->get_unique_href_variable('archive_message')));
			return;
		}

		Epesi::load_js('modules/CRM/MailArchive/utils.js');
		$opts['go'] = true;
		$accounts = array();
		$ret = array();
		$update_applet = '';
		foreach($conf as $key=>$on) {
			$x = explode('_',$key,3);
			if($x[0]=='account' && $on) {
				$id = $x[1];
				if(!isset($accounts[$id])) $accounts[$id] = array();
				$accounts[$id][] = $x[2];
			}
		}
		$accs = Utils_RecordBrowserCommon::get_records('rc_accounts',array('epesi_user'=>Acl::get_user(),'id'=>array_keys($accounts)));
		$id=1;
		print('<div id="mail_archive_accordion_'.$opts['id'].'">');
		foreach($accs as $row) {
			$cell_id = 'mailaccount_'.$opts['id'].'_'.$row['id'];
			$link = $this->create_unique_href(array('archive_message'=>'__MESSAGE_ID__','account'=>'__ACCOUNT_ID__','folder'=>'__FOLDER__'));

			//interval execution
			eval_js_once('setInterval(\'CRM_MailArchive.update_last_messages('.$opts['id'].' ,'.$row['id'].' ,'.json_encode($accounts[$row['id']]).','.$conf['period_'.$row['id']].',\\\''.Epesi::escapeJS(Epesi::escapeJS($link,false),false).'\\\', 0)\',60000)');

			//and now
			$update_applet .= 'CRM_MailArchive.update_last_messages('.$opts['id'].' ,'.$row['id'].' ,'.json_encode($accounts[$row['id']]).','.$conf['period_'.$row['id']].',\''.Epesi::escapeJS($link,false).'\',1);';
			print('<div id="'.$cell_id.'"></div>');
		}
		print('</div>');
		$this->js($update_applet);

		$href = $this->create_callback_href(array('Base_BoxCommon', 'push_module'), array($this->get_type(), 'account_manager', array(true)));
		$img = '<img src="' . Base_ThemeCommon::get_template_file('Base_Dashboard', 'configure.png') . '" border="0">';
		$tooltip = Utils_TooltipCommon::open_tag_attrs(__('Go to account settings'));
		$opts['actions'][] = "<a $tooltip $href>$img</a>";
	}

	public function archive_message($account,$folder,$uid) {
		if($this->is_back()) {
			$this->pop_main();
			location(array());
			return;
		}

		$mailbox = CRM_MailCommon::get_connection($account);
		$mailbox->setMailbox(mb_convert_encoding($folder, "UTF7-IMAP","UTF-8"));
	    $mailbox->setOptions(OP_READONLY);
		$mail = $mailbox->getMessageByUid($uid);
		if(!$mail) {
			Epesi::alert(__('This e-mail message was deleted or moved.'));
			$this->pop_main();
			location(array());
			return;
		}

		$headers = $mail->getHeaders();
		$message_id = str_replace(array('<','>'),'',$headers->message_id);

		$archived = Utils_RecordBrowserCommon::get_records('rc_mails',array('message_id'=>$message_id));
		if($archived) $archived = array_shift($archived);

		$subject = CRM_MailCommon::decode_mime_header($mail->getSubject());

		$body = wordwrap(htmlentities($mail->getMessageBody()),600,'###cut_here###');
		$cut_here = strpos($body,'###cut_here###');
		if($cut_here!==false) $body = substr($body,0,$cut_here).'...';

		$contacts_tmp = $mail->getAddresses('to');
		$contacts_tmp[] = $mail->getAddresses('from');
		$contacts = array();
		foreach($contacts_tmp as $contact_idx => $contact) {
			if(!isset($contact['address'])) {
				continue;
			}
			$addr = $contact['address'];
			$contact_id = CRM_MailCommon::look_contact($addr);
			if($contact_id)	$contacts = array_merge($contacts,$contact_id);
		}
		$related_str = array();
		foreach($contacts as $contact) {
			list($tab,$cid) = explode('/',$contact);
			$related_str[] = Utils_RecordBrowserCommon::create_default_linked_label($tab, $cid);
		}
		if($archived) foreach($archived['related'] as $ar_rel) {
			list($tab,$cid) = explode('/',$ar_rel);
			$related_str[] = Utils_RecordBrowserCommon::create_default_linked_label($tab, $cid);
		}

		$f = $this->init_module(Libs_QuickForm::module_name(),__('Archiving e-mail'),'archive_mail');
        $f->addElement('header',null,__('Archive e-mail'));
		$f->addElement('static','subject','<strong>'.__('Subject').'</strong>',$subject);
		$f->addElement('static','body','<strong>'.__('Body Excerpt').'</strong>',str_replace(PHP_EOL,"<br />",$body));
		if($related_str) $f->addElement('static','related','<strong>'.__('Related').'</strong>',implode('<br/>',$related_str));

		$fake_rb = $this;
		$fake_rb->tab = '__RECORDS__';
		$fake_rb->record = array('link'=>array());
		$desc = array('name' => 'Link', 'id' => 'link', 'type' => 'multiselect', 'visible' => 1, 'required' => 1, 'extra' => 0, 'active' => 1, 'export' => 1, 'tooltip' => 0, 'position' => 0, 'processing_order' => 0, 'filter' => 0, 'style' =>'', 'param' => '__RECORDSETS__::;CRM_MailArchiveCommon::link_to_crits', 'help' =>'' , 'ref_table' => '__RECORDSETS__', 'ref_field' => '', 'commondata' => '');
		Utils_RecordBrowserCommon::QFfield_select($f,'link',__('Link to'),'edit',array(),$desc, $fake_rb);

        Utils_ShortcutCommon::add(array('Ctrl','S'), 'function(){'.$f->get_submit_form_js().'}');

        Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
        Base_ActionBarCommon::add('save', __('Save'), $f->get_submit_form_href());

        if($f->validate()) {
            $vals = $f->exportValues();

			$attachments = array();
			$attachments_tmp = $mail->getAttachments();
			if($attachments_tmp) foreach($attachments_tmp as $attachment) {
				$structure = $attachment->getStructure();
				$attachments[] = array('type'=>$attachment->getMimeType(),'filename'=>$attachment->getFileName(),'mime_id'=>md5(serialize($attachment)),'attachment'=>$structure->ifdisposition?($structure->disposition=='attachment'?1:0):0,'content'=>$attachment->getData());
			}

			if($archived) {
				Utils_RecordBrowserCommon::update_record('rc_mails',$archived['id'],array('related'=>array_unique(array_merge($archived['related'],$vals['link']))));
			} else CRM_MailCommon::archive_message($message_id,isset($headers->references)?$headers->references:'',$contacts,$mail->getDate(),$subject,$mail->getMessageBody(1),$mail->getRawHeaders(),imap_utf8($mail->getAddresses('from',1)),imap_utf8($mail->getAddresses('to',1)),Base_AclCommon::get_user(),$attachments,$vals['link']);

			$this->pop_main();
			location(array());
        } else {
            $f->display();
		}
	}
    public function navigate($func, $params = array()) {
        $x = ModuleManager::get_instance('/Base_Box|0');
        if (!$x)
            trigger_error('There is no base box module instance', E_USER_ERROR);
        $x->push_main($this->get_type(), $func, $params);
        return false;
    }

    public function pop_main($i = 1) {
        $x = ModuleManager::get_instance('/Base_Box|0');
        if (!$x)
            trigger_error('There is no base box module instance', E_USER_ERROR);
        $x->pop_main($i);
    }

	public function caption() {
		return __('Archive e-mail');
	}
}

?>
