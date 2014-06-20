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

    public function new_mail($to='',$subject='',$body='',$message_id='',$references='') {
//        $this->body(array('task' => 'mail', '_action' => 'compose', '_to' => $to));
          $this->body(array('mailto' => 1));
          $_SESSION['rc_body'] = $body;
          $_SESSION['rc_to'] = $to;
          $_SESSION['rc_subject'] = $subject;
          $_SESSION['rc_reply'] = $message_id;
          $_SESSION['rc_references'] = $references;
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

    public function reload_mails($id) {
        $contact = CRM_ContactsCommon::get_contact($id);
        $emails = array($contact['email']);

        $multiple = Utils_RecordBrowserCommon::get_records('rc_multiple_emails',array('record_type'=>'contact','record_id'=>$id));
        foreach($multiple as $multi) $emails[] = $multi['email'];
        $emails = array_unique($emails);

        foreach($emails as $email) {
            $cc = Utils_RecordBrowserCommon::get_records('rc_mails',array('(~from'=>'%'.$email.'%','|~to'=>'%'.$email.'%'));

            foreach($cc as $mail) {
                if($mail['employee']==$id || in_array('P:'.$id,$mail['contacts'])) continue;
                $mail['contacts'][] = 'P:'.$id;
                Utils_RecordBrowserCommon::update_record('rc_mails',$mail['id'],array('contacts'=>$mail['contacts']));
                CRM_RoundcubeCommon::create_thread($mail['id']);
            }
        }
    }

    public function addon($arg, $rb) {
        $rs = $rb->tab;
        $id = $arg['id'];
        if($rs=='contact' && Base_AclCommon::i_am_admin()) {
            Base_ActionBarCommon::add('reload',__('Reload mails'),$this->create_callback_href(array($this,'reload_mails'),$arg['id']));
        }
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
   			$this->lp->add_option('cancel', __('Cancel'), Base_ThemeCommon::get_template_file('Base_ActionBar', 'icons/back.png'), null);
        	$this->lp->add_option('paste', __('Paste'), Base_ThemeCommon::get_template_file($this->get_type(), 'copy.png'), null);
        	$content = '';
        	foreach($_SESSION['rc_mails_cp'] as $mid) {
            	$mail = Utils_RecordBrowserCommon::get_record('rc_mails',$mid);
            	$content .= '<div style="text-align:left"><b>'.__('From').':</b> <i>'.$mail['from'].'</i><br /><b>'.__('To').':</b> <i>'.$mail['to'].'</i><br /><b>'.__('Subject').':</b> <i>'.$mail['subject'].'</i><br />'.substr(strip_tags($mail['body'],'<br><hr>'),0,200).(strlen($mail['body'])>200?'...':'').'</div>';
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

        $tb = $this->init_module('Utils/TabbedBrowser');
        $tb->set_tab(__('Threaded'),array($this,'addon_threaded'),array($rs,$id));
        $tb->set_tab(__('Flat'),array($this,'addon_flat'),array($rs,$id));
        $this->display_module($tb);
    }

    public function addon_threaded($rs,$id) {
        $rb = $this->init_module('Utils/RecordBrowser','rc_mail_threads','rc_mails_threaded');
        $rb->set_header_properties(array(
                        'date'=>array('width'=>10),
                        'contacts'=>array('name'=>__('Involved contacts'), 'width'=>20),
                        'subject'=>array('name'=>__('Message'),'width'=>40),
                        'attachments'=>array('width'=>5),
                        'count'=>array('width'=>5)
        ));

        //set order by threads:
        //1 - if there is reference sort by parent message date, else sort by this message date ("group" messages by "parent" date)
        //2 - if there is reference sort by parent message id, else sort by "my" message_id
/*          $rb->force_order(array(':CASE WHEN f_references is null OR (SELECT rx.f_date FROM rc_mails_data_1 rx WHERE rx.active=1 AND r.f_references LIKE '.DB::Concat('\'%\'','rx.f_message_id','\'%\'').' LIMIT 1) is null THEN (SELECT rx.f_date FROM rc_mails_data_1 rx WHERE rx.active=1 AND rx.f_references LIKE '.DB::Concat('\'%\'','r.f_message_id','\'%\'').' ORDER BY rx.f_date DESC LIMIT 1) ELSE (SELECT rx2.f_date FROM rc_mails_data_1 rx2 WHERE rx2.active=1 AND rx2.f_references LIKE '.DB::Concat('\'%\'','(SELECT rx.f_message_id FROM rc_mails_data_1 rx WHERE rx.active=1 AND r.f_references LIKE '.DB::Concat('\'%\'','rx.f_message_id','\'%\'').' ORDER BY rx.f_date ASC LIMIT 1)','\'%\'').' ORDER BY rx2.f_date DESC LIMIT 1) END'=>'DESC',
                    ':CASE WHEN f_references is null THEN f_message_id ELSE (SELECT rx.f_message_id FROM rc_mails_data_1 rx WHERE rx.f_references is null AND r.f_references LIKE '.DB::Concat('\'%\'','rx.f_message_id','\'%\'').' ORDER BY rx.f_date ASC LIMIT 1) END'=>'DESC',
                    ':CASE WHEN f_references is null OR (SELECT rx.f_date FROM rc_mails_data_1 rx WHERE rx.active=1 AND r.f_references LIKE '.DB::Concat('\'%\'','rx.f_message_id','\'%\'').' LIMIT 1) is null THEN 0 ELSE 1 END'=>'ASC',
                    'date'=>'DESC'
        ));*/

        $assoc_threads_ids = DB::GetCol('SELECT m.f_thread FROM rc_mails_data_1 m INNER JOIN rc_mails_assoc_data_1 a ON a.f_mail=m.id WHERE m.active=1 AND a.active=1 AND a.f_recordset=%s AND a.f_record_id=%d',array($rs,$id));
        if($rs=='contact') {
        	//$ids = DB::GetCol('SELECT id FROM rc_mails_data_1 WHERE f_employee=%d OR (f_recordset=%s AND f_object=%d)',array($id,$rs,$id));
        	$this->display_module($rb, array(array('(contacts'=>array('P:'.$id),'|id'=>$assoc_threads_ids), array(), array('last_date'=>'DESC')), 'show_data');
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
        	$this->display_module($rb, array(array('(contacts'=>$customers,'|id'=>$assoc_threads_ids), array(), array('last_date'=>'DESC')), 'show_data');
        } else
        $this->display_module($rb, array(array('id'=>$assoc_threads_ids), array(), array('last_date'=>'DESC')), 'show_data');

        //Epesi::load_js('modules/CRM/Roundcube/utils.js');
        //eval_js('CRM_RC.create_msg_tree("'.escapeJS($rb->get_path().'|0content',true).'")');
    }

    public function addon_flat($rs,$id) {
        $rb = $this->init_module('Utils/RecordBrowser','rc_mails','rc_mails_flat');
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

    public function thread_addon($arg,$rb) {
        $rb = $this->init_module('Utils/RecordBrowser','rc_mails','rc_mails_flat_thread');
        $rb->set_header_properties(array(
            'date'=>array('width'=>10),
            'employee'=>array('name'=>__('Archived by'),'width'=>20),
            'contacts'=>array('name'=>__('Involved contacts'), 'width'=>20),
            'subject'=>array('name'=>__('Message'),'width'=>40),
            'attachments'=>array('width'=>5)
        ));
        $rb->set_additional_actions_method(array($this, 'actions_for_mails'));

        $this->display_module($rb, array(array('thread'=>$arg['id']), array(), array('date'=>'DESC')), 'show_data');
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
        $gb_row->add_action('style="display:none;" href="javascript:void(0)" class="expand"','Expand', null, Base_ThemeCommon::get_template_file('Utils/GenericBrowser', 'expand.gif'), 5);
        $gb_row->add_action('style="display:none;" href="javascript:void(0)" class="collapse"','Collapse', null, Base_ThemeCommon::get_template_file('Utils/GenericBrowser', 'collapse.gif'), 5);
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
            $mail = $row['account_name'];

            $cell_id = 'mailaccount_'.$opts['id'].'_'.$row['id'];

            //interval execution
            eval_js_once('setInterval(\'CRM_RC.update_msg_num('.$opts['id'].' ,'.$row['id'].' , 0)\',300000)');

            //and now
            $update_applet .= 'CRM_RC.update_msg_num('.$opts['id'].' ,'.$row['id'].' ,1);';
            print('<li><i><a'.$this->create_callback_href(array($this,'open_rc_account'),$row['id']).'>'.$mail.'</a></i> - <span id="'.$cell_id.'"></span></li>');
        }
        print('</ul>');
        $this->js($update_applet);

        $href = $this->create_callback_href(array('Base_BoxCommon', 'push_module'), array($this->get_type(), 'account_manager', array(true)));
        $img = '<img src="' . Base_ThemeCommon::get_template_file('Base_Dashboard', 'configure.png') . '" border="0">';
        $tooltip = Utils_TooltipCommon::open_tag_attrs(__('Go to account settings'));
        $opts['actions'][] = "<a $tooltip $href>$img</a>";
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
    public function account_manager($pushed_on_top = false) {
        if ($pushed_on_top) {
            if ($this->is_back()) {
                Base_BoxCommon::pop_main();
                return;
            }
            Base_ActionBarCommon::add('back',__('Back'),$this->create_back_href());
        } else {
            Base_ActionBarCommon::add('back',__('Back'),$this->create_main_href('Base_User_Settings'));
        }

        $this->rb = $this->init_module('Utils/RecordBrowser','rc_accounts','rc_accounts');
        $this->rb->set_defaults(array('epesi_user'=>Acl::get_user()));
        $order = array(array('login'=>'DESC'), array('epesi_user'=>Acl::get_user()),array('epesi_user'=>false));
        $this->display_module($this->rb,$order);

        // other settings
        $qf = $this->init_module('Libs/QuickForm');
        $qf->addElement('advcheckbox', 'standard_mailto', __("Use standard mailto links"), null, array('onchange' => $qf->get_submit_form_js()));
        $use_standard_mailto = CRM_RoundcubeCommon::use_standard_mailto();
        $qf->setDefaults(array('standard_mailto' => $use_standard_mailto));
        if ($qf->validate()) {
            CRM_RoundcubeCommon::set_standard_mailto($qf->exportValue('standard_mailto'));
        }
        $qf->display_as_row();
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
