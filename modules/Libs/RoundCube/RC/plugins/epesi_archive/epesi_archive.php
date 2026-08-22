<?php
class epesi_archive extends rcube_plugin
{
  public $task = 'mail';
  public $archive_mbox = 'CRM Archive';
  public $archive_sent_mbox = 'CRM Archive Sent';

  function init()
  {
    global $account;

    if($account['f_imap_root']) {
        $this->archive_mbox = rtrim($account['f_imap_root'],'.').'.'.$this->archive_mbox;
        $this->archive_sent_mbox = rtrim($account['f_imap_root'],'.').'.'.$this->archive_sent_mbox;
    }

    $rcmail = rcmail::get_instance();
    $this->register_action('plugin.epesi_archive', array($this, 'request_action'));

    //register hook to archive just sent mail
    $this->add_hook('message_sent', array($this, 'auto_archive_on_send'));
    if(!isset($_SESSION['epesi_auto_archive']))
        $_SESSION['epesi_auto_archive'] = isset($account['f_archive_on_sending']) && $account['f_archive_on_sending']?1:0;

    $this->include_script('archive.js');
    $this->add_texts('localization', true);

    $this->add_hook('messages_list', array($this, 'list_messages'));

    if($rcmail->action == 'compose') {
        $this->add_button(
        array(
            'command' => 'plugin.epesi_auto_archive',
            'type' => 'link',
            'class' => 'button archive'.($_SESSION['epesi_auto_archive']?' pressed':''),
            'classact' => 'button archive',
            'innerclass' => 'inner',
            'label' => 'buttontitle_compose',
            'title' => 'buttontitle_compose',
            'domain' => $this->ID,
            'id'=>'epesi_auto_archive_button'
        ),
        'toolbar');

        // Elastic: style the toggle's on/off state (dim/grey when OFF, colour when ON).
        $skin_path = $this->local_skin_path();
        if (is_file($this->home . "/$skin_path/archive.css"))
            $this->include_stylesheet("$skin_path/archive.css");
    }

    if ($rcmail->action == '' || $rcmail->action == 'show') {
      $this->add_button(
        array(
            'command' => 'plugin.epesi_archive',
            'type' => 'link',
            'class' => 'button buttonPas archive disabled',
            'classact' => 'button archive',
            'classsel' => 'button archive pressed',
            'innerclass' => 'inner',
            'label' => 'buttontitle',
            'title' => 'buttontitle',
            'domain' => $this->ID,
        ),
        'toolbar');

      if(!isset($account['f_use_epesi_archive_directories']) || !$account['f_use_epesi_archive_directories']) return;

      // register hook to localize the archive folder
      $this->add_hook('render_mailboxlist', array($this, 'render_mailboxlist'));

      // set env variable for client
      $rcmail->output->set_env('archive_mailbox', $this->archive_mbox);
      $rcmail->output->set_env('archive_sent_mailbox', $this->archive_sent_mbox);

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
      $this->add_hook('storage_folders', array($this, 'add_mailbox'));
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
    return CRM_MailCommon::look_contact($addr,$E_SESSION['user']);
  }

  function request_action()
  {
    $this->add_texts('localization');
    $rcmail = rcmail::get_instance();

    if (isset($_POST['_enabled_auto_archive'])) { //auto archive toggle
        $_SESSION['epesi_auto_archive'] = rcube_utils::get_input_value('_enabled_auto_archive', rcube_utils::INPUT_POST);
        return;
    }

    //archive button
    $uids = rcube_utils::get_input_value('_uid', rcube_utils::INPUT_POST);
    $mbox = rcube_utils::get_input_value('_mbox', rcube_utils::INPUT_POST);
    if($mbox==$this->archive_mbox || $mbox==$this->archive_sent_mbox || $mbox==$rcmail->config->get('drafts_mbox')) {
        $rcmail->output->show_message($this->gettext('invalidfolder'), 'error');
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
        $rcmail->output->show_message($this->gettext('archived'), 'confirmation');
    }
    global $E_SESSION_ID,$E_SESSION;

    EpesiSession::set($E_SESSION_ID, $E_SESSION);
  }

  private function archive($uids,$verbose=true) {
    global $E_SESSION;
    $rcmail = rcmail::get_instance();
    $path = getcwd();
    // chdir to the Epesi root so FileStorage's relative 'data/...' paths resolve. The old
    // str_replace(getcwd()) trick assumed a CWD the RC 1.7.1 bundle no longer uses, so it failed
    // and the attachment write (archive_message → FileStorage) hit "No such file or directory".
    chdir(EPESI_LOCAL_DIR);

    $msgs = array();
    if (!is_array($uids)) $uids = $uids->get();
    foreach($uids as $uid) {
        $msg = new rcube_message($uid);
        if ($msg===null || empty($msg->headers)) {
            if($verbose) {
                $rcmail->output->show_message('messageopenerror', 'error');
            }
            return false;
        } else {
            $msgs[$uid] = $msg;
        }
    }

    $map = array();
    foreach($msgs as $k=>$msg) {
        $sends = rcube_mime::decode_address_list($msg->headers->to);
        $map[$k] = array();
        foreach($sends as $send) {
            $addr = $send['mailto'];
            $ret = $this->look_contact($addr);
            $map[$k] = array_merge($map[$k],$ret);
        }
        $addr = rcube_mime::decode_address_list($msg->headers->from);
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
            $rcmail->output->show_message($this->gettext('contactnotfound'), 'error');
            return false;
        }
    }

    $epesi_mails = array();
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
                    $body = $rcmail->storage->get_message_part($msg->uid, $mime_id, $part);
                    if(isset($part->replaces))
                        $cid_map = $part->replaces;
                    else
                        $cid_map = array();
                    break;
                }
            }
            foreach($cid_map as $kk=>&$v) {
                if(preg_match('/_part=(.*?)&/',$v,$matches)) {
                    $mid = $matches[1];
                    if(isset($mime_map[$mid]))
                        $v = CRM_MailCommon::get_attachment_url($mime_map[$mid]);
                } else {
                    unset($cid_map[$kk]);
                }
            }
            // Sanitize the HTML body before storing it in the CRM record. We can't use
            // rcmail_action_mail_index::wash_html() here: it calls $rcmail->output->asset_url(),
            // which only exists on the HTML output object — this archive action runs as an
            // AJAX/JSON request (rcmail_output_json), so that fatals. Wash with rcube_washtml
            // directly (what the old rcmail_wash_html wrapper did); safe context = user-initiated.
            $washer = new rcube_washtml(array(
                'show_washed'   => false,
                'allow_remote'  => true,
                'cid_map'       => $cid_map,
                'html_elements' => array('body'),
                'html_attribs'  => array('rel','type'),
            ));
            $body = $washer->wash($body);
        } else {
            $body = '<pre>'.$msg->first_text_part().'</pre>';
        }
        $headers = array();
        foreach($msg->headers as $kk=>$v) {
            if(is_string($v) && $kk!='from' && $kk!='to' && $kk!='body_structure')
                $headers[] = $kk.': '.rcube_mime::decode_mime_string((string)$v);
        }
        $message_id = str_replace(array('<','>'),'',$msg->get_header('MESSAGE-ID'));
        if(Utils_RecordBrowserCommon::get_records_count('rc_mails',array('message_id'=>$message_id))>0) {
            $rcmail->output->show_message($this->gettext('archived_duplicate'), 'warning');
            return false;
        }
        $employee = DB::GetOne('SELECT id FROM contact_data_1 WHERE active=1 AND f_login=%d',array($E_SESSION['user']));
        $attachments = array();
        foreach($msg->mime_parts as $mid=>$m) {
            if(!$m->disposition) continue;
            if(isset($cid_map['cid:'.$m->content_id]))
                $attachment = 0;
            else
                $attachment = 1;
            $attachments[] = array('type'=>$m->mimetype,'filename'=>$m->filename,'mime_id'=>$mime_map[$m->mime_id],'attachment'=>$attachment,'content'=>$msg->get_part_body($m->mime_id));
        }

        $id = CRM_MailCommon::archive_message($message_id,$msg->get_header('REFERENCES'),$contacts,$msg->headers->timestamp,$msg->subject,$body,implode("\n",$headers),rcube_mime::decode_mime_string((string)$msg->headers->from),rcube_mime::decode_mime_string((string)$msg->headers->to),$employee,$attachments);
        $epesi_mails[] = $id;
    }

    $E_SESSION['rc_mails_cp'] = $epesi_mails;

    chdir($path);
    return true;
  }

  function add_mailbox($p) {
    if($p['root']=='' && $p['name']=='*') {
        $rcmail = rcmail::get_instance();

        if(!$rcmail->storage->folder_exists($this->archive_mbox)) {
            $old = str_replace('CRM','Epesi',$this->archive_mbox);
            if($rcmail->storage->folder_exists($old)) {
                $rcmail->storage->rename_folder($old,$this->archive_mbox);
            } else
                $rcmail->storage->create_folder($this->archive_mbox,true);
        } elseif(!$rcmail->storage->folder_exists($this->archive_mbox,true))
            $rcmail->storage->subscribe($this->archive_mbox);

        if(!$rcmail->storage->folder_exists($this->archive_sent_mbox)) {
            $old = str_replace('CRM','Epesi',$this->archive_sent_mbox);
            if($rcmail->storage->folder_exists($old)) {
                $rcmail->storage->rename_folder($old,$this->archive_sent_mbox);
            } else
                $rcmail->storage->create_folder($this->archive_sent_mbox,true);
        } elseif(!$rcmail->storage->folder_exists($this->archive_sent_mbox,true))
            $rcmail->storage->subscribe($this->archive_sent_mbox);
    }
  }

  // Auto-archive on send (RC 1.7.1). The old approach hooked 'attachments_cleanup' and read
  // $store_folder/$saved/$store_target as globals — but in 1.7.1 those are locals in send.php and
  // that hook now fires from upload cleanup, so it was a no-op. There is no post-store hook either.
  // Instead we hook 'message_sent' (fires during delivery, BEFORE save_message reads
  // $_POST['_store_target']): redirect the sent copy straight into CRM Archive Sent, then create the
  // Epesi record at shutdown (which runs before the IMAP connection is closed), once it is stored.
  function auto_archive_on_send($args) {
    if (empty($_SESSION['epesi_auto_archive'])) return $args;
    if (!empty($_POST['_draft'])) return $args; // real sends only, not drafts
    unset($_SESSION['epesi_auto_archive']); // one-shot per send

    global $account;
    $use_dirs = !empty($account['f_use_epesi_archive_directories']);
    $rcmail = rcmail::get_instance();

    // File the sent copy in CRM Archive Sent (overrides reply_same_folder). save_message() reads
    // $_POST['_store_target'] later in this same request.
    if ($use_dirs)
        $_POST['_store_target'] = $this->archive_sent_mbox;

    // Message-ID from the just-sent headers; used to locate the stored copy at shutdown.
    $headers = isset($args['headers']) ? $args['headers'] : array();
    $msgid = '';
    foreach ($headers as $k => $v) {
        if (strcasecmp($k, 'Message-ID') === 0) { $msgid = $v; break; }
    }
    if (!$msgid) return $args;

    $folder = $use_dirs ? $this->archive_sent_mbox : $rcmail->config->get('sent_mbox');
    $self = $this;
    $rcmail->add_shutdown_function(function() use ($self, $msgid, $folder) {
        $self->finish_auto_archive($msgid, $folder);
    });

    return $args;
  }

  // Runs at shutdown (IMAP still open): find the stored sent message by Message-ID and archive it.
  public function finish_auto_archive($msgid, $folder) {
    if (!$folder) return;
    $rcmail = rcmail::get_instance();
    $storage = $rcmail->get_storage();
    $msgid = strtr($msgid, array('>' => '', '<' => ''));
    $storage->set_folder($folder);
    $uids = $storage->search_once($folder, 'HEADER Message-ID '.$msgid);
    if (!$uids || $uids->is_empty()) return;
    $this->archive($uids, false);
  }

  function list_messages($p) {
    $IMAP = $imap = rcmail::get_instance()->storage;
    $mbox = $IMAP->get_folder();
    if(preg_match('/CRM Archive Sent$/i',$mbox)) {
        foreach($p['cols'] as &$c) {
            if($c=='from') $c = 'to';
        }
    }
    return $p;
  }
}
