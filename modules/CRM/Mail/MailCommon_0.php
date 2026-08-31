<?php
/**
 * Core mail support - accounts, archive applet.
 * @author pbukowski@telaxus.com
 * @copyright Janusz Tylek
 * @license MIT
 * @version 0.1
 * @package epesi-CRM
 * @subpackage Mail
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_MailCommon extends ModuleCommon {
	// AdminLTE-only: Base_BootstrapIcons::resolve() looks this up for this
	// module's icon (sidebar menu, ActionBar launcher, admin panels, module
	// indicator, etc.) instead of a central map - see
	// modules/Base/Theme/bootstrap_icons.php.
	public static function bootstrap_icon() { return 'bi-envelope-fill'; }

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

    public static function admin_caption() {
        return array('label'=>__('Mail'), 'section'=>__('Features Configuration'));
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
            $param['archive_on_sending']=0; // default OFF: auto-archiving SENT mail is opt-in (per-account / compose toggle)
            $param['use_epesi_archive_directories']=1;
        }
        if($mode=='add' || $mode=='edit') {
            $param = self::encrypt_account_secret($param, $mode, 'password');
            $param = self::encrypt_account_secret($param, $mode, 'smtp_password');
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

    // On 'add', the field is always required (QFfield_password()/QFfield_smtp_password()) so it's
    // always present and non-empty - encrypt it. On 'edit', only trust the field's emptiness when
    // its '<field>_submitted' marker is present (a real form submission just went through) -
    // otherwise this is some other partial update (e.g. the grid's inline single-field edit) that
    // never touched this field, and RecordBrowserCommon_0.php's update_record() has already merged
    // the existing (already-encrypted) stored value into $param for it, which must NOT be
    // re-encrypted. Blank-and-submitted means "leave blank to keep current" - unset the key
    // entirely so update_record()'s own field-diff loop (`if (!isset($values[$desc['id']])) ...
    // continue;`) leaves that column untouched rather than nulling it out.
    private static function encrypt_account_secret($param, $mode, $field) {
        $marker = $field.'_submitted';
        if ($mode == 'add') {
            $param[$field] = self::encrypt($param[$field] ?? '');
        } elseif (isset($param[$marker])) {
            if (($param[$field] ?? '') === '') {
                unset($param[$field]);
            } else {
                $param[$field] = self::encrypt($param[$field]);
            }
        }
        unset($param[$marker]);
        return $param;
    }

    // ---- Password encryption at rest ----
    //
    // AES-256-GCM via openssl_encrypt, same shape as the one other encrypted-secret precedent in
    // this codebase (CRM_GoogleCalendarSyncCommon::encrypt()/decrypt(), see
    // AI-shared/mail-account-encryption-and-gmail-oauth.md for the design this follows). Key is a
    // random 32-byte file generated on first use, stored under this module's own data dir (outside
    // the DB, data/ is gitignored) - a compromise of one module's key doesn't expose another's.
    //
    // Threat model: protects stored passwords against a database-only compromise (a DB dump/leak/
    // backup theft without filesystem access). Does not protect against a compromised web server
    // process or filesystem-level access to encryption.key - that's an accepted trade-off, not a
    // gap to fix here.

    private static function get_encryption_key() {
        // ModuleManager::create_data_dir()/get_data_dir() resolve DATA_DIR (a bare relative
        // string, e.g. 'data' - see include/data_dir.php) against the current working
        // directory, which is fine for a normal request but not for
        // modules/Libs/RoundCube/RC/config/config.inc.php's own bootstrap: it chdir()s back
        // to Roundcube's own directory before calling decrypt(), so a relative path here
        // silently resolves to the wrong place (mkdir/file_get_contents failures, decrypt()
        // returning ''). Build an absolute path directly instead - same EPESI_LOCAL_DIR-
        // prefixed pattern that same config.inc.php already uses for its own tmp/log dirs.
        $dir = EPESI_LOCAL_DIR . '/' . DATA_DIR . '/CRM_Mail/';
        if (!is_dir($dir)) @mkdir($dir, 0700, true);
        $key_file = $dir . 'encryption.key';
        if (!file_exists($key_file)) {
            file_put_contents($key_file, random_bytes(32));
            @chmod($key_file, 0600);
        }
        return file_get_contents($key_file);
    }

    public static function encrypt($plain) {
        if ($plain === null || $plain === '') return '';
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt((string) $plain, 'aes-256-gcm', self::get_encryption_key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) return '';
        return base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt($encoded) {
        if (!$encoded) return '';
        $raw = base64_decode((string) $encoded, true);
        if ($raw === false || strlen($raw) < 28) return '';
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', self::get_encryption_key(), OPENSSL_RAW_DATA, $iv, $tag);
        return $plain === false ? '' : $plain;
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
                [$recordset, $record_id] = explode('/', $rel);
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
        $form->addRule($field,__('Account Name already in use'),$field,$rb->record['id'] ?? null);
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
        $form->addElement('password', $field, $label, array('id'=>$field,'autocomplete'=>'new-password','placeholder'=>$mode=='edit'?__('Leave blank to keep current password'):''));
        // Present on every real form submission but never a stored column, so it's never merged
        // back in from the old record on a partial edit (see submit_account()'s use of it) -
        // that's how we tell "left blank on purpose, keep existing" apart from "not part of this
        // submission at all" (RecordBrowser's update_record() otherwise re-merges the old,
        // already-encrypted value into any edit that didn't touch this field).
        $form->addElement('hidden', $field.'_submitted', 1);
        if ($mode == 'add') {
            $form->addRule($field,__('Field required'),'required');
        } else {
            // Never round-trip the stored (encrypted) value back into the page - blank on edit,
            // fixed-length mask on view.
            $form->setDefaults(array($field => ($mode == 'view' && $default !== '') ? '********' : ''));
        }
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
        $form->addElement('checkbox', $field, $label,'',array('onchange'=>'CRM_Mail.smtp_auth_change(this.checked)','id'=>$field,'class'=>'epesi-switch'));
        $form->setDefaults(array($field=>$default));
        // Dependent fields (SMTP Login/Password/Security, below) bake their own
        // initial disabled state from exportValue('smtp_auth') directly, so no
        // page-load eval_js() sync is needed here - onchange above still handles
        // live toggling from an actual user click. This matters beyond tidiness:
        // an unconditional page-load .prop('disabled', ...) poke on #smtp_pass
        // (formerly here) marks that field as script-touched, and Edge/Chrome
        // withhold their native password-reveal-eye affordance from password
        // fields mutated by script on load - unlike the plain #password field,
        // which no script ever touches and which does get the reveal eye.
        if($mode=='view') $form->freeze(array($field));
    }

    public static function QFfield_smtp_login(&$form, $field, $label, $mode, $default, $desc, $rb=null) {
        $attr = array('id'=>'smtp_login');
        if(!$form->exportValue('smtp_auth')) $attr['disabled'] = 'disabled';
        $form->addElement('text', $field, $label, $attr);
        $form->setDefaults(array($field=>$default));
        if($form->exportValue('smtp_auth'))
            $form->addRule($field,__('Field required'),'required');
        if($mode=='view') $form->freeze(array($field));
    }

    public static function QFfield_smtp_password(&$form, $field, $label, $mode, $default, $desc, $rb=null) {
        $attr = array('id'=>'smtp_pass','autocomplete'=>'new-password','placeholder'=>$mode=='edit'?__('Leave blank to keep current password'):'');
        if(!$form->exportValue('smtp_auth')) $attr['disabled'] = 'disabled';
        $form->addElement('password', $field, $label, $attr);
        $form->addElement('hidden', $field.'_submitted', 1); // see QFfield_password() for why
        $form->setDefaults(array($field => ($mode == 'view' && $default !== '') ? '********' : ''));
        // Required only when SMTP Auth is on AND there's no existing SMTP password to fall back
        // to (add, or edit of an account that never had one) - otherwise a blank field means
        // "keep the current one", same as the IMAP password field.
        if($form->exportValue('smtp_auth') && ($mode=='add' || $default===''))
            $form->addRule($field,__('Field required'),'required');
        if($mode=='view') $form->freeze(array($field));
    }

    public static function QFfield_smtp_security(&$form, $field, $label, $mode, $default, $desc, $rb=null) {
        $attr = array('id'=>'smtp_security');
        if(!$form->exportValue('smtp_auth')) $attr['disabled'] = 'disabled';
        $form->addElement('commondata', $field, $label,array('CRM/Mail/Security'),array('empty_option'=>true),$attr);
        $form->addRule($field,__('OpenSSL not available - cannot set TLS/SSL. Please contact EPESI administrator.'),'callback',array('CRM_MailCommon','check_ssl'));
        $form->setDefaults(array($field=>$default));
        if($mode=='view') $form->freeze(array($field));
    }

    public static function QFfield_default_account(&$form, $field, $label, $mode, $default, $desc, $rb=null) {
        $form->addElement('checkbox', $field, $label,'',array('class'=>'epesi-switch'));
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
				if(str_contains($t,(string) $m['email'])) {
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

    // Cc isn't a real rc_mails column (only From/To are archived that way, see
    // MailInstall.php's rc_mails field list) - it only exists inside the raw headers blob
    // archived alongside each mail (headers_data), which is blocked from the normal 'view'
    // field ACL (add_access('rc_mails','view',...) - the raw dump is noisy technical content,
    // not meant for the regular record view, hence no "Headers" tab anymore either - see
    // theme_adminltedark/mails.tpl). This pulls just the Cc line out of that blob instead of
    // exposing the whole thing.
    public static function get_cc_html($mail_id) {
        $headers = DB::GetOne('SELECT f_headers_data FROM rc_mails_data_1 WHERE id=%d', array($mail_id));
        if (!$headers || !preg_match('/^Cc:\s*(.+)$/mi', (string) $headers, $m)) return '';
        return self::format_address_list(trim($m[1]));
    }

    // From/To/Cc are archived as whatever the sending client's own header happened to contain
    // (rcube_mime::decode_mime_string() only undoes MIME encoded-words, see
    // Libs/RoundCube/RC/plugins/epesi_archive/epesi_archive.php) - some clients wrap the whole
    // "Name email@domain" pair in one pair of straight quotes with no <> around the address at
    // all (e.g. "Jane Doe jane@example.com"), which reads oddly verbatim. This
    // reformats each comma-separated entry to "Name" followed by a quoted address instead
    // (per request), same treatment for the standard Name <email@domain> form.
    public static function format_address_list($raw) {
        $raw = trim((string) $raw);
        if ($raw === '') return '';
        // Callers pass $raw_data field values (RecordBrowser's own record-
        // display prep), which are already HTML-escaped for direct output -
        // decode once before format_address()'s own htmlspecialchars() calls
        // below, or a stored value that already contains a literal quote
        // character (the straight-quoted "Name email@domain" form this
        // function specifically targets, see below) gets double-escaped:
        // the decoded '"' survives as real text, but the *encoded* one
        // (still reading as literal "&quot;" at this point) has its '&'
        // escaped into '&amp;' on top, rendering as literal "&quot;" text
        // on screen instead of a quote mark.
        $raw = html_entity_decode($raw, ENT_QUOTES);
        $out = array();
        foreach (self::split_address_list($raw) as $part) {
            $out[] = self::format_address($part);
        }
        return implode(', ', $out);
    }

    // Splits on commas that aren't inside a "..." quoted segment, so a quoted display name
    // containing a comma isn't split in half.
    private static function split_address_list($raw) {
        preg_match_all('/"[^"]*"|[^,]+/', $raw, $m);
        return array_map('trim', $m[0]);
    }

    private static function format_address($part) {
        $part = trim($part);
        if (strlen($part) >= 2 && $part[0] === '"' && substr($part, -1) === '"') {
            $part = trim(substr($part, 1, -1));
        }
        if ($part !== '' && preg_match('/^(.*?)\s*<?([^\s<>]+@[^\s<>]+?)>?$/', $part, $m)) {
            $name = trim($m[1]);
            $email = trim($m[2]);
            if ($name === '') return '"'.htmlspecialchars($email, ENT_QUOTES).'"';
            return htmlspecialchars($name, ENT_QUOTES).' "'.htmlspecialchars($email, ENT_QUOTES).'"';
        }
        return htmlspecialchars($part, ENT_QUOTES);
    }

    // "Date archived" (when the mail was pulled into EPESI) isn't an rc_mails field either -
    // distinct from the 'date' field (the e-mail's own Date header) - every RecordBrowser table
    // carries this as created_on/created_by metadata instead of a real column.
    public static function get_archived_on_html($mail_id) {
        $info = Utils_RecordBrowserCommon::get_record_info('rc_mails', $mail_id);
        return $info['created_on'] ? Base_RegionalSettingsCommon::time2reg($info['created_on']) : '';
    }

    // Attachment listing for theme_adminltedark/mails.tpl, replacing the old attachments_addon
    // tab: no inline image preview here even for PNG/JPG (an e-mail's own inline images already
    // render within the Body iframe itself, see get_html.php - duplicating them as oversized
    // previews here too just to download them again read worse, per request). Clicking the
    // filename opens a small View/Download prompt (Libs_LeightboxCommon - the same generic popup
    // Utils_Attachment's own file links use) rather than downloading immediately - get.php's
    // 'attachment' flag alone can only pick one fixed disposition, so both links pass an explicit
    // ?disposition= override to get the other one.
    //
    // Content styled to match Utils_FileStorage's own file popup (theme_adminltedark/
    // download.tpl's .epesi-filedl-* classes, loaded below) rather than a bare link list, per
    // request - built directly here instead of reusing Utils_FileStorage_FileLeightbox itself:
    // that helper (and the Utils_Attachment_FileActionHandler/Utils_RecordBrowser_FileActionHandler
    // machinery behind its own View/Download links) expects the file to be a genuine RecordBrowser
    // field value, which these attachments (rows in rc_mails_attachments, not an rc_mails field)
    // aren't - and it also pulls in "File History" and "Get link" (a public, unauthenticated,
    // 7-day download token - see Utils_FileStorage_RemoteActionHandler's forUsersOnly=false),
    // deliberately left out here rather than exposing an unauthenticated share link for private
    // archived mail without being asked for it specifically.
    public static function get_attachments_html($mail_id) {
        $rows = DB::Execute('SELECT mime_id, name, file_id FROM rc_mails_attachments WHERE mail_id=%d AND attachment=1 ORDER BY name', array($mail_id));
        Base_ThemeCommon::load_css('Utils_FileStorage', 'download');
        $files = '';
        while ($a = $rows->FetchRow()) {
            $name = htmlspecialchars((string) $a['name'], ENT_QUOTES);
            $size = self::get_attachment_size($a['file_id'], $mail_id, $a['mime_id']);
            $size_label = htmlspecialchars($size !== null ? self::format_file_size($size) : __('Unknown'), ENT_QUOTES);

            $lid = 'crm_mail_attachment_'.$mail_id.'_'.$a['mime_id'];
            $close_js = 'leightbox_deactivate(\''.$lid.'\');';
            $view_url = 'modules/CRM/Mail/get.php?'.http_build_query(array('mime_id'=>$a['mime_id'],'mail_id'=>$mail_id,'disposition'=>'inline'));
            $download_url = 'modules/CRM/Mail/get.php?'.http_build_query(array('mime_id'=>$a['mime_id'],'mail_id'=>$mail_id,'disposition'=>'attachment'));

            $content = '<div class="epesi-filedl-info">'
                     .   '<div class="epesi-filedl-row"><div class="epesi-filedl-label">'.__('Filename').'</div><div class="epesi-filedl-value">'.$name.'</div></div>'
                     .   '<div class="epesi-filedl-row"><div class="epesi-filedl-label">'.__('File size').'</div><div class="epesi-filedl-value">'.$size_label.'</div></div>'
                     . '</div>'
                     . '<div class="epesi-filedl-actions">'
                     .   '<a href="'.$view_url.'" target="_blank" onclick="'.$close_js.'"><span class="epesi-filedl-btn"><i class="bi bi-eye"></i><span class="epesi-filedl-btn-label">'.__('View').'</span></span></a>'
                     .   '<a href="'.$download_url.'" onclick="'.$close_js.'"><span class="epesi-filedl-btn"><i class="bi bi-download"></i><span class="epesi-filedl-btn-label">'.__('Download').'</span></span></a>'
                     . '</div>';
            $popup = Libs_LeightboxCommon::get($lid, $content, __('File'));

            $files .= '<div class="crm-mail-attachment-file"><a '.Libs_LeightboxCommon::get_open_href($lid).'>'.$name.'</a>'.($size!==null?' <span class="crm-mail-attachment-size">('.self::format_file_size($size).')</span>':'').'</div>'.$popup;
        }
        return $files;
    }

    // Attachments migrated from plain files under DATA_DIR to Utils_FileStorage (see
    // patches/20260629_mail_attachments_to_filestorage.php) - same dual lookup as get.php's own
    // file-serving fallback, needed here too since old rows may still be legacy-only.
    private static function get_attachment_size($file_id, $mail_id, $mime_id) {
        if ($file_id) {
            try {
                return Utils_FileStorageCommon::meta($file_id)['size'];
            } catch (Exception $e) {
            }
        }
        $legacy = DATA_DIR.'/CRM_Mail/attachments/'.$mail_id.'/'.$mime_id;
        return file_exists($legacy) ? filesize($legacy) : null;
    }

    private static function format_file_size($bytes) {
        $units = array('B','kB','MB','GB');
        for ($i = 0; $i < count($units) - 1 && $bytes >= 1024; $i++) $bytes /= 1024;
        return round($bytes, $bytes < 10 && $i > 0 ? 1 : 0).' '.$units[$i];
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

    public static function create_thread($id) {
        $m = Utils_RecordBrowserCommon::get_record('rc_mails',$id);
        $thread = $m['thread'];
        if(!$thread && $m['message_id'])
          $thread = DB::GetOne('SELECT f_thread FROM rc_mails_data_1 WHERE f_references is not null AND f_references LIKE '.DB::Concat('\'%%\'','%s','\'%%\'').' AND active=1',array($m['message_id']));
        if(!$thread && $m['references'])
          $thread = DB::GetOne('SELECT f_thread FROM rc_mails_data_1 WHERE f_message_id is not null AND %s LIKE '.DB::Concat('\'%%\'','f_message_id','\'%%\'').' AND active=1',array($m['references']));
        if(!$thread)
          $thread = Utils_RecordBrowserCommon::new_record('rc_mail_threads',array('subject'=>$m['subject'],'contacts'=>array_unique(array_merge($m['contacts'],array('contact/'.$m['employee']))),'first_date'=>$m['date'],'last_date'=>$m['date']));
        Utils_RecordBrowserCommon::update_record('rc_mails',$id,array('thread'=>$thread), false, null, true);
        $t = Utils_RecordBrowserCommon::get_record('rc_mail_threads',$thread);
        Utils_RecordBrowserCommon::update_record('rc_mail_threads',$thread,array('contacts'=>array_unique(array_merge($t['contacts'],$m['contacts'],array('contact/'.$m['employee']))),'first_date'=>strtotime($m['date'])<strtotime($t['first_date'])?$m['date']:$t['first_date'],'last_date'=>strtotime($m['date'])>strtotime($t['last_date'])?$m['date']:$t['last_date'],'subject'=>(trim($m['references'])=='' ||  mb_strlen($m['subject'])<mb_strlen($t['subject']))?$m['subject']:$t['subject']));
    }

    public static function subscribe_users_to_record($record)
    {
        $employee = $record['employee'];
        $contacts = $record['contacts'];
        $subscribers = $employee ? Utils_WatchdogCommon::get_subscribers('contact', $employee) : array();
        foreach ($contacts as $c) {
            [$rs_full, $con_id] = CRM_ContactsCommon::decode_record_token($c);
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
            $rec[$r2['id']] = $r2;
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
        $prefix = $rs . '/';

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
        $server_str = '{' . self::strip_server_port($rec['server']) . ':' . $port . '/imap/readonly/novalidate-cert' . ($rec['security'] ? '/' . $rec['security'] : '') . '}';
        $cache_key = 'crm_mail_'.md5($server_str . ' # ' . $rec['login'] . ' # ' . $rec['password']);
        if ($cache_validity_in_minutes) {
            $unread_messages = Cache::get($cache_key);
            if($unread_messages) return $unread_messages;
        }
        if ($return === null && $only_cached === false) {
            @set_time_limit(0);
            $mailbox = @imap_open(imap_utf7_encode($server_str), imap_utf7_encode($rec['login']), imap_utf7_encode(self::decrypt($rec['password'])), OP_READONLY || OP_SILENT);
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
                            $date = $msg->date ?? '';
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
                Cache::set($cache_key, $return, $cache_validity_in_minutes);
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
            } catch (Exception) {
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
            return array(array('name'=>'no_accounts','label'=>'','type'=>'static','values'=>__('No accounts configured, go Menu->My settings->Control panel->E-mail accounts')));
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
            $ret[] = 'contact/'.$contact_id;
        }
        $fields = DB::GetCol('SELECT field FROM company_field WHERE active=1 AND type=\'text\' AND field LIKE \'%mail%\' ORDER BY field');
        foreach($fields as & $f) {
            $f = 'c.f_'.preg_replace('/[^a-z0-9]/','_',strtolower($f));
        }
        $company = DB::GetCol('SELECT c.id FROM company_data_1 c LEFT JOIN rc_multiple_emails_data_1 m ON (m.f_record_id=c.id AND m.f_record_type=%s AND m.active=1) WHERE c.active=1 AND ('.implode('='.DB::qstr($addr).' OR ',$fields).'='.DB::qstr($addr).' OR m.f_email=%s) AND (c.f_permission<%s OR c.created_by=%d)',array('company',$addr,2,$user));
        foreach($company as $company_id) {
            $ret[] = 'company/'.$company_id;
        }

        return $ret;
    }

    public static function archive_message($message_id,$references,$contacts,$date,$subject,$body,$headers,$from,$to,$employee,$attachments) {
        $data = array('message_id'=>$message_id,'references'=>$references,'contacts'=>$contacts,'date'=>$date,'subject'=>substr($subject,0,256),'body'=>$body,'headers_data'=>$headers,'from'=>$from,'to'=>$to,'employee'=>$employee);
        $id = Utils_RecordBrowserCommon::new_record('rc_mails',$data);

        if(is_array($attachments))
            foreach($attachments as $m) {
                // Store the attachment in the central, deduplicated Utils_FileStorage.
                // write_content() returns the FILESTORAGE (storage-object) id — the id that
                // read_content()/file_exists()/meta() expect (NOT the low-level content id from
                // add_data_from_content()).
                $content = $m['content'];
                $file_id = Utils_FileStorageCommon::write_content($m['filename'], $content, null, 'rb:rc_mails/'.$id);
                DB::Execute('INSERT INTO rc_mails_attachments(mail_id,type,name,mime_id,attachment,file_id) VALUES(%d,%s,%s,%s,%b,%d)',array($id,$m['type'],$m['filename'],$m['mime_id'],$m['attachment'],$file_id));
            }
        return $id;
    }

    public static function get_attachment_url($mime_id) {
        return 'get.php?'.http_build_query(array('mail_id'=>'__MAIL_ID__','mime_id'=>$mime_id));
    }

    /**
     * The 'Server' account field is free text and some users enter it as
     * "host:port" (following the convention used elsewhere), but the port is
     * already tracked separately via the account's security setting - strip
     * it here so callers never build a mailbox spec with a duplicated port.
     */
    private static function strip_server_port($server) {
        return preg_replace('/:\d+$/', '', trim($server));
    }

    public static function get_connection($rec) {
        error_reporting(error_reporting() & ~E_NOTICE); //fetch sometimes gives E_NOTICE on email parse error

        static $cache = array();
        if(is_numeric($rec)) {
            if(isset($cache[$rec])) return $cache[$rec];
            $rec = Utils_RecordBrowserCommon::get_record('rc_accounts', $rec);
            if ($rec['epesi_user'] != Acl::get_user()) {
                throw new Exception('Invalid account id');
            }
        } elseif(isset($cache[$rec['id']])) return $cache[$rec['id']];

        $port = $rec['security'] == 'ssl' ? 993 : 143;
        $server = new \Fetch\Server(self::strip_server_port($rec['server']), $port);
        $server->setAuthentication($rec['login'], self::decrypt($rec['password']));
        $server->setFlag('readonly');
        $server->setFlag('novalidate-cert');
        if($rec['security']) $server->setFlag($rec['security']);

        @set_time_limit(0);
        $cache[$rec['id']] = $server;
        return $server;
    }

    public static function get_folders($rec) {
        static $cache = array();
        $mailbox = self::get_connection($rec);
        $srvstr = $mailbox->getServerString();
        if(isset($cache[$srvstr])) return $cache[$srvstr];
        $folders = $mailbox->listMailBoxes();
        foreach($folders as &$folder) $folder = mb_convert_encoding(str_replace($mailbox->getServerString(),'',$folder), "UTF-8", "UTF7-IMAP");
        sort($folders);
        $cache[$srvstr] = $folders;
        return $folders;
    }

    public static function decode_mime_header($header) {
        $elems = imap_mime_header_decode($header);
        $ret = '';
        foreach($elems as $elem) {
            $ret .= $elem->text;
        }
        return $ret;
    }

    public static function archive($connection,$message_id) {

    }
}

?>
