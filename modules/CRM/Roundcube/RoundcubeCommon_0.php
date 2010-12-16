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

class CRM_RoundcubeCommon extends ModuleCommon {
    public static function menu() {
	    if(self::Instance()->acl_check('access client')) {
            return array('E-mail'=>array());
        }
        return array();
    }

    public static function user_settings() {
	    if(self::Instance()->acl_check('access client')) {
            return array('E-mail Accounts'=>'account_manager');
        }
        return array();
    }

    public static function QFfield_epesi_user(&$form, $field, $label, $mode, $default, $desc, $rb=null) {
        $form->addElement('select', $field, $label,array($default=>Base_UserCommon::get_user_login($default)))->freeze();
        $form->setDefaults(array($field=>$default));
    }

    public static function QFfield_password(&$form, $field, $label, $mode, $default, $desc, $rb=null) {
        $form->addElement('password', $field, $label);
        $form->setDefaults(array($field=>$default));
        $form->addRule($field,Base_LangCommon::ts('Libs_QuickForm','Field required'),'required');
        if($mode=='view') $form->freeze(array($field));
    }

    public static function QFfield_smtp_auth(&$form, $field, $label, $mode, $default, $desc, $rb=null) {
        eval_js_once('var smtp_auth_change = function(val){if(val){$("smtp_login").enable();$("smtp_pass").enable();$("smtp_security").enable();}else{$("smtp_login").disable();$("smtp_pass").disable();$("smtp_security").disable();}}');
        $form->addElement('checkbox', $field, $label,'',array('onChange'=>'smtp_auth_change(this.checked)'));
        $form->setDefaults(array($field=>$default));
        eval_js('smtp_auth_change('.($default?1:0).')');
        if($mode=='view') $form->freeze(array($field));
    }

    public static function QFfield_smtp_login(&$form, $field, $label, $mode, $default, $desc, $rb=null) {
        $form->addElement('text', $field, $label,array('id'=>'smtp_login'));
        $form->setDefaults(array($field=>$default));
        if($form->exportValue('smtp_auth'))
            $form->addRule($field,Base_LangCommon::ts('Libs_QuickForm','Field required'),'required');
        if($mode=='view') $form->freeze(array($field));
    }

    public static function QFfield_smtp_password(&$form, $field, $label, $mode, $default, $desc, $rb=null) {
        $form->addElement('password', $field, $label,array('id'=>'smtp_pass'));
        $form->setDefaults(array($field=>$default));
        if($form->exportValue('smtp_auth'))
            $form->addRule($field,Base_LangCommon::ts('Libs_QuickForm','Field required'),'required');
        if($mode=='view') $form->freeze(array($field));
    }

    public static function QFfield_smtp_security(&$form, $field, $label, $mode, $default, $desc, $rb=null) {
        $form->addElement('commondata', $field, $label,array('CRM/Roundcube/Security'),array('empty_option'=>true),array('id'=>'smtp_security'));
        $form->addRule($field,Base_LangCommon::ts('CRM_Roundcube','OpenSSL not available - cannot set TLS/SSL. Please contact Epesi administrator.'),'callback',array('CRM_RoundcubeCommon','check_ssl'));
        $form->setDefaults(array($field=>$default));
        if($mode=='view') $form->freeze(array($field));
    }
    
    public static function QFfield_security(&$form, $field, $label, $mode, $default, $desc, $rb=null) {
        $form->addElement('commondata', $field, $label,array('CRM/Roundcube/Security'),array('empty_option'=>true));
        $form->addRule($field,Base_LangCommon::ts('CRM_Roundcube','OpenSSL not available - cannot set TLS/SSL. Please contact Epesi administrator.'),'callback',array('CRM_RoundcubeCommon','check_ssl'));
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

    public static function access_mails($action, $param=null) {
        $i = self::Instance();
	    if(!$i->acl_check('access mails')) return false;
        switch ($action) {
            case 'browse_crits':    return true;
            case 'browse':  return true;
            case 'view':    return array('headers_data'=>false);
            case 'clone':
            case 'add':
            case 'edit':    return false;
            case 'delete':  return true;
        }
        return false;

    }

    public static function access_mails_assoc($action, $param=null) {
        $i = self::Instance();
	    if(!$i->acl_check('access mails')) return false;
        switch ($action) {
            case 'browse_crits':    return true;
            case 'browse':  return true;
            case 'view':    return array('recordset'=>false);
            case 'clone':
            case 'add':
            case 'edit':    return false;
            case 'delete':  return true;
        }
        return false;

    }

    public static function QFfield_body(&$form, $field, $label, $mode, $default, $desc, $rb=null) {
        //$form->addElement('static', $field, $label,DB::GetOne('SELECT f_body FROM rc_mails_data_1 WHERE id=%d',array($rb->record['id'])));
        $form->addElement('static', $field, $label,'<iframe id="rc_mail_body" src="modules/CRM/Roundcube/get_body.php?'.http_build_query(array('id'=>$rb->record['id'])).'" style="width:100%;border:0" border="0"></iframe>');
    }

    public static function QFfield_headers(&$form, $field, $label, $mode, $default, $desc, $rb=null) {
        Libs_LeightboxCommon::display('mail_headers',$rb->record['headers_data'],Base_LangCommon::ts('CRM_Roundcube','Mail Headers'));
        $form->addElement('static', $field, $label,'<a '.Libs_LeightboxCommon::get_open_href('mail_headers').'>'.Base_LangCommon::ts('CRM_Roundcube','display').'</a>');
    }

    public static function QFfield_attachments(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
    }

    public static function display_attachments($record, $nolink, $desc) {
        return DB::GetOne('SELECT count(mime_id) FROM rc_mails_attachments WHERE mail_id=%d AND attachment=1',array($record['id']));
    }

    public static function display_subject($record, $nolink, $desc) {
        return Utils_RecordBrowserCommon::create_linked_label_r('rc_mails','subject',$record,$nolink);
    }

    public static function QFfield_direction(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
        if($default)
            $txt = Base_LangCommon::ts('CRM_Roundcube','Sent by employee');
        else
            $txt = Base_LangCommon::ts('CRM_Roundcube','Received by employee');
        $form->addElement('static', $field, $label,$txt);
    }

    public static function display_direction($record, $nolink, $desc) {
        if($record['direction'])
            return '<=';
        return '=>';
    }

    public static function display_record_id($r, $nolink=false) {
        return Utils_RecordBrowserCommon::create_default_linked_label($r['recordset'],$r['record_id']);
    }

    public static function applet_caption() {
        if(function_exists('imap_open'))
            return "Mail indicator";
        return false;
    }

    public static function applet_info() {
        return "Checks if there is new mail";
    }

    public static function applet_settings() {
        $conf = array(array('type'=>'header','label'=>'Choose accounts'));
        $ret = Utils_RecordBrowserCommon::get_records('rc_accounts',array('epesi_user'=>Acl::get_user()));
        foreach($ret as $row)
                $conf[] = array('name'=>'account_'.$row['id'], 'label'=>$row['login'].' at '.$row['server'], 'type'=>'checkbox', 'default'=>1);
        if(count($conf)==1)
            return array(array('type'=>'static','label'=>'No accounts configured, go Home->My settings->Mail accounts'));
        return $conf;
    }

    public static function new_addon($rs) {
        Utils_RecordBrowserCommon::new_addon($rs, 'CRM/Roundcube', 'addon', 'Mails');
    }

    public static function delete_addon($rs) {
        Utils_RecordBrowserCommon::delete_addon($rs, 'CRM/Roundcube', 'addon');
    }

	public static function access_mail_addresses($action, $param=null){
		$i = self::Instance();
		switch ($action) {
			case 'browse_crits':	return true;
			case 'browse':	return true;
			case 'view':	return array('record_type'=>false,'record_id'=>false);
			case 'clone':
			case 'add':
			case 'edit':	
			case 'delete':	return true;
		}
		return false;
    }

	public static function new_mail_addresses_addon($table) {
		Utils_RecordBrowserCommon::new_addon($table, 'CRM/Roundcube', 'mail_addresses_addon', 'Mail addresses');
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
			$form->addRule($field, Base_LangCommon::ts('CRM_Roundcube','Field required'), 'required');
			if ($mode=='edit') {
				$form->addRule($field, Base_LangCommon::ts('CRM_Roundcube','Nickname already in use'), 'check_nickname',array($y->record['record_type'],$y->record['record_id'],$y->record['id']));
				$form->setDefaults(array($field=>$default));
			} else {
				$rec = $y->get_custom_defaults();
				$form->addRule($field, Base_LangCommon::ts('CRM_Roundcube','Nickname already in use'), 'check_nickname',array($rec['record_type'],$rec['record_id']));
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
            $x->push_main('CRM_Roundcube','new_mail',array($v));
            unset($_REQUEST['rc_mailto']);
        }
        $ret = Utils_RecordBrowserCommon::get_records_count('rc_accounts',array('epesi_user'=>Acl::get_user()));
        if($ret) {
//    	    return '<a '.Base_BoxCommon::create_href('','CRM_Roundcube','new_mail',array($v)).'>'.$v.'</a>';
      	    return '<a '.Module::create_href(array('rc_mailto'=>$v)).'>'.$v.'</a>';
      	}
    	return '<a href="mailto:'.$v.'">'.$v.'</a>';
	}
}

?>
