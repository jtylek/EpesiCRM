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
        if(Acl::is_user()) {
            return array('E-mail'=>array());
        }
        return array();
    }

    public static function user_settings() {
        if(Acl::is_user()) {
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
        $form->setDefaults(array($field=>$default));
        if($mode=='view') $form->freeze(array($field));
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

    public static function new_addon($recordset) {
        Utils_RecordBrowserCommon::new_addon($recordset, 'CRM/Roundcube', 'addon', 'Mails');
    }

    public static function access_mails($action, $param=null) {
        $i = self::Instance();
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
        $attachments = DB::GetAssoc('SELECT mime_id,name FROM rc_mails_attachments WHERE mail_id=%d AND attachment=1',array($rb_obj->record['id']));
        foreach($attachments as $k=>&$n)
            $n = '<a href="modules/CRM/Roundcube/get.php?'.http_build_query(array('mime_id'=>$k,'mail_id'=>$rb_obj->record['id'])).'" target="_blank">'.$n.'</a>';
        if($attachments)
            $form->addElement('static',$field,$label,($attachments?implode(', ',$attachments):''));
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
        Utils_RecordBrowserCommon::new_addon($rs, 'CRM/Roundcube', 'addon');
    }

}

?>
