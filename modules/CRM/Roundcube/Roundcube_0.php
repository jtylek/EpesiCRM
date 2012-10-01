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
            Base_ActionBarCommon::add('add',($a==$def?'<b><u>'.$a['account_name'].'</u></b>':$a['account_name']), $this->create_callback_href(array($this,'account'),$a['id']),$a['server'],$a==$user_def?-1:0);
        }
        if($def===null) {
			print('<h1><a '.$this->create_callback_href(array($this,'push_settings'),array(__('E-mail Accounts'))).'>Please set your e-mail account</a></h1>');
            return;
        }
        $params = array('_autologin_id'=>$def['id'])+$params2;
//        if($params2) $params['_url'] = http_build_query($params2);
        print('<iframe style="border:0" border="0" src="modules/CRM/Roundcube/RC/index.php?'.http_build_query($params).'" width="100%" height="300px" id="rc_frame"></iframe>');
        eval_js('var dim=document.viewport.getDimensions();var rc=$("rc_frame");rc.style.height=(Math.max(dim.height,document.documentElement.clientHeight)-130)+"px";');
    }

	public function push_settings($s) {
		$x = ModuleManager::get_instance('/Base_Box|0');
		if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$x->push_main('Base_User_Settings',null,array($s));
	}
    
    public function admin() {
		if($this->is_back()) {
			$this->parent->reset();
			return;
		}

		Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
	
		$f = $this->init_module('Libs/QuickForm');
		
		$f->addElement('header',null,__('Outgoing mail global signature'));
		
		$fck = & $f->addElement('ckeditor', 'content', __('Content'));
		$fck->setFCKProps('800','300',true);
		
		$f->setDefaults(array('content'=>Variable::get('crm_roundcube_global_signature',false)));

		Base_ActionBarCommon::add('save',__('Save'),$f->get_submit_form_href());
		
		if($f->validate()) {
			$ret = $f->exportValues();
			$content = $ret['content'];
			Variable::set('crm_roundcube_global_signature',$content);
			Base_StatusBarCommon::message(__('Signature saved'));
			$this->parent->reset();
			return;
		}
		$f->display();	
        
    }

    public function new_mail($to='',$subject='',$body='') {
//        $this->body(array('task' => 'mail', '_action' => 'compose', '_to' => $to));
          $this->body(array('mailto' => $to,'subject'=>$subject));
          $_SESSION['rc_body'] = $body;
    }

    public function account($id) {
        $this->set_module_variable('default',$id);
    }

    public function assoc_addon($arg,$rb) {
        $rb = $this->init_module('Utils/RecordBrowser','rc_mails_assoc','rc_mails_assoc');
        $this->display_module($rb, array(array('mail'=>$arg['id'])), 'show_data');
    }

    public function attachments_addon($arg,$rb) {
 		$m = $this->init_module('Utils/GenericBrowser',null,'attachments');
        $attachments = DB::GetAssoc('SELECT mime_id,name FROM rc_mails_attachments WHERE mail_id=%d AND attachment=1',array($arg['id']));
        $data = array();
        foreach($attachments as $k=>&$n) {
            $filename = DATA_DIR.'/CRM_Roundcube/attachments/'.$arg['id'].'/'.$k;
     		$data[] = array('<a href="modules/CRM/Roundcube/get.php?'.http_build_query(array('mime_id'=>$k,'mail_id'=>$arg['id'])).'" target="_blank">'.$n.'</a>',file_exists($filename)?filesize($filename):'---');
        }
 		$this->display_module($m,array(array(array('name'=>'Filename','search'=>1),
 		                    array('name'=>'Size')),$data,false,null,array('Filename'=>'ASC')),'simple_table');
    }

    public function addon($arg, $rb) {
        $rs = $rb->tab;
        $id = $arg['id'];
        if(isset($_SESSION['rc_mails_cp']) && is_array($_SESSION['rc_mails_cp']) && !empty($_SESSION['rc_mails_cp'])) {
    	    $ok = true;
            foreach($_SESSION['rc_mails_cp'] as $mid) {
				if(!DB::GetOne('SELECT active FROM rc_mails_data_1 WHERE id=%d',array($mid))) {
					$ok = false;
					break;
				}
                $c = Utils_RecordBrowserCommon::get_records_count('rc_mails_assoc',array('mail'=>$mid,'recordset'=>$rs,'record_id'=>$id));
                if($rs == 'contact' || $rs=='company')
            	    $c += Utils_RecordBrowserCommon::get_records_count('rc_mails',array('id'=>$mid, '(employee'=>$id,'|contacts'=>$id));
                if($c) {
            	    $ok = false;
            	    break;
            	}
            }
            if($ok) {
        	$this->lp = $this->init_module('Utils_LeightboxPrompt');
   			$this->lp->add_option('cancel', __('Cancel'), null, null);
        	$this->lp->add_option('paste', __('Paste'), Base_ThemeCommon::get_template_file($this->get_type(), 'copy.png'), null);
        	$content = '';
        	foreach($_SESSION['rc_mails_cp'] as $mid) {
            	$mail = Utils_RecordBrowserCommon::get_record('rc_mails',$mid);
            	$content .= '<div style="text-align:left"><b>'.__('From:').'</b> <i>'.$mail['from'].'</i><br /><b>'.__('To:').'</b> <i>'.$mail['to'].'</i><br /><b>'.__('Subject:').'</b> <i>'.$mail['subject'].'</i><br />'.substr(strip_tags($mail['body'],'<br><hr>'),0,200).(strlen($mail['body'])>200?'...':'').'</div>';
        	}
        	$this->display_module($this->lp, array(__('Paste e-mail'), array(), $content, false));
       		$vals = $this->lp->export_values();
       		if ($vals) {
       			if($vals['option']=='paste')
       				$this->paste($rs,$id);
       		}
        	Base_ActionBarCommon::add(Base_ThemeCommon::get_template_file($this->get_type(),'copy.png'),__('Paste mail'), $this->lp->get_href());//$this->create_confirm_callback_href(__('Paste following email?'),array($this,'paste'),array($rs,$id)));
    	    }
        }
        
        $rb = $this->init_module('Utils/RecordBrowser','rc_mails','rc_mails');
        $rb->set_header_properties(array(
                        'date'=>array('width'=>10),
                        'employee'=>array('name'=>__('Archived by'),'width'=>20),
                        'contacts'=>array('name'=>__('Involved contacts'), 'width'=>20),
                        'subject'=>array('name'=>__('Message'),'width'=>40),
                        'attachments'=>array('width'=>5)
        ));
        $rb->set_additional_actions_method(array($this, 'actions_for_mails'));
        $assoc_mail_ids = array();
        $assoc_tmp = Utils_RecordBrowserCommon::get_records('rc_mails_assoc',array('recordset'=>$rs,'record_id'=>$id),array('mail'));
        foreach($assoc_tmp as $m)
        $assoc_mail_ids[] = $m['mail'];
        if($rs=='contact') {
        	//$ids = DB::GetCol('SELECT id FROM rc_mails_data_1 WHERE f_employee=%d OR (f_recordset=%s AND f_object=%d)',array($id,$rs,$id));
        	$this->display_module($rb, array(array('(employee'=>$id,'|contacts'=>array('P:'.$id),'|id'=>$assoc_mail_ids), array(), array('date'=>'DESC')), 'show_data');
        } elseif($rs=='company') {
            $form = $this->init_module('Libs/QuickForm');
            $form->addElement('checkbox', 'include_related', __('Include related e-mails'), null, array('onchange'=>$form->get_submit_form_js()));
            if ($form->validate()) {
                $show_related = $form->exportValue('include_related');
                $this->set_module_variable('include_related',$show_related);
            }
            $show_related = $this->get_module_variable('include_related');
            $form->setDefaults(array('include_related'=>$show_related));
            
            ob_start();
            $form->display_as_row();
            $html = ob_get_clean();
            
            $rb->set_button(false, $html);
            $customers = array('C:'.$id);
            if ($show_related) {
                $conts = CRM_ContactsCommon::get_contacts(array('company_name'=>$id));
                foreach ($conts as $c)
                    $customers[] = 'P:'.$c['id'];
            }
        	$this->display_module($rb, array(array('(contacts'=>$customers,'|id'=>$assoc_mail_ids), array(), array('date'=>'DESC')), 'show_data');
        } else
        $this->display_module($rb, array(array('id'=>$assoc_mail_ids), array(), array('date'=>'DESC')), 'show_data');
        
    }

    public function paste($rs,$id) {
        if(isset($_SESSION['rc_mails_cp']) && is_array($_SESSION['rc_mails_cp']) && !empty($_SESSION['rc_mails_cp'])) {
            foreach($_SESSION['rc_mails_cp'] as $mid) {
                $c = Utils_RecordBrowserCommon::get_records_count('rc_mails_assoc',array('mail'=>$mid,'recordset'=>$rs,'record_id'=>$id));
                if(!$c)
                    Utils_RecordBrowserCommon::new_record('rc_mails_assoc',array('mail'=>$mid,'recordset'=>$rs,'record_id'=>$id));
            }
			location(array());
        }
    }

    public function actions_for_mails($r, $gb_row) {
        $gb_row->add_action($this->create_callback_href(array($this,'copy'),array($r['id'])),'copy',null,Base_ThemeCommon::get_template_file($this->get_type(),'copy_small.png'));
    }

    public function copy($id) {
        $_SESSION['rc_mails_cp'] = array($id);
    }
    
    public function open_rc_account($id) {
        $x = ModuleManager::get_instance('/Base_Box|0');
        $x->push_main('CRM_Roundcube','body',array(array(),$id));
    }

    public function applet($conf, & $opts) {
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
            print('<li><i><a'.$this->create_callback_href(array($this,'open_rc_account'),$row['id']).'>'.$mail.'</a></i> - <span id="'.$cell_id.'"></span></li>');
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
		Base_ActionBarCommon::add('back',__('Back'),$this->create_main_href('Base_User_Settings'));
		
        $this->rb = $this->init_module('Utils/RecordBrowser','rc_accounts','rc_accounts');
        $this->rb->set_defaults(array('epesi_user'=>Acl::get_user()));
        $order = array(array('login'=>'DESC'), array('epesi_user'=>Acl::get_user()),array('epesi_user'=>false));
        $this->display_module($this->rb,$order);
    }

    public function caption() {
        return __('Roundcube Mail Client');
    }

	public function mail_body_addon($rec) {
		$theme = $this->init_module('Base_Theme');
		$rec['body'] = '<iframe id="rc_mail_body" src="modules/CRM/Roundcube/get_html.php?'.http_build_query(array('id'=>$rec['id'])).'" style="width:100%;border:0" border="0"></iframe>';
		$theme->assign('email', $rec);
		$theme->display('mail_body');
	}
	
	public function mail_headers_addon($rec) {
		$theme = $this->init_module('Base_Theme');
		$rec['headers_data'] = '<iframe id="rc_mail_body" src="modules/CRM/Roundcube/get_html.php?'.http_build_query(array('id'=>$rec['id'], 'field'=>'headers')).'" style="width:100%;border:0" border="0"></iframe>';
		$theme->assign('email', $rec);
		$theme->display('mail_headers');
	}
}

?>
