<?php
/**
 * Roundcube bindings
 * @author pbukowski@telaxus.com
 * @copyright Telaxus LLC
 * @license GPL
 * @version 0.1
 * @package epesi-CRM
 * @subpackage Roundcube
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_RoundcubeCommon extends Base_AdminModuleCommon {
    public static function menu() {
		if (Utils_RecordBrowserCommon::get_access('rc_accounts', 'browse'))
			return array(_M('E-mail')=>array());
        return array();
    }
	public static function addon_access() {
		return Utils_RecordBrowserCommon::get_access('contact','browse');
	}

    public static function user_settings() {
	    if(Utils_RecordBrowserCommon::get_access('rc_accounts', 'browse')) {
            return array(__('E-mail Accounts')=>'account_manager');
        }
        return array();
    }

    public static function QFfield_account_name(&$form, $field, $label, $mode, $default, $desc, $rb=null) {
        $form->addElement('text', $field, $label,array('id'=>$field));
        $form->registerRule($field,'function','check_account_name','CRM_RoundcubeCommon');
        $form->addRule($field,__('Account Name already in use'),$field,isset($rb->record['id'])?$rb->record['id']:null);
        $form->setDefaults(array($field=>$default));
        load_js('modules/CRM/Roundcube/utils.js');
        eval_js('CRM_RC.filled_smtp_message=\''.Epesi::escapeJS(__('SMTP login and password was filled with imap account details. Please change them if needed.'),false,true).'\';CRM_RC.edit_form()');
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
    
    
    public static function QFfield_epesi_user(&$form, $field, $label, $mode, $default, $desc, $rb=null) {
        $form->addElement('hidden', $field, $default);
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

    public static function QFfield_smtp_auth(&$form, $field, $label, $mode, $default, $desc, $rb=null) {
        eval_js_once('var smtp_auth_change = function(val){if(val){$("smtp_login").enable();$("smtp_pass").enable();$("smtp_security").enable();}else{$("smtp_login").disable();$("smtp_pass").disable();$("smtp_security").disable();}}');
        $form->addElement('checkbox', $field, $label,'',array('onChange'=>'smtp_auth_change(this.checked)','id'=>$field));
        $form->setDefaults(array($field=>$default));
        eval_js('smtp_auth_change('.($default?1:0).')');
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
        $form->addElement('commondata', $field, $label,array('CRM/Roundcube/Security'),array('empty_option'=>true),array('id'=>'smtp_security'));
        $form->addRule($field,__('OpenSSL not available - cannot set TLS/SSL. Please contact EPESI administrator.'),'callback',array('CRM_RoundcubeCommon','check_ssl'));
        $form->setDefaults(array($field=>$default));
        if($mode=='view') $form->freeze(array($field));
    }
    
    public static function QFfield_security(&$form, $field, $label, $mode, $default, $desc, $rb=null) {
        $form->addElement('commondata', $field, $label,array('CRM/Roundcube/Security'),array('empty_option'=>true));
        $form->addRule($field,__('OpenSSL not available - cannot set TLS/SSL. Please contact EPESI administrator.'),'callback',array('CRM_RoundcubeCommon','check_ssl'));
        $form->setDefaults(array($field=>$default));
        if($mode=='view') $form->freeze(array($field));
    }
    
    public static function check_ssl($o) {
        if($o=='ssl' || $o=='tls') return extension_loaded('openssl');
        return true;
    }

    public static function display_epesi_user($record, $nolink, $desc) {
        return Base_UserCommon::get_user_login($record['epesi_user']);
    }

    public static function QFfield_default_account(&$form, $field, $label, $mode, $default, $desc, $rb=null) {
        $form->addElement('checkbox', $field, $label,'');
        $form->setDefaults(array($field=>$default));
        if($mode=='view' || $default) $form->freeze(array($field));
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
        return $param;
    }

    public static function submit_mail($param, $mode) {
        if($mode=='delete') {
	    $m = Base_BoxCommon::main_module_instance();
	    $id = $m->record['id'];
	    $rs = $m->tab;
	    $c = Utils_RecordBrowserCommon::get_records('rc_mails_assoc',array('mail'=>$param['id'],'recordset'=>$rs,'record_id'=>$id),array('id'));
	    if(count($c)) {
		foreach($c as $cc)
		    Utils_RecordBrowserCommon::delete_record('rc_mails_assoc',$cc['id']);
		location(array());
		return false;
	    }
	}
        return $param;
    }

    public static function QFfield_body(&$form, $field, $label, $mode, $default, $desc, $rb=null) {
        //$form->addElement('static', $field, $label,DB::GetOne('SELECT f_body FROM rc_mails_data_1 WHERE id=%d',array($rb->record['id'])));
        $form->addElement('static', $field, $label,'<iframe id="rc_mail_body" src="modules/CRM/Roundcube/get_html.php?'.http_build_query(array('id'=>$rb->record['id'])).'" style="width:100%;border:0" border="0"></iframe>');
    }

    public static function QFfield_attachments(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
	if(isset($_GET['rc_reply']) && $_GET['rc_reply']==$rb_obj->record['id']) {
		Base_BoxCommon::push_module('CRM_Roundcube','new_mail',array($rb_obj->record['from'],(preg_match('/^Re:/i',$rb_obj->record['subject'])?'':'Re: ').$rb_obj->record['subject'],'<br /><br /><stron>On '.Base_RegionalSettingsCommon::time2reg($rb_obj->record['date']).', '.$rb_obj->record['from'].' wrote:</strong><br/>'.$rb_obj->record['body']));
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
		Base_BoxCommon::push_module('CRM_Roundcube','new_mail',array(implode(', ',$to),(preg_match('/^Re:/i',$rb_obj->record['subject'])?'':'Re: ').$rb_obj->record['subject'],'<br /><br /><stron>On '.Base_RegionalSettingsCommon::time2reg($rb_obj->record['date']).', '.$rb_obj->record['from'].' wrote:</strong><br/>'.$rb_obj->record['body']));
	} elseif(isset($_GET['rc_forward']) && $_GET['rc_forward']==$rb_obj->record['id']) {
		Base_BoxCommon::push_module('CRM_Roundcube','new_mail',array('',(preg_match('/^Re:/i',$rb_obj->record['subject'])?'':'Re: ').$rb_obj->record['subject'],'<br /><br /><stron>On '.Base_RegionalSettingsCommon::time2reg($rb_obj->record['date']).', '.$rb_obj->record['from'].' wrote:</strong><br/>'.$rb_obj->record['body']));
	}
	Base_ActionBarCommon::add('back','Reply', Module::create_href(array('rc_reply'=>$rb_obj->record['id'])));
	Base_ActionBarCommon::add('back','Reply All', Module::create_href(array('rc_replyall'=>$rb_obj->record['id'])));
	Base_ActionBarCommon::add('edit','Forward', Module::create_href(array('rc_forward'=>$rb_obj->record['id'])));
    }

    public static function display_attachments($record, $nolink, $desc) {
        return DB::GetOne('SELECT count(mime_id) FROM rc_mails_attachments WHERE mail_id=%d AND attachment=1',array($record['id']));
    }

    public static function display_subject($record, $nolink, $desc) {
		return Utils_RecordBrowserCommon::create_linked_label_r('rc_mails','subject',$record,$nolink).'<br />From: '.$record['from'].'<br />To: '.$record['to'];
	}

    public static function QFfield_direction(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
        if($default)
            $txt = __('Sent by employee');
        else
            $txt = __('Received by employee');
        $form->addElement('static', $field, $label,$txt);
    }

    public static function display_direction($record, $nolink, $desc) {
        if($record['direction'])
            return '<div class="direction dark_blue_gradient border_radius_3px"><</div>';
        return '<div class="direction dark_blue_gradient border_radius_3px">></div>';
    }

    public static function display_record_id($r, $nolink=false) {
        return Utils_RecordBrowserCommon::create_default_linked_label($r['recordset'],$r['record_id']);
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
                $conf[] = array('name'=>'account_'.$row['id'], 'label'=>__('%s at %s', array($row['login'],$row['server'])), 'type'=>'checkbox', 'default'=>1);
        if(count($conf)==1)
            return array(array('type'=>'static','label'=>__('No accounts configured, go Menu->My settings->Control panel->E-mail accounts')));
        return $conf;
    }

    public static function new_addon($rs) {
        Utils_RecordBrowserCommon::new_addon($rs, 'CRM/Roundcube', 'addon', _M('E-mails'));
    }

    public static function delete_addon($rs) {
        Utils_RecordBrowserCommon::delete_addon($rs, 'CRM/Roundcube', 'addon');
    }

	public static function new_mail_addresses_addon($table) {
		Utils_RecordBrowserCommon::new_addon($table, 'CRM/Roundcube', 'mail_addresses_addon', _M('Mail addresses'));
	}
	
	/**
	 * Gets associative array: nickname=>address.
	 * You can use it in selects with $a = Premium_MultipleAddressesCommon::get(..., ...); $k=array_keys($a); $select = array_combine($k,$k);
	 */
	public static function get_mail_addresses($tab,$rec_id) {
		$r = Utils_RecordBrowserCommon::get_records('rc_multiple_emails',array('record_type'=>$tab,'record_id'=>$rec_id));
		$rec = array();
		foreach($r as $r2)
			$rec[$r2['nickname']] = $r2;
		return $rec;
	}
	
	public static function QFfield_nickname(&$form, $field, $label, $mode, $default,$x,$y) {
		if ($mode=='add' || $mode=='edit') {
			$form->addElement('text', $field, $label);
			$form->registerRule('check_nickname','callback','check_nickname','CRM_RoundcubeCommon');
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
	
	public static function get_mailto_link($v) {
        if(isset($_REQUEST['rc_mailto'])) {
            $x = ModuleManager::get_instance('/Base_Box|0');
            $x->push_main('CRM_Roundcube','new_mail',array($_REQUEST['rc_mailto']));
            unset($_REQUEST['rc_mailto']);
        }
        $ret = Utils_RecordBrowserCommon::get_records_count('rc_accounts',array('epesi_user'=>Acl::get_user()));
        if($ret) {
//    	    return '<a '.Base_BoxCommon::create_href('','CRM_Roundcube','new_mail',array($v)).'>'.$v.'</a>';
      	    return '<a '.Module::create_href(array('rc_mailto'=>$v)).'>'.$v.'</a>';
      	}
    	return '<a href="mailto:'.$v.'">'.$v.'</a>';
	}
	
	public static function admin_caption() {
		return array('label'=>__('Outgoing mail global signature'), 'section'=>__('Server Configuration'));
	}

	public static function attachment_getters() {
	        $ret = Utils_RecordBrowserCommon::get_records_count('rc_accounts',array('epesi_user'=>Acl::get_user()));
		if($ret)
			return array(_M('Mail')=>array('func'=>'mail_file','icon'=>Base_ThemeCommon::get_template_file('CRM/Roundcube', 'icon.png')));
	}

	public static function mail_file($f,$d,$file_id) {
		$t = time()+3600*24*7;
		$url = Utils_AttachmentCommon::create_remote($file_id, 'mail', $t);
		$x = ModuleManager::get_instance('/Base_Box|0');
		$x->push_main('CRM_Roundcube','new_mail',array('',__('File attachment, expires on: %s',array(Base_RegionalSettingsCommon::time2reg($t))),"<br /><br />".$url));
	}
}

?>
