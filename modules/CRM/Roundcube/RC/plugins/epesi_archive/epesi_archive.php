<?php
class epesi_archive extends rcube_plugin
{
  public $task = 'mail';
  public $archive_mbox = 'Epesi Archive';
  public $archive_sent_mbox = 'Epesi Archive Sent';

  function init()
  {
    global $account;
    

    $rcmail = rcmail::get_instance();
    $this->register_action('plugin.epesi_archive', array($this, 'request_action'));
    
    //register hook to archive just sent mail
    $this->add_hook('attachments_cleanup', array($this, 'auto_archive'));
    if(!isset($_SESSION['epesi_auto_archive']))
        $_SESSION['epesi_auto_archive'] = isset($account['f_archive_on_sending']) && $account['f_archive_on_sending']?1:0;

    $this->include_script('archive.js');
    $skin_path = $rcmail->config->get('skin_path');
    $this->add_texts('localization', true);

    $this->add_hook('messages_list', array($this, 'list_messages'));
    
    if($rcmail->action == 'compose') {
        $this->add_button(
        array(
            'command' => 'plugin.epesi_auto_archive',
            'imageact' => $skin_path.'/archive_'.($_SESSION['epesi_auto_archive']?'act':'pas').'.png',
            'title' => 'buttontitle_compose',
            'domain' => $this->ID,
            'id'=>'epesi_auto_archive_button'
        ),
        'toolbar');
    }

    if ($rcmail->action == '' || $rcmail->action == 'show') {
      $this->add_button(
        array(
            'command' => 'plugin.epesi_archive',
            'imagepas' => $skin_path.'/archive_pas.png',
            'imageact' => $skin_path.'/archive_act.png',
            'title' => 'buttontitle',
            'domain' => $this->ID,
        ),
        'toolbar');
	
      if(!isset($account['f_use_epesi_archive_directories']) || !$account['f_use_epesi_archive_directories']) return;
      
      // register hook to localize the archive folder
      $this->add_hook('render_mailboxlist', array($this, 'render_mailboxlist'));

      // set env variable for client
      $rcmail->output->set_env('archive_mailbox', $this->archive_mbox);
      $rcmail->output->set_env('archive_mailbox_icon', $this->url($skin_path.'/foldericon.png'));

      $rcmail->output->set_env('archive_sent_mailbox', $this->archive_sent_mbox);
      $rcmail->output->set_env('archive_sent_mailbox_icon', $this->url($skin_path.'/foldericon.png'));

      // add archive folder to the list of default mailboxes
      if (($default_folders = $rcmail->config->get('default_imap_folders')) && !in_array($this->archive_mbox, $default_folders)) {
        $default_folders[] = $this->archive_mbox;
        $rcmail->config->set('default_imap_folders', $default_folders);
      }

      if (($default_folders = $rcmail->config->get('default_imap_folders')) && !in_array($this->archive_sent_mbox, $default_folders)) {
        $default_folders[] = $this->archive_sent_mbox;
        $rcmail->config->set('default_imap_folders', $default_folders);
      }

//      if(!$rcmail->config->get('create_default_folders'))
      $this->add_hook('mailboxes_list', array($this, 'add_mailbox'));
    }
  }

  function render_mailboxlist($p)
  {
    $rcmail = rcmail::get_instance();

    // set localized name for the configured archive folder
    if (isset($p['list'][$this->archive_mbox]))
        $p['list'][$this->archive_mbox]['name'] = $this->gettext('archivefolder');

    if (isset($p['list'][$this->archive_sent_mbox]))
        $p['list'][$this->archive_sent_mbox]['name'] = $this->gettext('archivesentfolder');

    return $p;
  }

  function look_contact($addr) {
    global $E_SESSION;
    $ret = array();
    
    $fields = DB::GetCol('SELECT field FROM contact_field WHERE active=1 AND type=\'text\' AND field LIKE \'%mail%\' ORDER BY field');
    foreach($fields as & $f) {
        $f = 'c.f_'.preg_replace('/[^a-z0-9]/','_',strtolower($f));
    }
    $contact = DB::GetCol('SELECT c.id FROM contact_data_1 c LEFT JOIN rc_multiple_emails_data_1 m ON (m.f_record_id=c.id AND m.f_record_type=%s) WHERE c.active=1 AND ('.implode('='.DB::qstr($addr).' OR ',$fields).'='.DB::qstr($addr).' OR m.f_email=%s) AND (c.f_permission<%s OR c.created_by=%d)',array('contact',$addr,'2',$E_SESSION['user']));
    foreach($contact as $contact_id) {
        $ret[] = 'P:'.$contact_id;
    }
    $fields = DB::GetCol('SELECT field FROM company_field WHERE active=1 AND type=\'text\' AND field LIKE \'%mail%\' ORDER BY field');
    foreach($fields as & $f) {
        $f = 'c.f_'.preg_replace('/[^a-z0-9]/','_',strtolower($f));
    }
    $company = DB::GetCol('SELECT c.id FROM company_data_1 c LEFT JOIN rc_multiple_emails_data_1 m ON (m.f_record_id=c.id AND m.f_record_type=%s) WHERE c.active=1 AND ('.implode('='.DB::qstr($addr).' OR ',$fields).'='.DB::qstr($addr).' OR m.f_email=%s) AND (c.f_permission<%s OR c.created_by=%d)',array('company',$addr,2,$E_SESSION['user']));
    foreach($company as $company_id) {
        $ret[] = 'C:'.$company_id;
    }
    
    return $ret;
  }

  function request_action()
  {
    $this->add_texts('localization');
    $rcmail = rcmail::get_instance();
    
    if (isset($_POST['_enabled_auto_archive'])) { //auto archive toggle
        $_SESSION['epesi_auto_archive'] = get_input_value('_enabled_auto_archive', RCUBE_INPUT_POST);
        return;
    }

    //archive button
    $uids = get_input_value('_uid', RCUBE_INPUT_POST);
    $mbox = get_input_value('_mbox', RCUBE_INPUT_POST);
    if($mbox==$this->archive_mbox || $mbox==$this->archive_sent_mbox || $mbox==$rcmail->config->get('drafts_mbox')) {
        $rcmail->output->command('display_message',$this->gettext('invalidfolder'), 'error');
        return;
    }
    $sent_mbox = ($rcmail->config->get('sent_mbox')==$mbox);
    
    $uids = explode(',',$uids);
    if($this->archive($uids)) {
	global $account;
	if(isset($account['f_use_epesi_archive_directories']) && $account['f_use_epesi_archive_directories']) {
            if($sent_mbox)
	        $rcmail->output->command('move_messages', $this->archive_sent_mbox);
	    else
        	$rcmail->output->command('move_messages', $this->archive_mbox);
        }
        $rcmail->output->command('display_message',$this->gettext('archived'), 'confirmation');
    }
  }

  private function archive($uids,$verbose=true) {
    global $E_SESSION;
    $rcmail = rcmail::get_instance();
    $path = getcwd();
    chdir(str_replace('/modules/CRM/Roundcube/RC','',$path));

    $msgs = array();    
    foreach($uids as $uid) {
        $msg = new rcube_message($uid);
        if (empty($msg->headers)) {
            if($verbose) {
                $rcmail->output->command('display_message','messageopenerror', 'error');
            }
            return false;
        } else {
            $msgs[$uid] = $msg;
        }
    }

    $map = array();
    foreach($msgs as $k=>$msg) {
        $sends = $rcmail->imap->decode_address_list($msg->headers->to);
        $map[$k] = array();
        foreach($sends as $send) {
            $addr = $send['mailto'];
            $ret = $this->look_contact($addr);
            $map[$k] = array_merge($map[$k],$ret);
        }
        $addr = $rcmail->imap->decode_address_list($msg->headers->from);
        if($addr) $addr = array_shift($addr);
        if(!isset($addr['mailto']) || !$addr['mailto']) {
            $map[$k] = false;
            continue;
        }
        $ret = $this->look_contact($addr['mailto']);
        $map[$k] = array_merge($map[$k],$ret);
    }
    
    if(!isset($_SESSION['force_archive']))
        $_SESSION['force_archive'] = array();
    foreach($map as $k=>$ret) {
        if(!$ret && !isset($_SESSION['force_archive'][$k]) && $verbose) {
            $_SESSION['force_archive'][$k] = 1;
            $rcmail->output->command('display_message',$this->gettext('contactnotfound'), 'error');
            return false;
        }
    }

    $attachments_dir = DATA_DIR.'/CRM_Roundcube/attachments/';
    $epesi_mails = array();
    if(!file_exists($attachments_dir)) mkdir($attachments_dir);
    foreach($msgs as $k=>$msg) {
        $contacts = $map[$k];
        $mime_map = array();
        foreach($msg->mime_parts as $mid=>$m)
            $mime_map[$m->mime_id] = md5($k.microtime(true).$mid);
        if($msg->has_html_part()) {
//            $body = $msg->first_html_part();
            foreach ($msg->mime_parts as $mime_id => $part) {
                $mimetype = strtolower($part->ctype_primary . '/' . $part->ctype_secondary);
                if ($mimetype == 'text/html') {
                    $body = $rcmail->imap->get_message_part($msg->uid, $mime_id, $part);
                    if(isset($part->replaces))
                        $cid_map = $part->replaces;
                    else
                        $cid_map = array();
                    break;
                }
            }
            foreach($cid_map as $k=>&$v) {
                $x = strrchr($v,'=');
                if(!$x) unset($cid_map[$k]);
                else {
                    $mid = substr($x,1);
                    if(isset($mime_map[$mid]))
                        $v = 'get.php?'.http_build_query(array('mail_id'=>'__MAIL_ID__','mime_id'=>$mime_map[$mid]));
                }
            }
            $body = rcmail_wash_html($body,array('safe'=>true,'inline_html'=>true),$cid_map);
        } else {
            $body = '<pre>'.$msg->first_text_part().'</pre>';
        }
        $date = $msg->get_header('timestamp');
        $headers = array();
        foreach($msg->headers as $k=>$v) {
            if(is_string($v) && $k!='from' && $k!='to' && $k!='body_structure')
                $headers[] = $k.': '.$rcmail->imap->decode_header($v);
        }
        $employee = DB::GetOne('SELECT id FROM contact_data_1 WHERE active=1 AND f_login=%d',array($E_SESSION['user']));
        $id = Utils_RecordBrowserCommon::new_record('rc_mails',array('contacts'=>$contacts,'date'=>$date,'employee'=>$employee,'subject'=>substr($msg->subject,0,256),'body'=>$body,'headers_data'=>implode("\n",$headers),'from'=>$rcmail->imap->decode_header($msg->headers->from),'to'=>$rcmail->imap->decode_header($msg->headers->to)));
        $epesi_mails[] = $id;
        foreach($contacts as $c) {
            list($rs,$con_id) = explode(':',$c);
            if($rs=='P')
                Utils_WatchdogCommon::new_event('contact',$con_id,'N_New mail');
            else
                Utils_WatchdogCommon::new_event('company',$con_id,'N_New mail');
        }
        Utils_WatchdogCommon::new_event('contact',$employee,'N_New mail');
        /*DB::Execute('INSERT INTO rc_mails_data_1(created_on,created_by,f_contacts,f_date,f_employee,f_subject,f_body,f_headers_data,f_direction) VALUES(%T,%d,%s,%T,%d,%s,%s,%s,%b)',array(
                    time(),$E_SESSION['user'],$contacts,$date,$employee,substr($msg->subject,0,256),$body,implode("\n",$headers),$sent_mbox));
        $id = DB::Insert_ID('rc_mails_data_1','id');*/
        foreach($msg->mime_parts as $mid=>$m) {
            if(!$m->disposition) continue;
            if(isset($cid_map['cid:'.$m->content_id]))
                $attachment = 0;
            else
                $attachment = 1;
            DB::Execute('INSERT INTO rc_mails_attachments(mail_id,type,name,mime_id,attachment) VALUES(%d,%s,%s,%s,%b)',array($id,$m->mimetype,$m->filename,$mime_map[$m->mime_id],$attachment));
            if(!file_exists($attachments_dir.$id)) mkdir($attachments_dir.$id);
            $fp = fopen($attachments_dir.$id.'/'.$mime_map[$m->mime_id],'w');
            $msg->get_part_content($m->mime_id,$fp);
            fclose($fp);
        }
    }

    //$rcmail->output->command('delete_messages');
    global $E_SESSION_ID;
    $lifetime = ini_get("session.gc_maxlifetime");
    if(DATABASE_DRIVER=='mysqlt') {
        if(!DB::GetOne('SELECT GET_LOCK(%s,%d)',array($E_SESSION_ID,ini_get('max_execution_time'))))
            trigger_error('Unable to get lock on session name='.$E_SESSION_ID,E_USER_ERROR);
    }
    $ret = DB::GetOne('SELECT data FROM session WHERE name = %s AND expires > %d', array($E_SESSION_ID, time()-$lifetime));
    if($ret) {
        $ret = unserialize($ret);
        $ret['rc_mails_cp'] = $epesi_mails;
        $data = serialize($ret);
        if(DATABASE_DRIVER=='postgres') $data = '\''.DB::BlobEncode($data).'\'';
        else $data = DB::qstr($data);
        $ret &= DB::Replace('session',array('expires'=>time(),'data'=>$data,'name'=>DB::qstr($E_SESSION_ID)),'name');
        if(DATABASE_DRIVER=='mysqlt') {
            DB::Execute('SELECT RELEASE_LOCK(%s)',array($E_SESSION_ID));
        }
    }
    
    chdir($path);
    return true;
  }

  function add_mailbox($p) {
    if($p['root']=='') {
        $rcmail = rcmail::get_instance();
        if(!$rcmail->imap->mailbox_exists($this->archive_mbox))
            $rcmail->imap->create_mailbox($this->archive_mbox,true);
        elseif(!$rcmail->imap->mailbox_exists($this->archive_mbox,true))
            $rcmail->imap->subscribe($this->archive_mbox);

        if(!$rcmail->imap->mailbox_exists($this->archive_sent_mbox))
            $rcmail->imap->create_mailbox($this->archive_sent_mbox,true);
        elseif(!$rcmail->imap->mailbox_exists($this->archive_sent_mbox,true))
            $rcmail->imap->subscribe($this->archive_sent_mbox);
    }
  }

  //on message sending
  function auto_archive() {
    if(!$_SESSION['epesi_auto_archive']) return;
    unset($_SESSION['epesi_auto_archive']);
    
    global $store_folder,$saved,$IMAP,$message_id,$store_target;
    if(!$store_folder || !$saved) return;
    $rcmail = rcmail::get_instance();
    
    $msgid = strtr($message_id, array('>' => '', '<' => ''));  
    $old_mbox = $IMAP->get_mailbox_name();

    $IMAP->set_mailbox($store_target);
    $uids = $IMAP->search_once('', 'HEADER Message-ID '.$msgid, true);
    if(empty($uids)) return;
    
    $archived = $this->archive($uids,false);

    global $account;
    if($archived && isset($account['f_use_epesi_archive_directories']) && $account['f_use_epesi_archive_directories']) {
        $rcmail->output->command('set_env', 'uid', array_shift($uids));
        $rcmail->output->command('set_env', 'mailbox',$store_target);
        $rcmail->output->command('move_messages', $this->archive_sent_mbox);
    }

    $IMAP->set_mailbox($old_mbox);
    
    if($archived) {
        $rcmail->output->command('display_message',$this->gettext('archived'), 'confirmation');
    }
  }
  
  function list_messages($p) {
    global $IMAP;
    $mbox = $IMAP->get_mailbox_name();
    if($mbox=='Epesi Archive Sent') {
        foreach($p['cols'] as &$c) {
            if($c=='from') $c = 'to';
        }
    }
    return $p;
  }
}
