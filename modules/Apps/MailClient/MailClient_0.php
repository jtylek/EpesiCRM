<?php
/**
 * Simple mail client

 Watpliwosci:
 Czy przeniesc CRM/MailClient tutaj? Na razie nie...
 Umozliwisc automatyczna synchronizacje pop3?
 
 *
 * TODO:
 * -filtry: actions, attachmenty w forward
 * -zalaczniki przy new
 * -obsluga imap:
 *   -subskrypcja do folderow
 *
 * @author pbukowski@telaxus.com
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-apps
 * @subpackage mailclient
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_MailClient extends Module {

	public function body() {
		if(!Acl::is_user()) return;
		$boxes = Apps_MailClientCommon::get_mailbox_data();
		if(empty($boxes)) {
			Apps_MailClientCommon::create_internal_mailbox();
			$boxes = Apps_MailClientCommon::get_mailbox_data(null,false);
		}

		if(!$this->isset_module_variable('opened_box')) {
			$v = current($boxes);
			$this->set_module_variable('opened_box',$v['id']);
			$this->set_module_variable('opened_dir','Inbox/');
		}

		$box = $this->get_module_variable('opened_box');
		$dir = $this->get_module_variable('opened_dir');
		$preview_id = 'mail_view';

		$box_idx = Apps_MailClientCommon::get_index($box,$dir);
		if($box_idx===false) {
			$dir = 'Inbox/';
			$this->set_module_variable('opened_dir',$dir);
			$box_idx = Apps_MailClientCommon::get_index($box,$dir);
			if($box_idx===false) {
				print('Invalid mailbox');
				return;
			}
		}

		$str = array();
		$tree = array();
		$move_folders = array();
		$is_pop3 = false;
		foreach($boxes as $v) {
			$str[$v['mail']] = Apps_MailClientCommon::get_mailbox_structure($v['id']);
			if($v['incoming_protocol']==0) $is_pop3=true;
			$name = $v['mail']=='#internal'?$this->t('Private messages'):$v['mail'];
			$online = Apps_MailClientCommon::is_online($v['id']);
			$open_cb = '<a '.$this->create_callback_href(array($this,'open_mail_dir_callback'),array($v['id'],'Inbox/')).'>'.$name.($online?'':' '.$this->ht('(Offline)')).'</a>';
			$tree[] = array('name'=>$open_cb, 'sub'=>$this->get_tree_structure($str[$v['mail']],$v['id']));
			if($online)
				$move_folders = array_merge($move_folders,$this->get_move_folders($str[$v['mail']],$name,$v['id']));
		}
//		print_r($tree);
//		return;


		$mail_actions_arr = array();

		$move_msg_f = $this->init_module('Libs/QuickForm',null,'move_msg');
		$move_msg_f->addElement('hidden','msg_id','-1',array('id'=>'mail_client_actions_move_msg_id'));
		$move_msg_f->addElement('select','folder',$this->t('Move message to folder'),$move_folders);
		$move_msg_f->addElement('button','submit_button',$this->ht('Move'),array('onClick'=>$move_msg_f->get_submit_form_js().'leightbox_deactivate(\'mail_actions\');'));
		$mail_actions_arr[] = $move_msg_f->toHtml();

		$mail_actions_arr[] = '<a onClick="leightbox_deactivate(\'mail_actions\')" href="" tpl_href="modules/Apps/MailClient/source.php?'.http_build_query(array('box'=>$box,'dir'=>$dir,'msg_id'=>'__MSG_ID__')).'" target="_blank" id="mail_client_actions_view_source">'.$this->t('View source').'</a>';

		$external_mail_actions = ModuleManager::call_common_methods('mail_actions');
		$i=0;
		foreach($external_mail_actions as $x) {
			if(!is_array($x)) continue;
			foreach($x as $caption=>$f) {
				if(!is_array($f) || !isset($f['func']))  {
					if(!is_string($f) || !is_array($f)) continue;
					$f = array('func'=>$f);
				}
				if($this->get_unique_href_variable('external_action')===$caption) {
					$id = $this->get_unique_href_variable('msg_id');
					if(is_numeric($id)) {
						$ret = call_user_func($f['func'],$box,$dir,$id,isset($f['args'])?$f['args']:null);
						if(isset($f['delete']) && $f['delete'] && $ret)
							Apps_MailClientCommon::remove_msg($box,$dir,$id);
					}
				}
				$mail_actions_arr[] = '<a href="javascript:void(0)" tpl_onClick="'.Module::create_unique_href_js(array('external_action'=>$caption,'msg_id'=>'__MSG_ID__')).'" id="mail_client_external_actions_'.$i.'">'.$this->t($caption).'</a>';
				$i++;
			}
		}

		Libs_LeightboxCommon::display('mail_actions','<ul><li>'.implode($mail_actions_arr,'<li>').'</ul>',$this->t('Mail actions'));

		if($move_msg_f->validate()) {
			$vals = $move_msg_f->exportValues();
			$out_box = explode('/',$vals['folder'],2);
			Apps_MailClientCommon::move_msg($box,$dir,$out_box[0],$out_box[1],$vals['msg_id']);
			location(array());
		}

		$th = $this->init_module('Base/Theme');
		$tree_mod = $this->init_module('Utils/Tree');
		$tree_mod->set_structure($tree);
		$tree_mod->sort();
		$th->assign('tree', $this->get_html_of_module($tree_mod));

		$drafts_folder = false;
		$sent_folder = false;
		$trash_folder = false;
		if(preg_match('/^Drafts/',$dir))
				$drafts_folder = true;
		elseif(preg_match('/^Sent/',$dir))
				$sent_folder = true;
		elseif(preg_match('/^Trash/',$dir))
				$trash_folder = true;

		$gb = $this->init_module('Utils/GenericBrowser',null,'list');
		$cols = array();
		$cols[] = array('name'=>$this->t('ID'), 'order'=>'id','width'=>'3', 'display'=>DEBUG);
		$cols[] = array('name'=>$this->t('Subject'), 'search'=>1, 'order'=>'subj','width'=>'50');
		$to_column_enabled = ($drafts_folder || $sent_folder || $trash_folder);
		$cols[] = array('name'=>$this->t('To'), 'search'=>1,'quickjump'=>1, 'order'=>'to','width'=>'10', 'display'=>$to_column_enabled);
		$from_column_enabled = ($trash_folder || !($drafts_folder || $sent_folder));
		$cols[] = array('name'=>$this->t('From'), 'search'=>1,'quickjump'=>1, 'order'=>'from','width'=>'10','display'=>$from_column_enabled);
		$cols[] = array('name'=>$this->t('Date'), 'search'=>1, 'order'=>'date','width'=>'15');
		$cols[] = array('name'=>$this->t('Size'), 'search'=>1, 'order'=>'size','width'=>'10');
		$gb->set_table_columns($cols);


		$gb->set_default_order(array($this->t('Date')=>'DESC'));
		$gb->force_per_page(10);

		$limit_max = count($box_idx);
		$imap_online = Apps_MailClientCommon::is_online($box);

		foreach($box_idx as $id=>$data) {
			$r = $gb->get_new_row();
			$subject = Apps_MailClientCommon::mime_header_decode($data['subject']);
			if($to_column_enabled) {
				$to_address = Apps_MailClientCommon::mime_header_decode($data['to']);
				if(preg_match('/<(.+)>$/',$to_address,$reqs) && preg_match('/([0-9]+)@epesi_(contact|user)$/',trim($reqs[1]),$reqs2) && $reqs2[2] == 'contact') {
					$to_address = CRM_ContactsCommon::contact_format_default(CRM_ContactsCommon::get_contact($reqs2[1]));
				} else $to_address = htmlspecialchars($to_address);
			} else $to_address = '';
			if($from_column_enabled) {
				$from_address = Apps_MailClientCommon::mime_header_decode($data['from']);
				if(preg_match('/<(.+)>$/',$from_address,$reqs) && preg_match('/([0-9]+)@epesi_(contact|user)$/',trim($reqs[1]),$reqs2) && $reqs2[2] == 'contact') {
					$from_address = CRM_ContactsCommon::contact_format_default(CRM_ContactsCommon::get_contact($reqs2[1]));
				} else $from_address = htmlspecialchars($from_address);
			} else $from_address = '';
			$subject = strip_tags($subject);
			if(strlen($subject)>40) $subject = Utils_TooltipCommon::create(substr($subject,0,38).'...',$subject);
			$r->add_data($id,array('value'=>'<a href="javascript:void(0)" onClick="Apps_MailClient.preview(\''.$preview_id.'\',\''.http_build_query(array('box'=>$box, 'dir'=>$dir, 'msg_id'=>$id, 'pid'=>$preview_id)).'\',\''.$id.'\')" id="apps_mailclient_msg_'.$id.'" '.($data['read']?'':'style="font-weight:bold"').'>'.$subject.'</a>','order_value'=>$subject),$to_address,$from_address,array('value'=>Base_RegionalSettingsCommon::time2reg($data['date']), 'order_value'=>strtotime($data['date'])),array('style'=>'text-align:right','value'=>filesize_hr($data['size']), 'order_value'=>$data['size']));
			$lid = 'mailclient_link_'.$id;
			if($imap_online) {
				$r->add_action($this->create_confirm_callback_href($this->ht('Delete this message?'),array($this,'remove_mail'),array($box,$dir,$id)),'Delete');
				if($drafts_folder) {
					$r->add_action($this->create_callback_href(array($this,'edit_mail'),array($box,$dir,$id,'edit')),'Edit');
				} elseif($sent_folder) {
					$r->add_action($this->create_callback_href(array($this,'edit_mail'),array($box,$dir,$id,'edit_as_new')),'Edit');
				} elseif($trash_folder) {
					$r->add_action($this->create_callback_href(array($this,'restore_mail'),array($box,$dir,$id)),'Restore');
				} else {
					$r->add_action($this->create_callback_href(array($this,'edit_mail'),array($box,$dir,$id,'reply')),'Reply',null,Base_ThemeCommon::get_template_file($this->get_type(),'reply.png'));
				}
				$r->add_action($this->create_callback_href(array($this,'edit_mail'),array($box,$dir,$id,'forward')),'Forward',null,Base_ThemeCommon::get_template_file($this->get_type(),'forward.png'));
				$r->add_action(Libs_LeightboxCommon::get_open_href('mail_actions').' id="actions_button_'.$id.'"','Actions',null,Base_ThemeCommon::get_template_file($this->get_type(),'actions.png'));
				$r->add_js('Event.observe(\'actions_button_'.$id.'\',\'click\',function() {Apps_MailClient.actions_set_id(\''.$id.'\')})');
			}
		}

		//list of messages/preview
		$th->assign('list', $this->get_html_of_module($gb,array(true),'automatic_display'));
		$th->assign('subject_label',$this->t('Subject'));
		$th->assign('preview_subject','<div id="'.$preview_id.'_subject"></div>');
		if($drafts_folder || $sent_folder)
			$th->assign('address_label',$this->t('To'));
		else
			$th->assign('address_label',$this->t('From'));
		$th->assign('preview_address','<div id="'.$preview_id.'_address"></div>');
		$th->assign('preview_attachments','<div id="'.$preview_id.'_attachments"></div>');
		$th->assign('preview_body','<iframe id="'.$preview_id.'_body"></iframe>');
		$th->display();

		//message view
		$th_show = $this->init_module('Base/Theme');
		$th_show->assign('subject_label',$this->t('Subject'));
		if($drafts_folder || $sent_folder)
			$th_show->assign('address_label',$this->t('To'));
		else
			$th_show->assign('address_label',$this->t('From'));

//		if($is_pop3)
		Base_ActionBarCommon::add(Base_ThemeCommon::get_template_file($this->get_type(),'check.png'),$this->t('Check'),$this->check_mail_href());
		Base_ActionBarCommon::add('new-mail',$this->ht('New mail'),$this->create_callback_href(array($this,'edit_mail'),array($box,$dir)));
		Base_ActionBarCommon::add('scan',$this->ht('Mark all as read'),$this->create_confirm_callback_href($this->ht('Are you sure?'),array($this,'mark_all_as_read')));
		if($trash_folder)
			Base_ActionBarCommon::add('delete',$this->ht('Empty trash'),$this->create_confirm_callback_href($this->ht('Are you sure?'),array($this,'empty_trash')));

	}

	private function check_mail_href($on_hide_js='') {
		$checknew_id = $this->get_path().'checknew';

		eval_js('new Apps_MailClient.check_mail(\''.$checknew_id.'\')');
		print('<div id="'.$checknew_id.'" class="leightbox"><div style="width:100%;text-align:center" id="'.$checknew_id.'progresses"></div>'.
			'<a id="'.$checknew_id.'L" style="display:none" href="javascript:void(0)" onClick="Apps_MailClient.hide(\''.$checknew_id.'\');Epesi.request(\'\');'.$on_hide_js.'">'.$this->t('hide').'</a>'.
			'</div>');
		return 'href="javascript:void(0)" rel="'.$checknew_id.'" class="lbOn" id="'.$checknew_id.'b"';
	}

	/////////////////////////////////////////
	// action callbacks
	public function open_mail_dir_callback($box,$dir) {
		$this->set_module_variable('opened_box',$box);
		$this->set_module_variable('opened_dir',$dir);
	}

	public function remove_mail($box,$dir,$id) {
		$box_dir=Apps_MailClientCommon::get_mailbox_dir($box);
		if($box_dir===false && !$quiet) {
			Epesi::alert($this->ht('Invalid mailbox'));
			return;
		}
		if($dir=='Trash/') {
			if(Apps_MailClientCommon::remove_msg($box,$dir,$id)) {
				Base_StatusBarCommon::message('Message deleted');
			} else {
				Epesi::alert($this->ht('Unable to delete message'));
			}
		} else {
			if(Apps_MailClientCommon::move_msg($box,$dir,$box,'Trash/',$id)) {
				Base_StatusBarCommon::message('Message moved to trash');
			} else {
				Epesi::alert($this->ht('Unable to move message to trash'));
			}
		}
	}

	public function mark_all_as_read() {
		$box = $this->get_module_variable('opened_box');
		$dir = $this->get_module_variable('opened_dir');
		if(!Apps_MailClientCommon::mark_all_as_read($box, $dir)) {
			Epesi::alert($this->ht('Invalid mailbox'));
		}
	}

	public function empty_trash() {
		$box = $this->get_module_variable('opened_box');
		$dir = $this->get_module_variable('opened_dir');
		
		if(Apps_MailClientCommon::is_imap($box)) {
			$imap = Apps_MailClientCommon::imap_open($box);
			if(!$imap) {
				return false;
			}
			imap_expunge($imap['connection']);
		}

		$idx = Apps_MailClientCommon::get_index($box,$dir);
		if($idx===false) {
			Epesi::alert($this->ht('Invalid index'));
			return false;
		}

		$mbox_dir = Apps_MailClientCommon::get_mailbox_dir($box);
		if($mbox_dir===false) {
			Epesi::alert($this->ht('Invalid mailbox'));
			return false;
		}
		$box = $mbox_dir.$dir;

		foreach($idx as $i=>$a) {
			@unlink($box.$i);
		}

		file_put_contents($box.'.idx','');
		file_put_contents($box.'.del','');
		file_put_contents($box.'.num','0,0');
	}

	public function restore_mail($box,$dir,$id) {
		$box_dir = Apps_MailClientCommon::get_mailbox_dir($box);
		if($box_dir===false) {
			Epesi::alert($this->ht('Invalid mailbox'));
			return false;
		}
		$trashpath = $box_dir.$dir.'.del';

		$in = @fopen($trashpath,'r');
		if($in===false) {
			Epesi::alert($this->ht('Invalid mail to restore'));
			return false;
		}
		$ret = array();
		$orig_box = false;
		while (($data = fgetcsv($in, 700)) !== false) {
			$num = count($data);
			if($num!=3) continue;
			if($data[0]==$id) {
				$orig_box = $data[1];
				continue;
			}
			$ret[] = $data;
		}
		fclose($in);
		if($orig_box===false) {
			Epesi::alert($this->ht('Invalid mail to restore'));
			return false;
		}


		$id2 = Apps_MailClientCommon::move_msg($box,$dir,$box,$orig_box,$id);

		if($id2!==false) {
			$out = @fopen($trashpath,'w');
			if($out) {
				foreach($ret as $v)
					fputcsv($out,$v);
				fclose($out);
			}
			Base_StatusBarCommon::message('Message restored.');
		} else {
			Epesi::alert($this->ht('Unable to restore mail.'));
		}
	}

	public function delete_folder_callback($box,$dir) {
		Apps_MailClientCommon::remove_mailbox_subdir($box,$dir);
		$parent_dir = substr($dir,0,strrpos(rtrim($dir,'/'),'/')+1);
		$this->set_module_variable('opened_dir',$parent_dir);
	}


	////////////////////////////
	// other methods

	//gets inbox folders from tree
	private function get_move_folders(array $str,$label,$box) {
		$ret = array();
		foreach($str as $d=>$v) {
			if($d!=='Trash') {
				$p = $box.'/'.$d.'/';
				$l = $label.'/'.$d.'/';
				$ret[$p] = $l;
				$ret = array_merge($ret,$this->get_move_folders_inbox($v,$p,$l));
			}
		}
		return $ret;
	}

	private function get_move_folders_inbox(array $str,$path,$label) {
		$ret = array();
		foreach($str as $name=>$sub) {
			$l = $label.$name.'/';
			$p = $path.$name.'/';
			$ret[$p] = $l;
			$ret = array_merge($ret,$this->get_move_folders_inbox($sub,$p,$l));
		}
		return $ret;
	}

	/**
	 * Sets callbacks(open,create/edit/delete folder) in tree of mailboxes for Utils/Tree.
	 */
	private function get_tree_structure(array $str,$box,$dir='',$create_dir=false,$edit_dir=false,$delete_dir=false) {
		$opened_box = $this->get_module_variable('opened_box');
		$opened_dir = $this->get_module_variable('opened_dir');
		$ret = array();
		$imap_online = Apps_MailClientCommon::is_online($box);
		foreach($str as $k=>$v) {
			$cr = $create_dir;
			$ed = $edit_dir;
			$del = $delete_dir;
			$p = $dir.$k.'/';
			$arr = array('name'=>$k);

			if($opened_box==$box && $p==$opened_dir) {
				$arr['visible'] = true;
				$arr['selected'] = true;
				$arr['opened'] = true;
			}
			if(!$cr && strcasecmp($p,'Inbox/')==0) $cr = true;
			$msgs = Apps_MailClientCommon::get_number_of_messages($box,$p);
			if(!$msgs) return $ret;
			$unread_msgs = $msgs['unread'];
			$num_of_msgs = $msgs['all'];
			$arr['name'] = '<a '.$this->create_callback_href(array($this,'open_mail_dir_callback'),array($box,$p)).'>'.$arr['name'].($num_of_msgs!==false?' ('.($unread_msgs?$unread_msgs.'/':'').$num_of_msgs.')':'').'</a>';
			if($cr && $imap_online)
				$arr['name'] .= '<a '.$this->create_callback_href(array($this,'edit_folder_callback'),array($box,$p)).'><img src="'.Base_ThemeCommon::get_template_file('Apps_MailClient','create_folder.png').'" border="0"></a>';
			if($ed && $imap_online)
				$arr['name'] .= '<a '.$this->create_callback_href(array($this,'edit_folder_callback'),array($box,$dir,$k)).'><img src="'.Base_ThemeCommon::get_template_file('Apps_MailClient','edit_folder.png').'" border="0"></a>';
			if($del && $imap_online)
				$arr['name'] .= '<a '.$this->create_confirm_callback_href($this->ht('Delete this folder with all messages and subfolders?'),array($this,'delete_folder_callback'),array($box,$p)).'><img src="'.Base_ThemeCommon::get_template_file('Apps_MailClient','delete_folder.png').'" border="0"></a>';

			if((!$ed || !$del) && strcasecmp($p,'Inbox/')==0) {
				$ed = true;
				$del = true;
			}
			$arr['sub']=$this->get_tree_structure($v,$box,$p,$cr,$ed,$del);
			$ret[] = $arr;
		}
		return $ret;
	}

	//////////////////////////////////////////
	// screens

	public function edit_mail_src($headers=null,$body=null,$body_type=null,$body_ctype=null,$box=null,$type=null,$drop_callback=null) {
		if($this->is_back()) {
			$x = ModuleManager::get_instance('/Base_Box|0');
			if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
			$x->pop_main();
		}

		$f = $this->init_module('Libs/QuickForm');
		$theme = $this->init_module('Base/Theme');

		$f->addElement('header','mail_header',$this->t(($body===null || $type!='edit')?'New mail':'Edit mail'));
		$f->addElement('hidden','action','send','id="new_mail_action"');

		$from_mails = DB::GetAssoc('SELECT id,mail FROM apps_mailclient_accounts WHERE smtp_server is not null AND smtp_server!=\'\' AND user_login_id='.Acl::get_user().' ORDER BY mail');
		foreach($from_mails as $idk=>$mk)
			if(!Apps_MailClientCommon::is_online($idk)) unset($from_mails[$idk]);
		if($from_mails)
			$from = $from_mails;
		else
			$from = array('pm'=>$this->ht('Private message'));

		if($box!==null)
			$f->setDefaults(array('from_addr'=>$box));

		$f->addElement('select','from_addr',$this->t('From'),$from,array('onChange'=>'Apps_MailClient.from_change(this.value)'));
		eval_js('Apps_MailClient.from_change(\''.$f->exportValue('from_addr').'\')');
		$f->addRule('from_addr',$this->t('Field required'),'required');


		$f->addElement('text','subject',$this->t('Subject'),array('maxlength'=>256));
		$f->addRule('subject',$this->t('Max length of subject is 256 chars'),'maxlength',256);
		$fck = & $f->addElement('fckeditor', 'body', $this->t('Content'));
		$fck->setFCKProps('800','300',true);

		//if edit
		$fav2 = array();
		$references = false;
		if($headers!==null) {
			if($body===null) {
				$opts = array('decode_bodies'=>true,'include_bodies'=>true);
			} else {
				$headers.= "\r\n\r\n"; //fake message empty body in header
				$opts = array();
			}
			$structure = Apps_MailClientCommon::mime_decode($headers,$opts);

			if($body===null) {
				$msg = Apps_MailClientCommon::parse_message_structure($structure,false);
				$body = $msg['body'];
				$body_type = $msg['type'];
				$body_ctype = $msg['ctype'];
				$attachments = $msg['attachments'];
			}

			$to_addr = array();
			$to_addr_ex = array();
			$subject = isset($structure->headers['subject'])?Apps_MailClientCommon::mime_header_decode($structure->headers['subject']):'no subject';
			if($type=='edit' || $type=='edit_as_new') {
				$to_address = Apps_MailClientCommon::mime_header_decode($structure->headers['to']);
			} elseif($type=='reply') {
				$subject = 'Re: '.$subject;
				if(isset($structure->headers['message-id']))
					$references = $structure->headers['message-id'];
				$to_address = Apps_MailClientCommon::mime_header_decode(isset($structure->headers['reply-to'])?$structure->headers['reply-to']:$structure->headers['from']);
			} elseif($type=='forward') {
				$subject = 'Fwd: '.$subject;
			}
			if(isset($to_address)) {
				$to_address = explode(',',$to_address);
				foreach($to_address as $v) {
					if(preg_match('/<([0-9]+)@epesi_(contact|user)>$/',$v,$r)) {
						$to_addr_ex[] = $r[1].'@epesi_'.$r[2];
					} elseif(strpos($v,'@')!==false) {
						$to_addr[] = trim($v);
					}
				}
				foreach($to_addr_ex as $k=>$v) {
					if(!preg_match('/^([0-9]+)@epesi_(contact|user)$/',$v,$r)) { //invalid epesi address
						unset($to_addr_ex[$k]);
						continue;
					}
					switch($r[2]) {
						case 'contact':
							if(ModuleManager::is_installed('CRM/Contacts')>=0) {
								$v2 = CRM_ContactsCommon::get_contact($r[1]);
								if($v2===null) {
									unset($to_addr_ex[$k]);
									continue;
								}
								if($v2['email'])
				    					$to_addr = array_filter($to_addr,create_function('$o','return $o!=\''.$v2['email'].'\';'));
								$fav2[$v] = CRM_ContactsCommon::contact_format_default($v2,true);
							} else {
								unset($to_addr_ex[$k]);
							}
							break;
						case 'user':
							$v2 = DB::GetRow('SELECT l.login,p.mail FROM user_password p INNER JOIN user_login l ON p.user_login_id=l.id WHERE user_login_id=%d',array($r[1]));
							$fav2[$v] = $v2['login'];
							if($v2['mail'])
								$to_addr = array_filter($to_addr,create_function('$o','return $o!=\''.$v2['mail'].'\';'));
					} 
				}
			}
			if($type=='reply' || $type=='forward') {
				$msg_header = "\n\n--------- Original Message ---------\n".
							" Subject: $subject\n".
							" Date: ".Apps_MailClientCommon::mime_header_decode(isset($structure->headers['date'])?$structure->headers['date']:$this->ht('no date header specified'))."\n".
							" From: ".Apps_MailClientCommon::mime_header_decode(isset($structure->headers['from'])?$structure->headers['from']:$this->ht('no from header specified'))."\n".
							" To: ".Apps_MailClientCommon::mime_header_decode(isset($structure->headers['to'])?$structure->headers['to']:$this->ht('no from header specified'))."\n\n";
				if($body_type=='plain') {
					$body = $msg_header.$body;
				} else {
					$c=0;
					$msg_header = str_replace("\n","<br>",$msg_header);
					$body = preg_replace('/(<body[^>]*>)/i','$1'.$msg_header,$body,1,$c);
					if($c==0) {
						$body = $msg_header.$body; //TODO: there was no body block... produces invalid HTML
					}
				}
			}

			$to_addr = implode(', ',$to_addr);

			if($body_type=='plain') {
				if(preg_match("/charset=([a-z0-9\-]+)/i",$body_ctype,$reqs)) {
					$charset = $reqs[1];
					$body_ctype = "text/plain; charset=utf-8";
					$body = iconv($charset,'UTF-8',$body);
				}
				$body = htmlspecialchars(preg_replace("/(http:\/\/[a-z0-9]+(\.[a-z0-9]+)+(\/[\.a-z0-9]+)*)/i", "<a href='\\1'>\\1</a>", $body));
				$body = '<html>'.
					'<head><meta http-equiv="Content-Type" content="'.$body_ctype.'"></head>'.
					'<body><pre>'.$body.'</pre></body></html>';
			} else {
				$body = trim($body);
			}
			$body = preg_replace('/"cid:([^@]+@[^@]+)"/i','"preview.php?'.http_build_query($_GET).'&attachment_cid=$1"',$body);
			$body = preg_replace("/<a([^>]*)>(.*)<\/a>/i", '<a$1 target="_blank">$2</a>', $body);

			$f->setDefaults(array('body'=>$body,'subject'=>$subject, 'to_addr'=>$to_addr,'to_addr_ex'=>$to_addr_ex));
		}

		$f->addElement('text','to_addr',$this->t('To'),Utils_TooltipCommon::open_tag_attrs($this->t('You can enter more then one email address separating it with comma.')).' id="apps_mailclient_to_addr"');
//		$f->addRule('to_addr',$this->t('Invalid mail address'),'email');
		if(!$this->get_module_variable('addressbook_initialized',false)) {
			eval_js('Apps_MailClient.addressbook_hidden = '.($from_mails?'true':'false'));
			$this->set_module_variable('addressbook_initialized',true);
		}
		eval_js('Apps_MailClient.addressbook_toggle_init()');

		$theme->assign('addressbook','<a href="javascript:void(0)" onClick="Apps_MailClient.addressbook_toggle()">Addressbook</a>');
		$theme->assign('addressbook_area_id','apps_mailclient_addressbook');

		if(ModuleManager::is_installed('CRM/Contacts')>=0) {
			$fav = CRM_ContactsCommon::get_contacts($from_mails?array(':Fav'=>true,'(!email'=>'','|!login'=>''):array(':Fav'=>true,'!login'=>''),array('id','login','first_name','last_name','company_name'));
			foreach($fav as $v)
				$fav2[$v['id'].'@epesi_contact'] = CRM_ContactsCommon::contact_format_default($v,true);
			$rb1 = $this->init_module('Utils/RecordBrowser/RecordPicker');
			$this->display_module($rb1, array('contact' ,'to_addr_ex',array('Apps_MailClientCommon','addressbook_rp_mail'),$from_mails?array('(!email'=>'','|!login'=>''):array('!login'=>''),array('work_phone'=>false,'mobile_phone'=>false,'email'=>true,'login'=>true)));
			$theme->assign('addressbook_add_button',$rb1->create_open_link('Add contact'));
		} else {
			$fav2 = DB::GetAssoc('SELECT '.DB::Concat('id',DB::qstr('@epesi_user')).',login FROM user_login');
		}
		$f->addElement('multiselect','to_addr_ex','',$fav2);
		$f->addFormRule(array($this,'check_to_addr'));

		if($f->validate()) {
			$v = $f->exportValues();
			if(!isset($v['to_addr'])) $v['to_addr'] = '';
			$save_folder = 'Drafts';
			$subject = isset($v['subject'])?$v['subject']:'no subject';
			$date = date('D M d H:i:s Y');

			if($v['from_addr']!='pm')
				$from = Apps_MailClientCommon::get_mailbox_data($v['from_addr']);
			else
				$from = null;
			$to = explode(',',$v['to_addr']);
			$to_epesi = array();
			$to_epesi_names = array();
			foreach($v['to_addr_ex'] as $kk) {
				if(!preg_match('/^([0-9]+)@epesi_(contact|user)$/',$kk,$r)) { //invalid epesi address or new record from rpicker
					if(!is_numeric($kk) || ModuleManager::is_installed('CRM/Contacts')<0) //no rpicker, invalid epesi address
						continue;
					$r[2] = 'contact';
					$r[1] = $kk;
				}
				switch($r[2]) {
					case 'contact':
						if(ModuleManager::is_installed('CRM/Contacts')>=0) {
							$kk2 = CRM_ContactsCommon::get_contact($r[1]);
							if($kk2===null) {
								continue;
							}
							if(isset($kk2['login']) && $kk2['login']!=='') {
								$where = Base_User_SettingsCommon::get('Apps_MailClient','default_dest_mailbox',$kk2['login']);
								if($where=='both' || $where=='pm') {
									$to_epesi[] = $kk2['login'];
									$to_epesi_names[$kk2['login']] = CRM_ContactsCommon::contact_format_default($kk2,true).' <'.$r[1].'@epesi_contact>';
									if($where=='pm')
										continue;
								}
								if($kk2['email']=='') {
									$to[] = Base_User_LoginCommon::get_mail($kk2['login']);
									continue;
								}
							}
							if($kk2['email'])
								$to[] = $kk2['email'];
						}
						break;
					case 'user':
						$where = Base_User_SettingsCommon::get('Apps_MailClient','default_dest_mailbox',$r[1]);
						if($where=='both' || $where=='pm') {
							$to_epesi[] = $r[1];
							$to_epesi_names[$r[1]] = $fav2[$kk].' <'.$kk.'>';
							if($where=='pm')
								continue;
						}
						$to[] = Base_User_LoginCommon::get_mail($r[1]);
				}
			}
			$to2 = array();
			foreach($to as $jj=>$kk) {
				$kk = trim($kk);
				if(preg_match("/<(.*)>$/",$kk,$reqs)) {
					$kkk = trim($reqs[1]);
				} else
					$kkk = $kk;
				if($kkk=='' || isset($to2[$kkk])) {
					unset($to[$jj]);
					continue;
				}
				$to[$jj] = $kk;
				$to2[$kkk] = 1;
			}

			$ret = true;
			if(ModuleManager::is_installed('CRM/Contacts')>=0) {
				$my = CRM_ContactsCommon::get_my_record();
				if($my['id']!==-1) {
					$name = CRM_ContactsCommon::contact_format_default($my,true);
					$name_epesi_mail = $my['id'].'@epesi_contact';
				}
			}
			if(!isset($name)) {
				$name = Base_UserCommon::get_my_user_login();
				$name_epesi_mail = Acl::get_user().'@epesi_user';
			}

			if($v['from_addr']=='pm')
				$to_names = implode(', ',$to_epesi_names);
			else
				$to_names = implode(', ',array_merge($to,$to_epesi_names));

			if($v['action']=='send') {
				$save_folder = 'Sent';
				//remote delivery

				$send_ok = true;
				if($from && $to) {
					$mailer = Apps_MailClientCommon::new_mailer($box,$name);
					foreach($to2 as $m=>$mmm)
						$mailer->AddAddress($m);
					$mailer->Subject = $v['subject'];
					$mailer->CharSet = "utf-8";
					$mailer->IsHTML(true);
					$mailer->Body = $v['body'];
					$mailer->AltBody = strip_tags($v['body']);
					if($references)
						$mailer->AddCustomHeader('References: '.$references);
					$send_ok = $mailer->Send();
					if(!$send_ok) {
						Epesi::alert($mailer->ErrorInfo."\n\nMessage moved to Drafts folder.");
						$save_folder = 'Drafts';
					}
					unset($mailer);
				}

				//local delivery
				if($send_ok)
					foreach($to_epesi as $e) {
						$dest_id = DB::GetOne('SELECT id FROM apps_mailclient_accounts WHERE mail=\'#internal\' AND user_login_id=%d',array($e));
						if($dest_id===false) {
							$dest_id = Apps_MailClientCommon::create_internal_mailbox($e);
						}
						if(Apps_MailClientCommon::drop_message($dest_id,'Inbox/',$v['subject'],$name.' <'.$name_epesi_mail.'>',$to_names,$date,$v['body'])===false)
							Epesi::alert($this->ht('Unable to send message to %s',array($to_epesi_names[$e])));
					}
			}
			if($ret) {
				$dest_id = $from?$from['id']:DB::GetOne('SELECT id FROM apps_mailclient_accounts WHERE mail=\'#internal\' AND user_login_id=%d',array(Acl::get_user()));
				if(($mid = Apps_MailClientCommon::drop_message($dest_id,$save_folder.'/',$v['subject'],$from?$from['mail']:$this->ht('private message'),$to_names,$date,$v['body'],true))!==false) {
					if($drop_callback!==null && is_callable($drop_callback))
						call_user_func($drop_callback,$dest_id,$save_folder.'/',$mid);
					return false;
				}
			}
		}
		$f->assign_theme('form', $theme);

		$theme->display('new');

		Base_ActionBarCommon::add('save','Save',' href="javascript:void(0)" onClick="$(\'new_mail_action\').value=\'save\';'.addcslashes($f->get_submit_form_js(),'"').'"');
		Base_ActionBarCommon::add('send','Send',' href="javascript:void(0)" onClick="$(\'new_mail_action\').value=\'send\';'.addcslashes($f->get_submit_form_js(),'"').'"');
		Base_ActionBarCommon::add('back','Back',$this->create_back_href());

		return true;
	}

	public function edit_mail($box,$dir,$id=null,$type=null) {
		if($this->is_back()) return false;

		$message = null;
		$box_dir = Apps_MailClientCommon::get_mailbox_dir($box);
		if($box_dir === false) {
			Epesi::alert($this->ht('Invalid mailbox'));
		} elseif($id!==null) {
			$message = @file_get_contents($box_dir.$dir.$id);
			if($message===false)
				$message = null;
		}
		$ret = $this->edit_mail_src($message,null,null,null,$box,$type);
		if($ret===false) {
			if($id!==null && $type=='edit') 
				Apps_MailClientCommon::remove_msg($box,$dir,$id);
			location(array());
		}
		return $ret;
	}

	// qf filter
	public function check_to_addr($f) {
		if(empty($f['to_addr']) && empty($f['to_addr_ex']))
			return array('to_addr'=>$this->t('You must provide at least one recipient email address.'));
		return true;
	}

	public function edit_folder_callback($box,$dir,$folder=false) {
		if($this->is_back()) return false;

		$f = $this->init_module('Libs/QuickForm',null,'create_folder');
		$f->addElement('header',null,$folder===false?$this->t('Create folder in %s',array(trim($dir,'/'))):$this->t('Edit folder in %s',array(trim($dir,'/'))));
		$f->addElement('text','name',$this->t('Name'));
		$f->addRule('name',$this->t('Field required'),'required');
		$f->addRule('name',$this->t('Invalid character - only letters and digits are allowed'),'alphanumeric');
		if($folder!==false) {
			$f->setDefaults(array('name'=>$folder));
		}

		if($f->validate()) {
			$name = $f->exportValue('name');
			$new_name = $dir.$name.'/';
			if($folder!==false) { //edit
				Apps_MailClientCommon::rename_mailbox_subdir($box,$dir.$folder.'/',$new_name);
			} else {
				Apps_MailClientCommon::create_mailbox_subdir($box,$new_name);
			}
			$this->set_module_variable('opened_dir',$new_name);
			$this->set_module_variable('opened_box',$box);
			return false;
		}
		$f->display();

		Base_ActionBarCommon::add('save','Save',$f->get_submit_form_href());
		Base_ActionBarCommon::add('back','Back',$this->create_back_href());

		return true;
	}

	////////////////////////////////////////////////////////////
	//account management
	public function account_manager() {
		$gb = $this->init_module('Utils/GenericBrowser',null,'accounts');
		$gb->set_table_columns(array(
			array('name'=>$this->t('Mail'), 'order'=>'mail')
				));
		$ret = $gb->query_order_limit('SELECT id,mail FROM apps_mailclient_accounts WHERE mail!="#internal" AND user_login_id='.Acl::get_user(),'SELECT count(mail) FROM apps_mailclient_accounts WHERE mail!="#internal" AND user_login_id='.Acl::get_user());
		$num = 0;
		while($row=$ret->FetchRow()) {
			$r = & $gb->get_new_row();
			$r->add_data($row['mail']);
			$r->add_action($this->create_callback_href(array($this,'account'),array($row['id'],'edit')),'Edit');
			$r->add_action($this->create_callback_href(array($this,'account'),array($row['id'],'view')),'View');
			$r->add_action($this->create_confirm_callback_href($this->ht("Delete this account?"),array($this,'delete_account'),$row['id']),'Delete');
			$num++;
		}
		$this->display_module($gb);
		Base_ActionBarCommon::add('add','New account',$this->create_callback_href(array($this,'account'),array(null,'new')));
	}

	public function account($id,$action='view') {
		if($this->is_back()) return false;

		$f = $this->init_module('Libs/QuickForm');

		$defaults=null;
		if($action!='new') {
			$defaults = Apps_MailClientCommon::get_mailbox_data($id);
		}

		$native_support = true;
		if(!function_exists('imap_open')) {
			$native_support = false;
			if($defaults)
				$defaults['incoming_protocol'] = 0;
			$pop3_methods = array('auto'=>'Automatic', 'DIGEST-MD5'=>'DIGEST-MD5', 'CRAM-MD5'=>'CRAM-MD5', 'APOP'=>'APOP', 'PLAIN'=>'PLAIN', 'LOGIN'=>'LOGIN', 'USER'=>'USER');
			eval_js('Event.observe(\'mailclient_smtp_auth\',\'change\',function(x) {'.
					'if(this.checked==true) {this.form.smtp_login.disabled=false;this.form.smtp_password.disabled=false;} else {this.form.smtp_login.disabled=true;this.form.smtp_password.disabled=true;}'.
					'})');
		} else {
			eval_js('Event.observe(\'mailclient_incoming_protocol\',\'change\',function(x) {'.
					'if(this.value==0) this.form.pop3_leave_msgs_on_server.disabled=false; else this.form.pop3_leave_msgs_on_server.disabled=true;'.
					'});'.
				'Event.observe(\'mailclient_smtp_auth\',\'change\',function(x) {'.
					'if(this.checked==true) {this.form.smtp_login.disabled=false;this.form.smtp_password.disabled=false;} else {this.form.smtp_login.disabled=true;this.form.smtp_password.disabled=true;}'.
					'})');
		}

		$cols = array(
				array('name'=>'header','label'=>$this->t(ucwords($action).' account'),'type'=>'header'),
				array('name'=>'mail','label'=>$this->t('Mail address'),'rule'=>array(array('type'=>'email','message'=>$this->t('This isn\'t valid e-mail address'), 'param'=>true))),
				array('name'=>'login','label'=>$this->t('Login')),
				array('name'=>'password','label'=>$this->t('Password'),'type'=>'password'),

				array('name'=>'in_header','label'=>$this->t('Incoming mail'),'type'=>'header'),
				array('name'=>'incoming_protocol','label'=>$this->t('Incoming protocol'),'type'=>'select','values'=>array(0=>'POP3',1=>'IMAP'), 'default'=>0,'param'=>array('id'=>'mailclient_incoming_protocol')+($native_support?array():array('disabled'=>1))),
				array('name'=>'incoming_server','label'=>$this->t('Incoming server address')),
				array('name'=>'incoming_ssl','label'=>$this->t('Receive with SSL')));
		if(!$native_support)
			$cols[] = array('name'=>'incoming_method','label'=>$this->t('Authorization method'),'type'=>'select','values'=>$pop3_methods, 'default'=>'auto');
		$cols = array_merge($cols,
			array(array('name'=>'pop3_leave_msgs_on_server','label'=>$this->t('Remove messages from server'),'type'=>'select',
					'values'=>array(0=>'immediately',1=>'after 1 day', 3=>'after 3 days', 7=>'after 1 week', 14=>'after 2 weeks', 30=>'after 1 month', -1=>'never'),
					'default'=>'0','param'=>((isset($defaults) && $defaults['incoming_protocol']) || ($f->getSubmitValue('submited') && $f->getSubmitValue('incoming_protocol')))?array('disabled'=>1):array()),

				array('name'=>'out_header','label'=>$this->t('Outgoing mail'),'type'=>'header'),
				array('name'=>'smtp_server','label'=>$this->t('SMTP server address')),
				array('name'=>'smtp_ssl','label'=>$this->t('Send with SSL')),
				array('name'=>'smtp_auth','label'=>$this->t('SMTP authorization required'),'param'=>array('id'=>'mailclient_smtp_auth')),
				array('name'=>'smtp_login','label'=>$this->t('Login'),'param'=>((isset($defaults) && $defaults['smtp_auth']==0) || ($f->getSubmitValue('submited') && !$f->getSubmitValue('smtp_auth')))?array('disabled'=>1):array()),
				array('name'=>'smtp_password','label'=>$this->t('Password'),'type'=>'password','param'=>((isset($defaults) && $defaults['smtp_auth']==0) || ($f->getSubmitValue('submited') && !$f->getSubmitValue('smtp_auth')))?array('disabled'=>1):array())
			));

		$f->add_table('apps_mailclient_accounts',$cols);
		$f->setDefaults($defaults);

		if($action=='view') {
			Base_ActionBarCommon::add('edit','Edit',$this->create_callback_href(array($this,'account'),array($id,'edit')));
			$f->freeze();
		} else {
			$f->addElement('submit',null,'Save','style="display:none"'); //provide on ENTER submit event
			if($f->validate()) {
				$values = $f->exportValues();
				$dbup = array('id'=>$id, 'user_login_id'=>Acl::get_user());
				foreach($cols as $v) {
					if(preg_match("/header$/",$v['name'])) continue;
					if(isset($values[$v['name']]))
						$dbup[$v['name']] = $values[$v['name']];
					else
						$dbup[$v['name']] = 0;
				}
				if(!$native_support)
					$dbup['incoming_protocol'] = 0;
				DB::Replace('apps_mailclient_accounts', $dbup, array('id'), true,true);
				if($action=='new')
					$id = DB::Insert_ID('apps_mailclient_accounts','id');
				Apps_MailClientCommon::get_mailbox_data($id,$use_cache=false);
				if($action=='new')
					Apps_MailClientCommon::create_mailbox_dir($id);
				if($values['incoming_protocol']==1) { //imap
					eval_js('Apps_MailClient.cache_mailboxes_start()',false);//make sure cache is working
				}
				return false;
			}
			Base_ActionBarCommon::add('save','Save',' href="javascript:void(0)" onClick="'.addcslashes($f->get_submit_form_js(),'"').'"');
		}
		$f->display();

		Base_ActionBarCommon::add('back','Back',$this->create_back_href());

		return true;
	}

	public function delete_account($id){
		$box_dir = Apps_MailClientCommon::get_mailbox_dir($id);
		if($box_dir===false) {
			Epesi::alert($this->ht('Invalid mailbox'));
			return;
		}
		recursive_rmdir($box_dir);
		DB::Execute('DELETE FROM apps_mailclient_accounts WHERE id=%d',array($id));
	}

	//////////////////////////////////////////////////////////////////
	//applet
	public function applet($conf, $opts) {
		$opts['go'] = true;
		Base_ThemeCommon::load_css($this->get_type());
		$accounts = array();
		$ret = array();
		$update_applet = '';
		foreach($conf as $key=>$on) {
			$x = explode('_',$key);
			if($x[0]=='account' && $on) {
				$id = $x[1];
				$mail = Apps_MailClientCommon::get_mailbox_data($id);
				if(!$mail) continue;
				$mail = $mail['mail'];

				if($mail==='#internal') $mail = $this->t('Private messages');

				$cell_id = 'mailaccount_'.$opts['id'].'_'.$id;
				$ret[$mail] = '<span id="'.$cell_id.'"></span>';

				//interval execution
				eval_js_once('setInterval(\'Apps_MailClient.update_msg_num('.$opts['id'].' ,'.$id.' , 0)\',300000)');

				//and now
				$update_applet .= 'Apps_MailClient.update_msg_num('.$opts['id'].' ,'.$id.' ,1);';
			}
		}
		$this->js($update_applet);
		$check_action = $this->check_mail_href(str_replace('1);','0);',$update_applet));
		$opts['actions'][] = '<a '.Utils_TooltipCommon::open_tag_attrs($this->t('Check mail')).' '.$check_action.'><img src="'.Base_ThemeCommon::get_template_file($this->get_type(),'check_small.png').'" border="0"></a>';
		$th = $this->init_module('Base/Theme');
		$th->assign('accounts',$ret);
		$th->display('applet');
	}

	///////////////////////////////////////////////////
	// admin

	public function admin() {
		if($this->is_back()) {
			$this->parent->reset();
		}

		$form = & $this->init_module('Libs/QuickForm',null,'mailclient_setup');

		$form->addElement('header', 'module_header', $this->t('Mail messages setup'));
		$s = array();
		for($i=5; $i<250; $i*=2) {
			$k = $i*1024*1024;
			$s[$k] = filesize_hr($k);
		}
		$form->addElement('select','max_mail_size',$this->t('Max downloaded mail size'), $s);

		$form->setDefaults(array('max_mail_size'=>Variable::get('max_mail_size')));

		Base_ActionBarCommon::add('back', 'Back', $this->create_back_href());
		Base_ActionBarCommon::add('save', 'Save', $form->get_submit_form_href());

		if($form->validate()) {
			if($form->process(array($this,'submit_admin'))) {
				$this->parent->reset();
			}
		} else $form->display();
	}
	
	public function submit_admin($data) {
		return Variable::set('max_mail_size',$data['max_mail_size']);
	}

	///////////////////////////////////////////
	// filters management

	public function manage_filters() {
		// account selection
		$accounts = DB::GetAssoc('SELECT id,mail FROM apps_mailclient_accounts WHERE user_login_id=%d',array(Acl::get_user()));
		$def_account = & $this->get_module_variable('filters_account',null);
		if(empty($accounts))
			$accounts[Apps_MailClientCommon::create_internal_mailbox()] = Base_LangCommon::ts('Apps_MailClient','Private messages');
		else
			foreach($accounts as $k=>$row)
				if($row==='#internal')
					$accounts[$k] = Base_LangCommon::ts('Apps_MailClient','Private messages');
		
		if($def_account == null || !isset($accounts[$def_account])) {
		    $def_account = current(array_keys($accounts));
		}

		$form = & $this->init_module('Libs/QuickForm',null,'mailclient_setup');

		$form->addElement('select', 'account', $this->t('Mail account'),$accounts, array('onChange'=>$form->get_submit_form_js()));
		$form->setDefaults(array('account'=>$def_account));

		if($form->validate()) {
			$vals = $form->exportValues();
			if(isset($vals['account']) && is_numeric($vals['account'])) 
				$def_account = $vals['account'];
		}
		$form->display();
		

		// filters browser 
		$gb = $this->init_module('Utils/GenericBrowser',null,'filters');
		$gb->set_table_columns(array(
			array('name'=>$this->t('Name'), 'order'=>'name')
				));
		$ret = $gb->query_order_limit('SELECT id,name FROM apps_mailclient_filters WHERE account_id='.$def_account,'SELECT count(id) FROM apps_mailclient_filters WHERE account_id='.$def_account);
		while($row=$ret->FetchRow()) {
			$r = & $gb->get_new_row();
			$r->add_data($row['name']);
			$r->add_action($this->create_callback_href(array($this,'filter'),array($row['id'],'edit')),'Edit');
			$r->add_action($this->create_confirm_callback_href($this->ht("Delete this filter?"),array($this,'delete_filter'),$row['id']),'Delete');
		}
		$this->display_module($gb);
		Base_ActionBarCommon::add('add','New filter',$this->create_callback_href(array($this,'filter'),array(null,'new')));
	}
	
	public function filter($id, $action='new') {
		if($this->is_back()) return false;
		
		$f = $this->init_module('Libs/QuickForm');
		$theme = $this->init_module('Base/Theme');
		
		$f->addElement('header','general_header',$this->t('General'));
		$f->addElement('text','name',$this->t('Name'),array('maxlength'=>64));
		$f->addRule('name',$this->t('Max length of field exceeded'),'maxlength',64);
		$f->addRule('name',$this->t('Field required'),'required');
		$f->addElement('select','match',$this->t('For incoming messages that match'),array('allrules'=>Base_LangCommon::ts('Apps_MailClient','all of the following rules'), 'anyrule'=>Base_LangCommon::ts('Apps_MailClient','any of the following rules'), 'allmessages'=>Base_LangCommon::ts('Apps_MailClient','all messages')),array('onChange'=>'Apps_MailClient.filters_match_change(this.value)'));
		$f->addRule('match',$this->t('Field required'),'required');
		$f->setDefaults(array('match'=>'anyrule'));


		if($action!='new') { //view or edit
			$row = DB::GetRow('SELECT name,match_method FROM apps_mailclient_filters WHERE id=%d',array($id));
			$f->setDefaults(array('name'=>$row['name'],'match'=>Apps_MailClientCommon::filter_match_method($row['match_method'])));

			$ret = DB::Execute('SELECT header,rule,value FROM apps_mailclient_filter_rules WHERE filter_id=%d',array($id));
			$rid = 1;
			$rules_def = array();
			while($row = $ret->FetchRow()) {
				$rules_def[$rid] = array('value'=>$row['value'], 'match'=>Apps_MailClientCommon::filter_rules_match($row['rule']), 'header'=>$row['header']);
				$rid++;
			}
			$f->setDefaults(array('rule'=>$rules_def,'rules_ids'=>implode(',',array_keys($rules_def))));

			$ret = DB::Execute('SELECT action,value FROM apps_mailclient_filter_actions WHERE filter_id=%d',array($id));
			$rid = 1;
			$actions_def = array();
			while($row = $ret->FetchRow()) {
				$row_action = Apps_MailClientCommon::filter_actions($row['action']);
				if($row_action=='move' || $row_action=='copy')
					$value_name = 'value_box';
				else
					$value_name = 'value';
				$actions_def[$rid] = array($value_name=>$row['value'], 'action'=>$row_action);
				$rid++;
			}
			$f->setDefaults(array('action'=>$actions_def,'actions_ids'=>implode(',',array_keys($actions_def))));
		}
		

		eval_js('Apps_MailClient.filters_match_change(\''.$f->exportValue('match').'\')');

		//rules
		$f->addElement('header','rules_header',$this->t('Rules'));
		$f->addElement('hidden','rules_ids','1',array('id'=>'mail_filters_rules_ids'));
		$rules_ids = $f->exportValue('rules_ids');
		if($rules_ids==='')
			trigger_error('Filter rules_ids field empty!');
		$rules_ids = explode(',',$rules_ids);
		$theme->assign('rules_ids',$rules_ids);
		
		$rules_ids[] = 'template';
		foreach($rules_ids as $rid) {
			$g = array();
			$g[] = $f->createElement('select','header','',array('subject'=>$this->ht('Subject'),'from'=>$this->ht('From')));
			$g[] = $f->createElement('select','match','',array('contains'=>Base_LangCommon::ts('Apps_MailClient','contains'),'notcontains'=>Base_LangCommon::ts('Apps_MailClient','doesn\'t contains'),'is'=>Base_LangCommon::ts('Apps_MailClient','is'),'notis'=>Base_LangCommon::ts('Apps_MailClient','isn\'t'),'begins'=>Base_LangCommon::ts('Apps_MailClient','begins with'),'ends'=>Base_LangCommon::ts('Apps_MailClient','ends with')));
			$g[] = $f->createElement('text','value');
			$g[] = $f->createElement('button','remove',$this->ht('Remove rule'),array('onClick'=>'Apps_MailClient.filter_remove(\'rule\','.$rid.')'));
			$f->addGroup($g,'rule['.$rid.']');
		}
		$theme->assign('rule_template_block','mail_filters_rules_template');
		$theme->assign('rules_elements','mail_filters_rules_elements');
		$theme->assign('rule_remove_block','mail_filters_rule_');
		$theme->assign('rules_block','mail_filters_rules_block');
		$f->addElement('button','add_rule_button',$this->t('Add rule'),array('onClick'=>'Apps_MailClient.filter_add(\'rule\')'));

		//actions
		$f->addElement('header','actions_header',$this->t('Actions'));
		$f->addElement('hidden','actions_ids','1',array('id'=>'mail_filters_actions_ids'));
		$actions_ids = $f->exportValue('actions_ids');
		if($actions_ids==='')
			trigger_error('Filter actions_ids field empty!');
		$actions_ids = explode(',',$actions_ids);
		$theme->assign('actions_ids',$actions_ids);

		//move and copy mboxes
		$boxes = Apps_MailClientCommon::get_mailbox_data();
		$move_folders = array();
		foreach($boxes as $v) {
			$name = $v['mail']=='#internal'?$this->t('Private messages'):$v['mail'];
			$str = Apps_MailClientCommon::get_mailbox_structure($v['id']);
			$move_folders = array_merge($move_folders,$this->get_move_folders($str,$name,$v['id']));
		}
		
		$actions_ids[] = 'template';
		foreach($actions_ids as $rid) {
			$g = array();
			$g[] = $f->createElement('select','action','',array('move'=>Base_LangCommon::ts('Apps_MailClient','Move message to'),'copy'=>Base_LangCommon::ts('Apps_MailClient','Copy message to'),'forward'=>Base_LangCommon::ts('Apps_MailClient','Forward message to'),'forward_delete'=>Base_LangCommon::ts('Apps_MailClient','Forward message to ... and delete.'),'read'=>Base_LangCommon::ts('Apps_MailClient','Mark as read'),'delete'=>Base_LangCommon::ts('Apps_MailClient','Delete message pernamently')),array('onChange'=>'Apps_MailClient.filter_action_change('.$rid.',this.value)'));
			$g[] = $f->createElement('text','value','',array('id'=>'mail_filter_action_value_'.$rid));
			$g[] = $f->createElement('select','value_box','',$move_folders,array('id'=>'mail_filter_action_value_box_'.$rid));
			$g[] = $f->createElement('button','remove',$this->ht('Remove action'),array('onClick'=>'Apps_MailClient.filter_remove(\'action\','.$rid.')'));
			$f->addGroup($g,'action['.$rid.']');
			$val = $f->exportValue('action['.$rid.']');
			eval_js('Apps_MailClient.filter_action_change(\''.$rid.'\',\''.(isset($val['action'])?$val['action']:'').'\')');
		}
		$theme->assign('action_template_block','mail_filters_actions_template');
		$theme->assign('actions_elements','mail_filters_actions_elements');
		$theme->assign('action_remove_block','mail_filters_action_');
		$f->addElement('button','add_action_button',$this->t('Add action'),array('onClick'=>'Apps_MailClient.filter_add(\'action\')'));


		//validate rules and actions
		$f->addFormRule(array($this,'check_rules_and_actions'));
		
		if($f->validate()) {
		    $vals = $f->exportValues();
		    if($action=='new') {
			    DB::Execute('INSERT INTO apps_mailclient_filters (account_id,name,match_method) VALUES (%d, %s, %d)',
				    array($this->get_module_variable('filters_account'),$vals['name'],Apps_MailClientCommon::filter_match_method($vals['match'])));
			    $id = DB::Insert_ID('apps_mailclient_filters','id');
		    } else { //edit
			    DB::Execute('UPDATE apps_mailclient_filters SET name=%s, match_method=%d WHERE id=%d',
				    array($vals['name'],Apps_MailClientCommon::filter_match_method($vals['match']),$id));
			    DB::Execute('DELETE FROM apps_mailclient_filter_rules WHERE filter_id=%d',array($id));
			    DB::Execute('DELETE FROM apps_mailclient_filter_actions WHERE filter_id=%d',array($id));
		    }
		    foreach($vals['rule'] as $i=>$row) {
			    if($i=='template') continue;
			    DB::Execute('INSERT INTO apps_mailclient_filter_rules (filter_id,header,rule,value) VALUES (%d, %s, %d, %s)',
				    array($id,$row['header'],Apps_MailClientCommon::filter_rules_match($row['match']),$row['value']));
		    }
		    foreach($vals['action'] as $i=>$row) {
			    if($i=='template') continue;
			    if($row['action'] == 'move' || $row['action'] == 'copy')
				    $row['value'] = $row['value_box'];
			    DB::Execute('INSERT INTO apps_mailclient_filter_actions (filter_id,action,value) VALUES (%d, %d, %s)',
				    array($id,Apps_MailClientCommon::filter_actions($row['action']),$row['value']));
		    }
		    return false;			
		}

		$f->assign_theme('form', $theme);

		$theme->display('filter_new');

		Base_ActionBarCommon::add('save','Save',$f->get_submit_form_href());
		Base_ActionBarCommon::add('back','Back',$this->create_back_href());

		return true;
		
	}
	
	public function check_rules_and_actions($f) {
		$ret = array();
		foreach($f['rule'] as $id=>$r) {
			if($id=='template') continue;
			if(empty($r['value']))
				$ret['rule['.$id.']'] = $this->t('Value required.');
		}
		foreach($f['action'] as $id=>$r) {
			if($id=='template') continue;
			if($r['action']=='move' || $r['action']=='copy') {
				if(empty($r['value_box']))
					$ret['action['.$id.']'] = $this->t('Value required.');
			} elseif($r['action']=='forward' || $r['action']=='forward_delete') {
				if(empty($r['value'])) {
					$ret['action['.$id.']'] = $this->t('Value required.');
					break;
				}
				$mr = new HTML_QuickForm_Rule_Email();
				if(!$mr->validate($r['value'],true))
					$ret['action['.$id.']'] = $this->t('This isn\'t valid e-mail address');
			}
		}
		if(!empty($ret))
			return $ret;
		return true;
	}
	
	public function delete_filter($id) {
		DB::Execute('DELETE FROM apps_mailclient_filter_rules WHERE filter_id=%d',array($id));
		DB::Execute('DELETE FROM apps_mailclient_filter_actions WHERE filter_id=%d',array($id));
		DB::Execute('DELETE FROM apps_mailclient_filters WHERE id=%d',array($id));
	}
	
	//////////////////////////////////////////////
	// other

	public function caption() {
		return "Mail client";
	}


}

?>
