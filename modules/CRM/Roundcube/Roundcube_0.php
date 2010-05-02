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

    public function body() {
        $accounts = Utils_RecordBrowserCommon::get_records('rc_accounts',array('epesi_user'=>Acl::get_user()));
        $def = null;
        $def_id = $this->get_module_variable('default',null);
        foreach($accounts as $a) {
            if($def===null) $def = $a;
            if($def_id===null && $a['default_account']) $def = $a;
            elseif($a['id']==$def_id) $def = $a;
            Base_ActionBarCommon::add('add',$a['login'], $this->create_callback_href(array($this,'account'),$a['id']),$a['server']);
        }
        if($def===null) {
            print($this->t('No accounts'));
            return;
        }
        $params = array('_autologin_id'=>$def['id'],'_autologin_hash'=>md5($def['server'].$def['password'].$def['smtp_server']));
        print('<iframe style="border:0" border="0" src="modules/CRM/Roundcube/src/index.php?'.http_build_query($params).'" width="600px" height="300px" id="rc_frame"></iframe>');
        eval_js('var dim=document.viewport.getDimensions();var rc=$("rc_frame");rc.style.height=(dim.height-120)+"px";rc.style.width=(dim.width-50)+"px";');
    }

    public function account($id) {
        $this->set_module_variable('default',$id);
    }

    ////////////////////////////////////////////////////////////
    //account management
    public function account_manager() {
        $this->rb = $this->init_module('Utils/RecordBrowser','rc_accounts','rc_accounts');
        $this->rb->set_defaults(array('epesi_user'=>Acl::get_user()));
        $order = array(array('login'=>'DESC'), array('epesi_user'=>Acl::get_user()),array('epesi_user'=>false));
        $this->display_module($this->rb,$order);
    }

    public function caption() {
        return 'Roundcube Mail Client';
    }

}

?>
