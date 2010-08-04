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
        $params = array('_autologin_id'=>$def['id']);
        print('<iframe style="border:0" border="0" src="modules/CRM/Roundcube/src/index.php?'.http_build_query($params).'" width="100%" height="300px" id="rc_frame"></iframe>');
        eval_js('var dim=document.viewport.getDimensions();var rc=$("rc_frame");rc.style.height=(dim.height-100)+"px";');
    }

    public function account($id) {
        $this->set_module_variable('default',$id);
    }

    public function assoc_addon($arg,$rb) {
        $rb = $this->init_module('Utils/RecordBrowser','rc_mails_assoc','rc_mails_assoc');
        $this->display_module($rb, array(array('mail'=>$arg['id'])), 'show_data');
    }

    public function addon($arg, $rb) {
        $rs = $rb->tab;
        $id = $arg['id'];
        $rb = $this->init_module('Utils/RecordBrowser','rc_mails','rc_mails');
        $rb->set_header_properties(array(
                'direction'=>array('width'=>5),
                'date'=>array('width'=>10),
                'employee'=>array('width'=>20),
                'contacts'=>array('width'=>20),
                'subject'=>array('width'=>40),
                'attachments'=>array('width'=>5)
            ));
        $rb->set_button(false);
        $rb->set_additional_actions_method(array($this, 'actions_for_mails'));
        $assoc_mail_ids = array();
        foreach(Utils_RecordBrowserCommon::get_records('rc_mails_assoc',array('recordset'=>$rs,'record_id'=>$id),array('mail')) as $m)
            $assoc_mail_ids[] = $m['mail'];
        if($rs=='contact') {
            //$ids = DB::GetCol('SELECT id FROM rc_mails_data_1 WHERE f_employee=%d OR (f_recordset=%s AND f_object=%d)',array($id,$rs,$id));
            $this->display_module($rb, array(array('(employee'=>$id,'|contacts'=>array('P:'.$id),'|id'=>$assoc_mail_ids), array(), array('date'=>'DESC')), 'show_data');
        } elseif($rs=='company') {
            $this->display_module($rb, array(array('(contacts'=>array('C:'.$id),'|id'=>$assoc_mail_ids), array(), array('date'=>'DESC')), 'show_data');
        } else
            $this->display_module($rb, array(array('id'=>$assoc_mail_ids), array(), array('date'=>'DESC')), 'show_data');
        if(isset($_SESSION['rc_mails_cp']) && is_array($_SESSION['rc_mails_cp']) && !empty($_SESSION['rc_mails_cp']))
            Base_ActionBarCommon::add(Base_ThemeCommon::get_template_file($this->get_type(),'copy.png'),'Paste mail', $this->create_callback_href(array($this,'paste'),array($rs,$id)));
    }

    public function paste($rs,$id) {
        if(isset($_SESSION['rc_mails_cp']) && is_array($_SESSION['rc_mails_cp']) && !empty($_SESSION['rc_mails_cp'])) {
            foreach($_SESSION['rc_mails_cp'] as $mid) {
                $c = Utils_RecordBrowserCommon::get_records_count('rc_mails_assoc',array('mail'=>$mid,'recordset'=>$rs,'record_id'=>$id));
                if(!$c)
                    Utils_RecordBrowserCommon::new_record('rc_mails_assoc',array('mail'=>$mid,'recordset'=>$rs,'record_id'=>$id));
            }
            Epesi::alert($this->t('Mail(s) is associated to this record now'));
        }
    }

    public function actions_for_mails($r, $gb_row) {
        $gb_row->add_action($this->create_callback_href(array($this,'copy'),array($r['id'])),'copy',null,Base_ThemeCommon::get_template_file($this->get_type(),'copy_small.png'));
    }

    public function copy($id) {
        $_SESSION['rc_mails_cp'] = array($id);
    }

    public function applet($conf, $opts) {
        Epesi::load_js('modules/CRM/Roundcube/utils.js');
        $opts['go'] = true;
        $accounts = array();
        $ret = array();
        $update_applet = '';
        foreach($conf as $key=>$on) {
            $x = explode('_',$key);
            if($x[0]=='account' && $on) {
                $id = $x[1];
                $accounts[] = $id;
            }
        }
        $accs = Utils_RecordBrowserCommon::get_records('rc_accounts',array('epesi_user'=>Acl::get_user(),'id'=>$accounts));
        print('<ul>');
        foreach($accs as $row) {
            $mail = $row['login'];

            $cell_id = 'mailaccount_'.$opts['id'].'_'.$row['id'];

            //interval execution
            eval_js_once('setInterval(\'CRM_RC.update_msg_num('.$opts['id'].' ,'.$row['id'].' , 0)\',300000)');

            //and now
            $update_applet .= 'CRM_RC.update_msg_num('.$opts['id'].' ,'.$row['id'].' ,1);';
            print('<li><i>'.$mail.'</i> - <span id="'.$cell_id.'"></span></li>');
        }
        print('</ul>');
        $this->js($update_applet);
    }

	public function mail_addresses_addon($arg,$rb) {
		$type = $rb->tab;
		$loc = Base_RegionalSettingsCommon::get_default_location();
		$rb = $this->init_module('Utils/RecordBrowser','rc_multiple_emails');
		$order = array(array('record_type'=>$type,'record_id'=>$arg['id']), array('record_type'=>false,'record_id'=>false), array());
		$rb->set_defaults(array('record_type'=>$type,'record_id'=>$arg['id']));
        $rb->enable_quick_new_records();
		$this->display_module($rb,$order,'show_data');
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
