<?php
/**
 * Core mail support - accounts, archive applet.
 * @author pbukowski@telaxus.com
 * @copyright Telaxus LLC
 * @license MIT
 * @version 0.1
 * @package epesi-CRM
 * @subpackage Mail
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_MailCommon extends ModuleCommon {
    public static function processing_related($values, $mode) {
        switch ($mode) {
            case 'edit':
            $rec = Utils_RecordBrowserCommon::get_record('rc_related', $values['id']);
            $rs = $rec['recordset'];
            self::delete_addon($rs);
            case 'add':
            $rs = $values['recordset'];
            self::new_addon($rs);
            break;

            case 'delete':
            $rs = $values['recordset'];
            self::delete_addon($rs);
            break;
        }
        return $values;
    }

    public static function new_addon($rs) {
        Utils_RecordBrowserCommon::new_addon($rs, CRM_Mail::module_name(), 'addon', _M('E-mails'));
    }

    public static function delete_addon($rs) {
        Utils_RecordBrowserCommon::delete_addon($rs, CRM_Mail::module_name(), 'addon');
    }

    public static function submit_account($param, $mode) {
        if($mode=='edit')
            $acc = Utils_RecordBrowserCommon::get_record('rc_accounts',$param['id']);
        if($mode=='adding') {
            $param['archive_on_sending']=1;
            $param['use_epesi_archive_directories']=1;
        }
        if($mode=='add' || (isset($acc['default_account']) && !$acc['default_account'])) {
            $count = DB::GetOne('SELECT count(*) FROM rc_accounts_data_1 WHERE active=1 AND f_epesi_user=%d',array(Acl::get_user()));
            if($count) {
                if($param['default_account'])
                    DB::Execute('UPDATE rc_accounts_data_1 SET f_default_account=0 WHERE active=1 AND f_epesi_user=%d',array(Acl::get_user()));
            } else
                $param['default_account']=1;
        }
        if($mode=='index') return array();
        return $param;
    }

    public static function submit_mail($param, $mode) {
        if ($mode == 'add') {
            $param['message_id'] = ltrim(rtrim($param['message_id'],'>'),'<');
        } else if ($mode == 'added') {
            self::create_thread($param['id']);
            self::subscribe_users_to_record($param);
        } else if ($mode == 'edit') {
            $old_related = Utils_RecordBrowserCommon::get_value('rc_mails', $param['id'], 'related');
            $old_related = Utils_RecordBrowserCommon::decode_multi($old_related);
            $new_related = $param['related'];
            $new_related = Utils_RecordBrowserCommon::decode_multi($new_related);
            $subscribers = array();
            foreach ($new_related as $rel) {
                if (in_array($rel, $old_related)) continue;
                list($recordset, $record_id) = explode('/', $rel);
                $subscribers = array_merge($subscribers, Utils_WatchdogCommon::get_subscribers($recordset, $record_id));
            }
            foreach (array_unique($subscribers) as $user_id) {
                Utils_WatchdogCommon::user_subscribe($user_id, 'rc_mails', $param['id']);
            }
        }
        return $param;
    }

    public static function QFfield_recordset(&$form, $field, $label, $mode, $default) {
        if ($mode == 'add' || $mode == 'edit') {
            $rss = DB::GetCol('SELECT f_recordset FROM rc_related_data_1 WHERE active=1');
            // remove currently selected value
            $key = array_search($default, $rss);
            if ($key !== false)
                unset($rss[$key]);
            $tabs = DB::GetAssoc('SELECT tab, caption FROM recordbrowser_table_properties WHERE tab not in (\'' . implode('\',\'', $rss) . '\') AND tab not like %s AND tab not like %s', array('%_related', 'rc_%'));
            foreach ($tabs as $k => $v) {
                $tabs[$k] = _V($v) . " ($k)";
            }
            $form->addElement('select', $field, $label, $tabs, array('id' => $field));
            $form->addRule($field, 'Field required', 'required');
            if ($mode == 'edit')
                $form->setDefaults(array($field => $default));
        } else {
            $form->addElement('static', $field, $label);
            $form->setDefaults(array($field => $default));
        }
    }

    public static function display_recordset($r, $nolink = false) {
        $caption = Utils_RecordBrowserCommon::get_caption($r['recordset']);
        return $caption . ' (' . $r['recordset'] . ')';
    }

    public static function display_epesi_user($record, $nolink, $desc) {
        return Base_UserCommon::get_user_login($record['epesi_user']);
    }

    public static function QFfield_epesi_user(&$form, $field, $label, $mode, $default, $desc, $rb=null) {
        $form->addElement('hidden', $field, $default);
    }

    public static function QFfield_account_name(&$form, $field, $label, $mode, $default, $desc, $rb=null) {
        $form->addElement('text', $field, $label,array('id'=>$field));
        $form->registerRule($field,'function','check_account_name','CRM_MailCommon');
        $form->addRule($field,__('Account Name already in use'),$field,isset($rb->record['id'])?$rb->record['id']:null);
        $form->setDefaults(array($field=>$default));
        if ($mode == 'add' || $mode == 'edit') {
            load_js('modules/CRM/Mail/utils.js');
            eval_js('CRM_Mail.filled_smtp_message=\''.Epesi::escapeJS(__('SMTP login and password was filled with imap account details. Please change them if needed.'),false,true).'\';CRM_Mail.edit_form()');
        }
        if($mode=='view') $form->freeze(array($field));
    }

    public static function check_account_name($o,$d) {
    	if($d!==null) {
    		if(!DB::GetOne('SELECT 1 FROM rc_accounts_data_1 WHERE active=1 AND f_account_name=%s AND f_epesi_user=%d AND id!=%d',array($o,Acl::get_user(),$d)))
    			return true;
    	} else {
    		if(!DB::GetOne('SELECT 1 FROM rc_accounts_data_1 WHERE active=1 AND f_account_name=%s AND f_epesi_user=%d',array($o,Acl::get_user())))
    			return true;
    	}
    	return false;
    }

    public static function display_password($r, $nolink=null, $desc=array()) {
		if ($r[$desc['id']]) return '******';
		else return '';
	}

    public static function QFfield_password(&$form, $field, $label, $mode, $default, $desc, $rb=null) {
        $form->addElement('password', $field, $label,array('id'=>$field));
        $form->setDefaults(array($field=>$default));
        $form->addRule($field,__('Field required'),'required');
        if($mode=='view') $form->freeze(array($field));
    }

    public static function QFfield_security(&$form, $field, $label, $mode, $default, $desc, $rb=null) {
        $form->addElement('commondata', $field, $label,array('CRM/Mail/Security'),array('empty_option'=>true));
        $form->addRule($field,__('OpenSSL not available - cannot set TLS/SSL. Please contact EPESI administrator.'),'callback',array('CRM_MailCommon','check_ssl'));
        $form->setDefaults(array($field=>$default));
        if($mode=='view') $form->freeze(array($field));
    }

    public static function check_ssl($o) {
        if($o=='ssl' || $o=='tls') return extension_loaded('openssl');
        return true;
    }

    public static function QFfield_smtp_auth(&$form, $field, $label, $mode, $default, $desc, $rb=null) {
        $form->addElement('checkbox', $field, $label,'',array('onchange'=>'CRM_Mail.smtp_auth_change(this.checked)','id'=>$field));
        $form->setDefaults(array($field=>$default));
        if ($mode == 'edit' || $mode == 'add') {
            eval_js('CRM_Mail.smtp_auth_change('.($default?1:0).')');
        }
        if($mode=='view') $form->freeze(array($field));
    }

    public static function QFfield_smtp_login(&$form, $field, $label, $mode, $default, $desc, $rb=null) {
        $form->addElement('text', $field, $label,array('id'=>'smtp_login'));
        $form->setDefaults(array($field=>$default));
        if($form->exportValue('smtp_auth'))
            $form->addRule($field,__('Field required'),'required');
        if($mode=='view') $form->freeze(array($field));
    }

    public static function QFfield_smtp_password(&$form, $field, $label, $mode, $default, $desc, $rb=null) {
        $form->addElement('password', $field, $label,array('id'=>'smtp_pass'));
        $form->setDefaults(array($field=>$default));
        if($form->exportValue('smtp_auth'))
            $form->addRule($field,__('Field required'),'required');
        if($mode=='view') $form->freeze(array($field));
    }

    public static function QFfield_smtp_security(&$form, $field, $label, $mode, $default, $desc, $rb=null) {
        $form->addElement('commondata', $field, $label,array('CRM/Mail/Security'),array('empty_option'=>true),array('id'=>'smtp_security'));
        $form->addRule($field,__('OpenSSL not available - cannot set TLS/SSL. Please contact EPESI administrator.'),'callback',array('CRM_MailCommon','check_ssl'));
        $form->setDefaults(array($field=>$default));
        if($mode=='view') $form->freeze(array($field));
    }

    public static function QFfield_default_account(&$form, $field, $label, $mode, $default, $desc, $rb=null) {
        $form->addElement('checkbox', $field, $label,'');
        $form->setDefaults(array($field=>$default));
        if($mode=='view' || $default) $form->freeze(array($field));
    }

    public static function display_subject($record, $nolink, $desc) {
    /*    static $last_message_id = null;*/
        if(isset($record['body'])) {
            $chars_count = 100;
            $body_preview = strip_tags($record['body']);
            if (strlen($body_preview) > $chars_count)
                $body_preview = substr($body_preview, 0, $chars_count) . " ...";
            $subject_label = Utils_RecordBrowserCommon::create_linked_label_r('rc_mails','subject',$record,$nolink);
            $subject_label = Utils_TooltipCommon::create($subject_label, "<pre class=\"wrap\">$body_preview</pre>", false);
            $ret = $subject_label .'<br />From: '.$record['from'].'<br />To: '.$record['to'] . '<br />';
        } else {
            $ret = Utils_RecordBrowserCommon::create_linked_label_r('rc_mail_threads','subject',$record,$nolink);
        }
/*        $replies = '<div style="text-align:center;float:right;width:20px;font-size:16px;line-height:20px;padding:8px;border-radius:18px;height:20px;background-color:gray;color:white;" class="num_of_replies"></div>';
        if(!$record['references'] || !$last_message_id || strpos($record['references'],$last_message_id)===false) {
            $last_message_id = $record['message_id'];
            return $replies.$ret;
        }
        if(!$last_message_id) return $replies.$ret;
        return '<div style="margin-left:20px" class="reply parent_'.md5($last_message_id).'">'.$ret.'</div>';*/
        return $ret;
	}

    public static function QFfield_thread_count(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
        $form->addElement('static', $field, $label,self::display_thread_count($rb_obj->record,true,null));
    }

    public static function display_thread_count($record, $nolink, $desc) {
        return DB::GetOne('SELECT count(*) FROM rc_mails_data_1 WHERE f_thread=%d AND active=1',array($record['id']));
    }

    public static function QFfield_thread_attachments(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
        $form->addElement('static', $field, $label,self::display_thread_attachments($rb_obj->record,true,null));
    }

    public static function display_thread_attachments($record, $nolink, $desc) {
        return DB::GetOne('SELECT count(mime_id) FROM rc_mails_attachments WHERE mail_id IN (SELECT m.id FROM rc_mails_data_1 m WHERE m.f_thread=%d AND m.active=1) AND attachment=1',array($record['id']));
    }

    public static function QFfield_attachments(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
        if(isset($_GET['rc_reply']) || isset($_GET['rc_replyall']) || isset($_GET['rc_forward'])) {
            $attachments = DB::GetAssoc('SELECT mime_id,name FROM rc_mails_attachments WHERE mail_id=%d AND attachment=1',array($rb_obj->record['id']));
            $data = array();
            if($attachments) {
                $hash = md5(time().' '.serialize($rb_obj->record));
                DB::Execute('INSERT INTO rc_mails_attachments_download(mail_id,hash) VALUES(%d,%s)',array($rb_obj->record['id'],$hash));
                foreach($attachments as $k=>&$n) {
                    $filename = DATA_DIR.'/CRM_Mail/attachments/'.$rb_obj->record['id'].'/'.$k;
                    if(file_exists($filename)) {
                        $data[] = '<a href="'.rtrim(get_epesi_url().'/').'/modules/CRM/Mail/get_remote.php?'.http_build_query(array('mime_id'=>$k,'mail_id'=>$rb_obj->record['id'],'hash'=>$hash)).'" target="_blank">'.$n.'</a>';
                    }
                }
            }
            $attachments = implode('<br />',$data);
        } else $attachments = '';
	if(isset($_GET['rc_reply']) && $_GET['rc_reply']==$rb_obj->record['id']) {
		Base_BoxCommon::push_module('CRM_Roundcube','new_mail',array(html_entity_decode($rb_obj->record['from']),(preg_match('/^Re:/i',$rb_obj->record['subject'])?'':'Re: ').$rb_obj->record['subject'],'<br /><br /><strong>'.__('On %s wrote',array(Base_RegionalSettingsCommon::time2reg($rb_obj->record['date']).', '.$rb_obj->record['from'])).':</strong><br/>'.$rb_obj->record['body'].($attachments?'<hr /><strong>'.__('Attachments').':</strong><br/>'.$attachments:''),$rb_obj->record['message_id'],html_entity_decode($rb_obj->record['references'])));
	} elseif(isset($_GET['rc_replyall']) && $_GET['rc_replyall']==$rb_obj->record['id']) {
		$to = explode(',',$rb_obj->record['to']);
		$to[] = $rb_obj->record['from'];
		$mails = Utils_RecordBrowserCommon::get_records('rc_accounts',array('epesi_user'=>Acl::get_user()),array('email'));
		foreach($to as $k=>$t) {
			$to[$k] = trim($t);
			foreach($mails as $m) {
				if(strpos($t,$m['email'])!==false) {
				    unset($to[$k]);
				    break;
				}
			}
		}
		Base_BoxCommon::push_module('CRM_Roundcube','new_mail',array(html_entity_decode(implode(', ',$to)),(preg_match('/^Re:/i',$rb_obj->record['subject'])?'':'Re: ').$rb_obj->record['subject'],'<br /><br /><strong>'.__('On %s wrote',array(Base_RegionalSettingsCommon::time2reg($rb_obj->record['date']).', '.$rb_obj->record['from'])).':</strong><br/>'.$rb_obj->record['body'].($attachments?'<hr /><strong>'.__('Attachments').':</strong><br/>'.$attachments:''),$rb_obj->record['message_id'],html_entity_decode($rb_obj->record['references'])));
	} elseif(isset($_GET['rc_forward']) && $_GET['rc_forward']==$rb_obj->record['id']) {
		Base_BoxCommon::push_module('CRM_Roundcube','new_mail',array('',(preg_match('/^Re:/i',$rb_obj->record['subject'])?'':'Re: ').$rb_obj->record['subject'],'<br /><br /><strong>'.__('On %s wrote',array(Base_RegionalSettingsCommon::time2reg($rb_obj->record['date']).', '.$rb_obj->record['from'])).':</strong><br/>'.$rb_obj->record['body'].($attachments?'<hr /><strong>'.__('Attachments').':</strong><br/>'.$attachments:'')));
	}
	Base_ActionBarCommon::add('reply',__('Reply'), Module::create_href(array('rc_reply'=>$rb_obj->record['id'])));
	Base_ActionBarCommon::add('reply',__('Reply All'), Module::create_href(array('rc_replyall'=>$rb_obj->record['id'])));
	Base_ActionBarCommon::add('forward',__('Forward'), Module::create_href(array('rc_forward'=>$rb_obj->record['id'])));
    }

    public static function display_attachments($record, $nolink, $desc) {
        return DB::GetOne('SELECT count(mime_id) FROM rc_mails_attachments WHERE mail_id=%d AND attachment=1',array($record['id']));
    }

    public static function QFfield_body(&$form, $field, $label, $mode, $default, $desc, $rb=null) {
        //$form->addElement('static', $field, $label,DB::GetOne('SELECT f_body FROM rc_mails_data_1 WHERE id=%d',array($rb->record['id'])));
        $form->addElement('static', $field, $label,'<iframe id="rc_mail_body" src="modules/CRM/Mail/get_html.php?'.http_build_query(array('id'=>$rb->record['id'])).'" style="width:100%;border:0" border="0"></iframe>');
    }

    public static function QFfield_mail_thread(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
        $form->addElement('static', $field, $label,self::display_mail_thread($rb_obj->record,false,null));
    }

    public static function display_mail_thread($record, $nolink, $desc) {
        if($record['thread']) return Utils_RecordBrowserCommon::record_link_open_tag('rc_mail_threads', $record['thread'], $nolink).DB::GetOne('SELECT count(*) FROM rc_mails_data_1 WHERE f_thread=%d AND active=1',array($record['thread'])).Utils_RecordBrowserCommon::record_link_close_tag();
        return '';
    }

    public static function QFfield_hidden(&$form, $field, $label, $mode, $default, $desc, $rb=null) {
    }

    public static function watchdog_label($rid = null, $events = array(), $details = true) {
        return Utils_RecordBrowserCommon::watchdog_label(
            'rc_mails',
            __('Mails'),
            $rid,
            $events,
            'subject',
            $details
        );
    }

    public static function QFfield_nickname(&$form, $field, $label, $mode, $default,$x,$y) {
        if ($mode=='add' || $mode=='edit') {
            $form->addElement('text', $field, $label);
            $form->registerRule('check_nickname','callback','check_nickname','CRM_MailCommon');
            $form->addRule($field, __('Field required'), 'required');
            if ($mode=='edit') {
                $form->addRule($field, __('Nickname already in use'), 'check_nickname',array($y->record['record_type'],$y->record['record_id'],$y->record['id']));
                $form->setDefaults(array($field=>$default));
            } else {
                $rec = $y->get_custom_defaults();
                $form->addRule($field, __('Nickname already in use'), 'check_nickname',array($rec['record_type'],$rec['record_id']));
            }
        } else {
            $form->addElement('static', $field, $label, $default);
        }
    }

    public static function check_nickname($v,$id) {
        if(isset($id[2])) {
            $r = Utils_RecordBrowserCommon::get_records('rc_multiple_emails',array('nickname'=>$v,'record_type'=>$id[0],'record_id'=>$id[1],'!id'=>$id[2]),array());
            return empty($r);
        }
        $r = Utils_RecordBrowserCommon::get_records('rc_multiple_emails',array('nickname'=>$v,'record_type'=>$id[0],'record_id'=>$id[1]),array());
        return empty($r);
    }

    public static function create_thread($id) {
        $m = Utils_RecordBrowserCommon::get_record('rc_mails',$id);
        $thread = $m['thread'];
        if(!$thread && $m['message_id'])
          $thread = DB::GetOne('SELECT f_thread FROM rc_mails_data_1 WHERE f_references is not null AND f_references LIKE '.DB::Concat('\'%%\'','%s','\'%%\'').' AND active=1',array($m['message_id']));
        if(!$thread && $m['references'])
          $thread = DB::GetOne('SELECT f_thread FROM rc_mails_data_1 WHERE f_message_id is not null AND %s LIKE '.DB::Concat('\'%%\'','f_message_id','\'%%\'').' AND active=1',array($m['references']));
        if(!$thread)
          $thread = Utils_RecordBrowserCommon::new_record('rc_mail_threads',array('subject'=>$m['subject'],'contacts'=>array_unique(array_merge($m['contacts'],array('P:'.$m['employee']))),'first_date'=>$m['date'],'last_date'=>$m['date']));
        Utils_RecordBrowserCommon::update_record('rc_mails',$id,array('thread'=>$thread), false, null, true);
        $t = Utils_RecordBrowserCommon::get_record('rc_mail_threads',$thread);
        Utils_RecordBrowserCommon::update_record('rc_mail_threads',$thread,array('contacts'=>array_unique(array_merge($t['contacts'],$m['contacts'],array('P:'.$m['employee']))),'first_date'=>strtotime($m['date'])<strtotime($t['first_date'])?$m['date']:$t['first_date'],'last_date'=>strtotime($m['date'])>strtotime($t['last_date'])?$m['date']:$t['last_date'],'subject'=>(trim($m['references'])=='' ||  mb_strlen($m['subject'])<mb_strlen($t['subject']))?$m['subject']:$t['subject']));
    }

    public static function subscribe_users_to_record($record)
    {
        $employee = $record['employee'];
        $contacts = $record['contacts'];
        $subscribers = $employee ? Utils_WatchdogCommon::get_subscribers('contact', $employee) : array();
        foreach ($contacts as $c) {
            list($rs_full, $con_id) = CRM_ContactsCommon::decode_record_token($c);
            $subscribers = array_merge($subscribers, Utils_WatchdogCommon::get_subscribers($rs_full, $con_id));
        }
        foreach (array_unique($subscribers) as $user_id) {
            Utils_WatchdogCommon::user_subscribe($user_id, 'rc_mails', $record['id']);
        }
    }

    public static function new_mail_addresses_addon($table) {
        Utils_RecordBrowserCommon::new_addon($table, CRM_Mail::module_name(), 'mail_addresses_addon', _M('Mail addresses'));
    }

    public static function get_mail_addresses($tab,$rec_id) {
        $r = Utils_RecordBrowserCommon::get_records('rc_multiple_emails',array('record_type'=>$tab,'record_id'=>$rec_id));
        $rec = array();
        foreach($r as $r2)
            $rec[$r2['nickname']] = $r2;
        return $rec;
    }

    public static function QFfield_related(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
        if(DB::GetOne('SELECT 1 FROM rc_related_data_1 WHERE active=1'))
            Utils_RecordBrowserCommon::QFfield_select($form, $field, $label, $mode, $default, $desc, $rb_obj);
    }

    public static function related_crits() {
        $recordsets = DB::GetCol('SELECT f_recordset FROM rc_related_data_1 WHERE active=1');
        $crits = array(
            '' => array(),
        );
        foreach ($recordsets as $rec)
            $crits[$rec] = array();
        return $crits;
    }

    public static function get_accounts($user_id = null)
    {
        if ($user_id === null) {
            $user_id = Acl::get_user();
        }
        $crits = array();
        if ($user_id) {
            $crits['epesi_user'] = $user_id;
        }
        $ret = Utils_RecordBrowserCommon::get_records('rc_accounts', $crits);
        return $ret;
    }

    public static function get_email_addresses($rs,$rec) {
        if(is_numeric($rec)) $rec = Utils_RecordBrowserCommon::get_record($rs,$rec);

        $emails = array();
        if(isset($rec['email']) && $rec['email']) $emails[] = $rec['email'];

        $multiple = Utils_RecordBrowserCommon::get_records('rc_multiple_emails',array('record_type'=>$rs,'record_id'=>$rec['id']));
        foreach($multiple as $multi) if($multi['email']) $emails[] = $multi['email'];

        return array_unique($emails);
    }

    public static function reload_mails($rs,$id,$email_addresses = null) {
        $prefix = ($rs=='contact'?'P':'C').':';

        if(!$email_addresses) $email_addresses = self::get_email_addresses($rs,$id);

        foreach($email_addresses as $email) {
            $cc = Utils_RecordBrowserCommon::get_records('rc_mails',array('(~from'=>'%'.$email.'%','|~to'=>'%'.$email.'%'));

            foreach($cc as $mail) {
                if(($rs=='contact' && $mail['employee']==$id) || in_array($prefix.$id,$mail['contacts'])) continue;
                if(!preg_match('/(^|[\s,\<\;])'.preg_quote($email,'/').'($|[\s,\>\&])/i',$mail['from'].','.$mail['to'])) {
                    continue;
                }

                $mail['contacts'][] = $prefix.$id;
                Utils_RecordBrowserCommon::update_record('rc_mails',$mail['id'],array('contacts'=>$mail['contacts']));
                CRM_MailCommon::create_thread($mail['id']);
            }
        }
    }

    /**
     * @param int  $account_id
     * @param bool $only_cached If true then only cached response will be retrieved
     * @param int  $cache_validity_in_minutes Provide 0 or false to force request
     *
     * @return array|null
     * @throws Exception
     */
    public static function get_unread_messages($account_id, $only_cached = false, $cache_validity_in_minutes = 3)
    {
        $return = null;
        $rec = Utils_RecordBrowserCommon::get_record('rc_accounts', $account_id);
        if ($rec['epesi_user'] != Acl::get_user()) {
            throw new Exception('Invalid account id');
        }
        $port = $rec['security'] == 'ssl' ? 993 : 143;
        $server_str = '{' . $rec['server'] . '/imap/readonly/novalidate-cert' . ($rec['security'] ? '/' . $rec['security'] : '') . ':' . $port . '}';
        $cache_key = md5($server_str . ' # ' . $rec['login'] . ' # ' . $rec['password']);
        $cache = new FileCache(DATA_DIR . '/cache/mail_unread.php');
        if ($cache_validity_in_minutes) {
            $unread_messages = $cache->get($cache_key);
            if ($unread_messages && ($only_cached || $unread_messages['t'] > (time() - $cache_validity_in_minutes*60))) {
                $return = $unread_messages['val'];
            }
        }
        if ($return === null && $only_cached === false) {
            @set_time_limit(0);
            $mailbox = @imap_open(imap_utf7_encode($server_str), imap_utf7_encode($rec['login']), imap_utf7_encode($rec['password']), OP_READONLY || OP_SILENT);
            $err = imap_errors();
            $unseen = array();
            if (!$mailbox || $err) {
                $err = __('Connection error') . ": " . implode(', ', $err);
            } else {
                $uns = @imap_search($mailbox, 'UNSEEN ALL');
                if ($uns) {
                    $l = @imap_fetch_overview($mailbox, implode(',', $uns), 0);
                    $err = imap_errors();
                    if (!$l || $err) {
                        $error_info = $err ? ": " . implode(', ', $err) : "";
                        $err = __('Error reading messages overview') . $error_info;
                    } else {
                        foreach ($l as $msg) {
                            $from = isset($msg->from) ? imap_utf8($msg->from) : '<unknown>';
                            $subject = isset($msg->subject) ? imap_utf8($msg->subject) : '<no subject>';
                            $date = isset($msg->date) ? $msg->date : '';
                            $unseen[] = array('from' => $from, 'subject' => $subject, 'id' => $msg->uid, 'date' => $date, 'unix_timestamp' => $msg->udate);
                        }
                    }
                }
            }
            if (!is_bool($mailbox)) {
                imap_close($mailbox);
            }
            imap_errors(); // called just to clean up errors.
            if ($err) {
                throw new Exception($err);
            } else {
                $return = $unseen;
                $cache->set($cache_key, array('val' => $return, 't' => time()));
            }
        }
        return $return;
    }

    public static function notification()
    {
        $notifications = array();
        foreach (self::get_accounts() as $account) {
            try {
                $unread_messages = self::get_unread_messages($account['id'], true);
            } catch (Exception $ex) {
                return array();
            }
            if (!$unread_messages) {
                return array();
            }
            foreach ($unread_messages as $m) {
                $notification_title = __('New email') . ' - ' . $account['account_name'];
                $notification_body = $m['from'] . "\n" . $m['subject'];
                $notifications["rc_message_{$account['id']}_{$m['id']}"] = array('title' => $notification_title, 'body' => $notification_body);
            }
        }
        return array('tray' => $notifications);
    }

    public static function applet_caption() {
        if(function_exists('imap_open'))
            return __('Mail indicator');
        return false;
    }

    public static function applet_info() {
        return __('Checks if there is new mail');
    }

    public static function applet_settings() {
        $conf = array(array('type'=>'header','label'=>__('Choose accounts')));
        $ret = Utils_RecordBrowserCommon::get_records('rc_accounts',array('epesi_user'=>Acl::get_user()));
        foreach($ret as $row)
                $conf[] = array('name'=>'account_'.$row['id'], 'label'=>$row['account_name'], 'type'=>'checkbox', 'default'=>1);
        if(count($conf)==1)
            return array(array('type'=>'static','label'=>__('No accounts configured, go Menu->My settings->Control panel->E-mail accounts')));
        return $conf;
    }

    public static function user_settings() {
        if(Utils_RecordBrowserCommon::get_access('rc_accounts', 'browse')) {
            return array(__('E-mail Accounts')=>'account_manager');
        }
        return array();
    }

    public static function addon_access() {
        return Utils_RecordBrowserCommon::get_access('contact','browse');
    }

    public static function look_contact($addr,$user=null) {
        $ret = array();

        if(!$user) $user = Base_AclCommon::get_user();

        $fields = DB::GetCol('SELECT field FROM contact_field WHERE active=1 AND type=\'text\' AND field LIKE \'%mail%\' ORDER BY field');
        foreach($fields as & $f) {
            $f = 'c.f_'.preg_replace('/[^a-z0-9]/','_',strtolower($f));
        }
        $contact = DB::GetCol('SELECT c.id FROM contact_data_1 c LEFT JOIN rc_multiple_emails_data_1 m ON (m.f_record_id=c.id AND m.f_record_type=%s AND m.active=1) WHERE c.active=1 AND ('.implode('='.DB::qstr($addr).' OR ',$fields).'='.DB::qstr($addr).' OR m.f_email=%s) AND (c.f_permission<%s OR c.created_by=%d)',array('contact',$addr,'2',$user));
        foreach($contact as $contact_id) {
            $ret[] = 'P:'.$contact_id;  //TODO: change to contact/XX
        }
        $fields = DB::GetCol('SELECT field FROM company_field WHERE active=1 AND type=\'text\' AND field LIKE \'%mail%\' ORDER BY field');
        foreach($fields as & $f) {
            $f = 'c.f_'.preg_replace('/[^a-z0-9]/','_',strtolower($f));
        }
        $company = DB::GetCol('SELECT c.id FROM company_data_1 c LEFT JOIN rc_multiple_emails_data_1 m ON (m.f_record_id=c.id AND m.f_record_type=%s AND m.active=1) WHERE c.active=1 AND ('.implode('='.DB::qstr($addr).' OR ',$fields).'='.DB::qstr($addr).' OR m.f_email=%s) AND (c.f_permission<%s OR c.created_by=%d)',array('company',$addr,2,$user));
        foreach($company as $company_id) {
            $ret[] = 'C:'.$company_id; //TODO: change to company/XX
        }

        return $ret;
    }

    public static function archive_message($message_id,$references,$contacts,$date,$subject,$body,$headers,$from,$to,$employee,$attachments) {
        $data = array('message_id'=>$message_id,'references'=>$references,'contacts'=>$contacts,'date'=>$date,'subject'=>substr($subject,0,256),'body'=>$body,'headers_data'=>$headers,'from'=>$from,'to'=>$to,'employee'=>$employee);
        $id = Utils_RecordBrowserCommon::new_record('rc_mails',$data);

        $attachments_dir = DATA_DIR.'/CRM_Mail/attachments/';
        if(!file_exists($attachments_dir)) mkdir($attachments_dir);

        if(is_array($attachments))
            foreach($attachments as $m) {
                DB::Execute('INSERT INTO rc_mails_attachments(mail_id,type,name,mime_id,attachment) VALUES(%d,%s,%s,%s,%b)',array($id,$m['type'],$m['filename'],$m['mime_id'],$m['attachment']));
                if(!file_exists($attachments_dir.$id)) mkdir($attachments_dir.$id);
                file_put_contents($attachments_dir.$id.'/'.$m['mime_id'],$m['content']);
            }
        return $id;
    }

    public static function get_attachment_url($mime_id) {
        return 'get.php?'.http_build_query(array('mail_id'=>'__MAIL_ID__','mime_id'=>$mime_id));
    }
}

?>
