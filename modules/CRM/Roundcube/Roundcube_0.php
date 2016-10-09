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

class CRM_Roundcube extends Module {
    public $rb;

    public function body($params2=array(),$def_account_id=null) {
        $accounts = Utils_RecordBrowserCommon::get_records('rc_accounts',array('epesi_user'=>Acl::get_user()));
        $def = null;
        $user_def = null;
        $def_id = $this->get_module_variable('default',$def_account_id);
        foreach($accounts as $a) {
            if($def===null) $def = $a;
            if($a['default_account']) $user_def = $a;
            if($def_id===null && $a['default_account']) {
                $def = $a;
                break;
            } elseif($a['id']==$def_id) {
                $def = $a;
                break;
            }
        }
        foreach($accounts as $a) {
            Base_ActionBarCommon::add('add',($a==$def?'<b><u>'.$a['account_name'].'</u></b>':$a['account_name']), $this->create_callback_href(array($this,'account'),$a['id']),$a['email'],$a==$user_def?-1:0);
        }
        if($def===null) {
			print('<h1><a '.$this->create_callback_href(array($this,'push_settings'),array(__('E-mail Accounts'))).'>Please set your e-mail account</a></h1>');
            return;
        }
        $params = array('_autologin_id'=>$def['id'])+$params2;
//        if($params2) $params['_url'] = http_build_query($params2);
        print('<div style="background:transparent url(images/loader-0.gif) no-repeat 50% 50%;"><iframe style="border:0" border="0" src="modules/CRM/Roundcube/RC/index.php?'.http_build_query($params).'" width="100%" height="300px" id="rc_frame"></iframe></div>');
        eval_js('var dim=document.viewport.getDimensions();var rc=$("rc_frame");rc.style.height=(Math.max(dim.height,document.documentElement.clientHeight)-130)+"px";');
        $epesi_mail_url = get_epesi_url() . '?rc_mailto=%s';
        $epesi_mail_name = EPESI . ' - ' . get_epesi_url();
        eval_js_once("if (typeof navigator != 'undefined') { navigator.registerProtocolHandler('mailto', '$epesi_mail_url', '$epesi_mail_name'); }");
    }

    public function push_settings($s) {
        $x = ModuleManager::get_instance('/Base_Box|0');
        if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
        $x->push_main('Base_User_Settings',null,array($s));
    }

    public function new_mail($to='',$subject='',$body='',$message_id='',$references='') {
        if (strpos($to, 'mailto:') === 0) {
            $this->body(array('mailto' => $to));
            unset($_SESSION['rc_body']);
            unset($_SESSION['rc_to']);
            unset($_SESSION['rc_subject']);
            unset($_SESSION['rc_reply']);
            unset($_SESSION['rc_references']);
        } else {
            $this->body(array('mailto' => 1));
            $_SESSION['rc_body'] = $body;
            $_SESSION['rc_to'] = $to;
            $_SESSION['rc_subject'] = $subject;
            $_SESSION['rc_reply'] = $message_id;
            $_SESSION['rc_references'] = $references;
        }
    }

    public function account($id) {
        $this->set_module_variable('default',$id);
    }

    public function caption() {
        return __('Roundcube Mail Client');
    }

}

?>
