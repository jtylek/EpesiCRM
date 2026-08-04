<?php
/**
 * Roundcube bindings
 * @author pbukowski@telaxus.com
 * @copyright Janusz Tylek
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
			$href = $this->create_callback_href(array($this,'push_settings'),array(__('E-mail Accounts')));
			if (Base_ThemeCommon::is_adminlte_family()) {
				print('<div class="text-center text-muted" style="padding:4rem 1rem;">'
					.'<i class="bi bi-envelope-plus" style="font-size:3rem;"></i>'
					.'<p class="mt-3 mb-3" style="font-size:1.1rem;">'.__('Please set your e-mail account').'</p>'
					.'<a class="btn btn-primary" '.$href.'><i class="bi bi-gear me-1"></i>'.__('E-mail Accounts').'</a>'
					.'</div>');
			} else
				print('<h1><a '.$href.'>Please set your e-mail account</a></h1>');
            return;
        }
        $params = array('_autologin_id'=>$def['id'])+$params2;
        if (function_exists('apache_get_modules') && in_array('mod_rewrite',apache_get_modules())) {
            $multiwin = CRM_RoundcubeCommon::multiwin_supported();
            $RC = $multiwin ? 'RCWIN_' . CID : 'RC';
            // §57: soften the old red alarm — the single-window limit only bites if you open a
            // SECOND mail window, so show a calm note once per session instead of scaring users
            // on every mail open.
            if (!$multiwin && !$this->get_module_variable('multiwin_notice_shown')) {
                $this->set_module_variable('multiwin_notice_shown', 1);
                echo '<div style="color:#888; font-size:0.85em; padding-bottom:0.5em;">'
                    . __('Note: this hosting supports only one open mail window — please avoid opening the mailbox in a second browser window at the same time.')
                    . '</div>';
            }
        } else {
            $RC = 'RC';
        }
        $rc_src = 'modules/Libs/RoundCube/' . $RC . '/public_html/index.php?'.http_build_query($params);
        if (Base_ThemeCommon::is_adminlte_family()) {
            // Replaces the old animated images/loader-0.gif background - the
            // spinner sits over the iframe until Roundcube's own page inside
            // it finishes loading, then the iframe's onload hides it.
            print('<div class="position-relative">'
                .'<div class="position-absolute top-50 start-50 translate-middle">'
                .'<div class="spinner-border text-primary" role="status"><span class="visually-hidden">'.__('Loading...').'</span></div>'
                .'</div>'
                .'<iframe style="border:0" border="0" src="'.$rc_src.'" width="100%" height="300px" id="rc_frame" onload="this.previousElementSibling.style.display=\'none\';"></iframe>'
                .'</div>');
        } else {
            print('<div style="background:transparent url(images/loader-0.gif) no-repeat 50% 50%;"><iframe style="border:0" border="0" src="'.$rc_src.'" width="100%" height="300px" id="rc_frame"></iframe></div>');
        }
        eval_js('var dim=document.viewport.getDimensions();var rc=$("rc_frame");rc.style.height=(Math.max(dim.height,document.documentElement.clientHeight)-130)+"px";');
        $epesi_mail_url = get_epesi_url() . '?rc_mailto=%s';
        $epesi_mail_name = EPESI . ' - ' . get_epesi_url();
        eval_js_once("if (typeof navigator != 'undefined') { navigator.registerProtocolHandler('mailto', '$epesi_mail_url', '$epesi_mail_name'); }");
    }

    public function push_settings($s) {
        Base_BoxCommon::push_module(Base_User_Settings::module_name(),null,array($s));
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
