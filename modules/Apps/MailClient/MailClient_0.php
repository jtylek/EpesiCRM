<?php
/**
 * Simple mail client
 *
 * TODO: 
 * -drafts, sent i trash to specjalne foldery, wszystkie inne traktujemy tak jak inbox
 * -zalaczniki przy new
 * -dodawanie, edycja folderow w mailboxach
 * -obsluga imap
 * -obsluga ssl przy wysylaniu smtp
 * 
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package apps-mail
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_MailClient extends Module {
	private $lang;
	
	public function construct() {
		$this->lang = $this->init_module('Base/Lang');
	}
	
	public function body() {
		$def_box = Apps_MailClientCommon::get_default_box();

		$box_file = $this->get_module_variable('opened_box',$def_box);
		$preview_id = $this->get_path().'preview';
		$show_id = $this->get_path().'show';

		$th = $this->init_module('Base/Theme');
		$tree = $this->init_module('Utils/Tree');
		$str = Apps_MailClientCommon::get_mail_dir_structure();
		$this->set_open_mail_dir_callbacks($str);
		$tree->set_structure($str);
		$tree->sort();
		$th->assign('tree', $this->get_html_of_module($tree));
		
		$box = Apps_MailClientCommon::get_index(ltrim($box_file,'/'));
		if($box===false) {
			print('Invalid mailbox');
			return;
		}

		$drafts_folder = false;
		$sent_folder = false;
		$trash_folder = false;
		if(ereg('Drafts$',$box_file))
				$drafts_folder = true;
		elseif(ereg('Sent$',$box_file))
				$sent_folder = true;
		elseif(ereg('Trash$',$box_file))
				$trash_folder = true;
		
		$gb = $this->init_module('Utils/GenericBrowser',null,'list');
		$cols = array();
		$cols[] = array('name'=>$this->lang->t('ID'), 'order'=>'id','width'=>'3', 'display'=>DEBUG);
		$cols[] = array('name'=>$this->lang->t('Subject'), 'search'=>1, 'order'=>'subj','width'=>'50');
		$cols[] = array('name'=>$this->lang->t('To'), 'search'=>1,'quickjump'=>1, 'order'=>'to','width'=>'10', 'display'=>($drafts_folder || $sent_folder || $trash_folder));
		$cols[] = array('name'=>$this->lang->t('From'), 'search'=>1,'quickjump'=>1, 'order'=>'from','width'=>'10','display'=>($trash_folder || !($drafts_folder || $sent_folder)));
		$cols[] = array('name'=>$this->lang->t('Date'), 'search'=>1, 'order'=>'date','width'=>'15');
		$cols[] = array('name'=>$this->lang->t('Size'), 'search'=>1, 'order'=>'size','width'=>'10');
		$gb->set_table_columns($cols);

		
		$gb->set_default_order(array($this->lang->t('Date')=>'DESC'));
		$gb->force_per_page(10);
	
		$limit_max = count($box);
		
		load_js($this->get_module_dir().'utils.js');
		
		foreach($box as $id=>$data) {
			$r = $gb->get_new_row();
			$subject = Apps_MailClientCommon::mime_header_decode($data['subject']);
			$to_address = Apps_MailClientCommon::mime_header_decode($data['to']);
			$from_address = Apps_MailClientCommon::mime_header_decode($data['from']);
			$subject = strip_tags($subject);
			if(strlen($subject)>40) $subject = Utils_TooltipCommon::create(substr($subject,0,38).'...',$subject);
			$r->add_data($id,array('value'=>'<a href="javascript:void(0)" onClick="Apps_MailClient.preview(\''.$preview_id.'\',\''.http_build_query(array('box'=>$box_file, 'msg_id'=>$id, 'pid'=>$preview_id)).'\',\''.$id.'\')" id="apps_mailclient_msg_'.$id.'" '.($data['read']?'':'style="font-weight:bold"').'>'.$subject.'</a>','order_value'=>$subject),htmlentities($to_address),htmlentities($from_address),array('value'=>Base_RegionalSettingsCommon::time2reg($data['date']), 'order_value'=>strtotime($data['date'])),array('style'=>'text-align:right','value'=>filesize_hr($data['size']), 'order_value'=>$data['size']));
			$lid = 'mailclient_link_'.$id;
			$r->add_action('href="javascript:void(0)" rel="'.$show_id.'" class="lbOn" id="'.$lid.'" ','View');
			$r->add_action($this->create_confirm_callback_href($this->lang->ht('Delete this message?'),array($this,'remove_mail'),array($box_file,$id)),'Delete');
			if($drafts_folder) {
				$r->add_action($this->create_callback_href(array($this,'edit_mail'),array($box_file,$id,'edit')),'Edit');
			} elseif($sent_folder) {
				$r->add_action($this->create_callback_href(array($this,'edit_mail'),array($box_file,$id,'edit_as_new')),'Edit');
			} elseif($trash_folder) {
				$r->add_action($this->create_callback_href(array($this,'restore_mail'),array($box_file,$id)),'Restore');
			} else {
				$r->add_action($this->create_callback_href(array($this,'edit_mail'),array($box_file,$id,'reply')),'Reply');			
			}
			$r->add_action($this->create_callback_href(array($this,'edit_mail'),array($box_file,$id,'forward')),'Forward');			
			$r->add_js('Event.observe(\''.$lid.'\',\'click\',function() {Apps_MailClient.preview(\''.$show_id.'\',\''.http_build_query(array('box'=>$box_file, 'msg_id'=>$id, 'pid'=>$show_id)).'\',\''.$id.'\')})');
		}
		
		$th->assign('list', $this->get_html_of_module($gb,array(true),'automatic_display'));
		$th->assign('subject_label',$this->lang->t('Subject'));
		$th->assign('preview_subject','<div id="'.$preview_id.'_subject"></div>');
		if($drafts_folder || $sent_folder)
			$th->assign('address_label',$this->lang->t('To'));
		else
			$th->assign('address_label',$this->lang->t('From'));
		$th->assign('preview_address','<div id="'.$preview_id.'_address"></div>');
		$th->assign('preview_attachments','<div id="'.$preview_id.'_attachments"></div>');
		$th->assign('preview_body','<iframe id="'.$preview_id.'_body" style="width:100%;height:70%"></iframe>');
		$th->display();

		$th_show = $this->init_module('Base/Theme');
		$th_show->assign('subject_label',$this->lang->t('Subject'));
		if($drafts_folder || $sent_folder)
			$th_show->assign('address_label',$this->lang->t('To'));
		else
			$th_show->assign('address_label',$this->lang->t('From'));
		$th_show->assign('subject','<div id="'.$show_id.'_subject"></div>');
		$th_show->assign('address','<div id="'.$show_id.'_address"></div>');
		$th_show->assign('attachments','<div id="'.$show_id.'_attachments"></div>');
		$th_show->assign('body','<iframe id="'.$show_id.'_body" style="width:95%;height:90%"></iframe>');
		$th_show->assign('close','<a class="lbAction" rel="deactivate">Close</a>');
		print('<div id="'.$show_id.'" class="leightbox">');
		$th_show->display('message');
		print('</div>');
		
		$checknew_id = $this->get_path().'checknew';
		Base_ActionBarCommon::add('folder',$this->lang->t('Check'),'href="javascript:void(0)" rel="'.$checknew_id.'" class="lbOn" id="'.$checknew_id.'b"');
//		if(DB::GetOne('SELECT 1 FROM apps_mailclient_accounts WHERE smtp_server is not null AND smtp_server!=\'\' AND user_login_id='.Acl::get_user())) //bo bedzie internal
		Base_ActionBarCommon::add('add',$this->lang->ht('New mail'),$this->create_callback_href(array($this,'edit_mail'),array($box_file)));
		Base_ActionBarCommon::add('scan',$this->lang->ht('Mark all as read'),$this->create_confirm_callback_href($this->lang->ht('Are you sure?'),array($this,'mark_all_as_read')));
		eval_js('Apps_MailClient.check_mail_button_observe(\''.$checknew_id.'\')');
		print('<div id="'.$checknew_id.'" class="leightbox"><div style="width:100%;text-align:center" id="'.$checknew_id.'progresses"></div>'.
			'<a id="'.$checknew_id.'L" style="display:none" href="javascript:void(0)" onClick="Apps_MailClient.hide(\''.$checknew_id.'\');Epesi.request(\'\');">'.$this->lang->t('hide').'</a>'.
			'</div>');
//echo('<script>function destroy_me(parent) {var x=parent.$(\''.$_GET['id'].'X\');x.parentNode.removeChild(x);parent.leightbox_deactivate(\''.$_GET['id'].'\')}</script>');
//echo('<a href="javascript:destroy_me(parent)">hide</a>');
	}
	
	public function mark_all_as_read() {
		$box_file = $this->get_module_variable('opened_box');
		Apps_MailClientCommon::mark_all_as_read($box_file);
	}
	
	public function restore_mail($box,$id) {
		$box = trim($box,'/');
		$trashpath = Apps_MailClientCommon::get_mail_dir().$box.'/.del';

		$in = @fopen($trashpath,'r');
		if($in===false) {
			Epesi::alert($this->lang->ht('Invalid mail to restore'));
			return false;
		}
		$ret = array();
		$orig_box = false;
		while (($data = fgetcsv($in, 700)) !== false) {
			$num = count($data);
			if($num!=2) continue;
			if($data[0]==$id) {
				$orig_box = $data[1];
				continue;
			}
			$ret[] = $data;
		}
		fclose($in);
		if($orig_box===false) {
			Epesi::alert($this->lang->ht('Invalid mail to restore'));
			return false;
		}


		$box = trim($box,'/');
		$x = explode('/',$box,2);
		$orig_box = trim($orig_box,'/');
		$y = explode('/',$orig_box,2);
		$id2 = Apps_MailClientCommon::move_msg($x[0],$x[1],$y[0],$y[1],$id);
		
		if($id2!==false) {
			$out = @fopen($trashpath,'w');
			if($out) {
				foreach($ret as $v)
					fputcsv($out,$v);
				fclose($out);
			}
			Base_StatusBarCommon::message('Message restored.');
		} else {
			Epesi::alert($this->lang->ht('Unable to restore mail.'));
		}
	}
	
	public function edit_mail($box,$id=null,$type=null) {
		if($this->is_back()) return false;
		
		$f = $this->init_module('Libs/QuickForm');
		$theme = $this->init_module('Base/Theme');
		
		$f->addElement('header','mail_header',$this->lang->t(($id===null || $type!='edit')?'New mail':'Edit mail'));
		$f->addElement('hidden','action','send','id="new_mail_action"');
		
		$from_mails = DB::GetAssoc('SELECT id,mail FROM apps_mailclient_accounts WHERE smtp_server is not null AND smtp_server!=\'\' AND user_login_id='.Acl::get_user().' ORDER BY mail');
		if($from_mails)
			$from = $from_mails;
		else
			$from = array('pm'=>$this->lang->ht('Private message'));
		
		$box = trim($box,'/');
		$m = explode('/',$box,2);
		$m = str_replace(array('__at__','__dot__'),array('@','.'),$m[0]);
		foreach($from_mails as $k=>$v)
			if($v==$m) {
				$f->setDefaults(array('from_addr'=>$k));
				break;
			}
			
		eval_js_once('apps_mailclient_from_change = function(v) {'.
						'if(v==\'pm\') {'.
							'$("apps_mailclient_to_addr").disable();'.
						'} else {'.
							'$("apps_mailclient_to_addr").enable();'.
						'}}');
		$f->addElement('select','from_addr',$this->lang->t('From'),$from,array('onChange'=>'apps_mailclient_from_change(this.value)'));
		eval_js('apps_mailclient_from_change(\''.$f->exportValue('from_addr').'\')');
		$f->addRule('from_addr',$this->lang->t('Field required'),'required');
		$f->addElement('text','to_addr',$this->lang->t('To'),Utils_TooltipCommon::open_tag_attrs($this->lang->t('You can enter more then one email address separating it with comma.')).' id="apps_mailclient_to_addr"');
//		$f->addRule('to_addr',$this->lang->t('Invalid mail address'),'email');
		eval_js_once('var apps_mailclient_addressbook_hidden = '.($from_mails?'true':'false').';'.
						'apps_mailclient_addressbook_toggle = function() {'.
						'if(apps_mailclient_addressbook_hidden) {'.
							'Effect.SlideDown(\'apps_mailclient_addressbook\',{duration:0.3});'.
							'apps_mailclient_addressbook_hidden = false;'.
						'} else {'.
							'Effect.SlideUp(\'apps_mailclient_addressbook\',{duration:0.3});'.
							'apps_mailclient_addressbook_hidden = true;'.
						'}};'.
						'apps_mailclient_addressbook_toggle_init = function() {'.
						'if(apps_mailclient_addressbook_hidden) {'.
							'$(\'apps_mailclient_addressbook\').hide();'.
						'} else {'.
							'$(\'apps_mailclient_addressbook\').show();'.
						'}};');
		eval_js('apps_mailclient_addressbook_toggle_init()');
		$theme->assign('addressbook','<a href="javascript:void(0)" onClick="apps_mailclient_addressbook_toggle()">Addressbook</a>');
		$theme->assign('addressbook_area_id','apps_mailclient_addressbook');
		$fav2 = array();
		if(ModuleManager::is_installed('CRM/Contacts')>=0) {
			$fav = CRM_ContactsCommon::get_contacts($from_mails?array(':Fav'=>true,'(!email'=>'','|!login'=>''):array(':Fav'=>true,'!login'=>''),array('id','first_name','last_name','company_name'));
			foreach($fav as $v)
				$fav2[$v['id']] = CRM_ContactsCommon::contact_format_default($v,true);
			$rb1 = $this->init_module('Utils/RecordBrowser/RecordPicker');
			$this->display_module($rb1, array('contact' ,'to_addr_ex',array('Apps_MailClientCommon','addressbook_rp_mail'),$from_mails?array('(!email'=>'','|!login'=>''):array('!login'=>''),array('work_phone'=>false,'mobile_phone'=>false,'email'=>true,'login'=>true)));
			$theme->assign('addressbook_add_button',$rb1->create_open_link('Add contact'));
		} else {
			$fav2 = DB::GetAssoc('SELECT id,login FROM user_login');
		}
		$f->addElement('multiselect','to_addr_ex','',$fav2);
		$f->addFormRule(array($this,'check_to_addr'));
		$f->addElement('text','subject',$this->lang->t('Subject'),array('maxlength'=>256));
		$f->addRule('subject',$this->lang->t('Max length of subject is 256 chars'),'maxlength',256);
		$fck = & $f->addElement('fckeditor', 'body', $this->lang->t('Content'));
		$fck->setFCKProps('800','300',true);
		
		//if edit
		$references = false;
		if($id!==null) {
			$message = @file_get_contents(Apps_MailClientCommon::get_mail_dir().$box.'/'.$id);
			if($message!==false) {
				ini_set('include_path',dirname(__FILE__).'/PEAR'.PATH_SEPARATOR.ini_get('include_path'));
				require_once('Mail/mimeDecode.php');
				$decode = new Mail_mimeDecode($message, "\r\n");
				$structure = $decode->decode(array('decode_bodies'=>true,'include_bodies'=>true));
				if(!isset($structure->headers['from']))
					$structure->headers['from'] = '';
				if(!isset($structure->headers['to']))
					$structure->headers['to'] = '';
				if(!isset($structure->headers['date']))
					$structure->headers['date'] = '';
	
				$body = false;
				$body_type = false;
				$body_ctype = false;
				$attachments = array();

				if($structure->ctype_primary=='multipart' && isset($structure->parts)) {
					$parts = $structure->parts;
					for($i=0; $i<count($parts); $i++) {
						$part = $parts[$i];
						if($part->ctype_primary=='multipart' && isset($part->parts))
							$parts = array_merge($parts,$part->parts);
							if($body===false && $part->ctype_primary=='text' && $part->ctype_secondary=='plain' && (!isset($part->disposition) || $part->disposition=='inline')) {
							$body = $part->body;
							$body_type = 'plain';
							$body_ctype = isset($structure->headers['content-type'])?$structure->headers['content-type']:'text/'.$body_type;
						} elseif($part->ctype_primary=='text' && $part->ctype_secondary=='html' && ($body===false || $body_type=='plain') && (!isset($part->disposition) || $part->disposition=='inline')) {
							$body = $part->body;
							$body_type = 'html';
						}
						if(isset($part->ctype_parameters['name'])) {
							if(isset($part->headers['content-id']))
								$attachments[$part->ctype_parameters['name']] = trim($part->headers['content-id'],'><');
							else
								$attachments[$part->ctype_parameters['name']] = true;
						}
					}
				} elseif(isset($structure->body) && $structure->ctype_primary=='text') {
					$body = $structure->body;
					$body_type = $structure->ctype_secondary;
					$body_ctype = isset($structure->headers['content-type'])?$structure->headers['content-type']:'text/'.$body_type;
				}
	
				if($body===false) die('invalid message');
	
				/*$ret_attachments = '';
				if($attachments) {
					foreach($attachments as $name=>$a) {
						if($a===true)
							$ret_attachments .= '<a target="_blank" href="modules/Apps/MailClient/preview.php?'.http_build_query(array_merge($_GET,array('attachment_name'=>$name))).'">'.$name.'</a><br>';
						else
							$ret_attachments .= '<a target="_blank" href="modules/Apps/MailClient/preview.php?'.http_build_query(array_merge($_GET,array('attachment_cid'=>$a))).'">'.$name.'</a><br>';
					}
				}*/

				$to_addr = array();
				$to_addr_ex = array();
				$subject = isset($structure->headers['subject'])?Apps_MailClientCommon::mime_header_decode($structure->headers['subject']):'no subject';
				if($type=='edit' || $type=='edit_as_new') {
					$to_address = Apps_MailClientCommon::mime_header_decode($structure->headers['to']);
					$to_address = explode(',',$to_address);
					foreach($to_address as $v) {
						if(strpos($v,'@')!==false) {
							$to_addr[] = trim($v);
						} elseif(ereg('<([0-9]+)>$',$v,$r)) {
							$to_addr_ex[] = $r[1];
						}
					}
					if(ModuleManager::is_installed('CRM/Contacts')>=0) {
						foreach($to_addr_ex as $k=>$v) {
							$v = CRM_ContactsCommon::get_contact_by_user_id($v);
							if($v===null) {
								unset($to_addr_ex[$k]);
								continue;
							}
							$to_addr_ex[$k] = $v['id'];
							$to_addr = array_filter($to_addr,create_function('$o','return $o!=\''.$v['email'].'\';'));
						}
					} else {
						foreach($to_addr_ex as $k=>$v) {
							$mail = Base_User_LoginCommon::get_mail($v);
							$to_addr = array_filter($to_addr,create_function('$o','return $o!=\''.$mail.'\';'));
						}				
					}
				} elseif($type=='reply') {
					$subject = 'Re: '.$subject;
					if(isset($structure->headers['message-id']))
						$references = $structure->headers['message-id'];
					$to_addr[] = Apps_MailClientCommon::mime_header_decode(isset($structure->headers['reply-to'])?$structure->headers['reply-to']:$structure->headers['from']);
				} elseif($type=='forward') {
					$subject = 'Fwd: '.$subject;
				}
				if($type=='reply' || $type=='forward') {
					$msg_header = "\n\n--------- Original Message ---------\n".
								"Subject: $subject\n".
								"Date: ".Apps_MailClientCommon::mime_header_decode(isset($structure->headers['date'])?$structure->headers['date']:$this->lang->ht('no date header specified'))."\n".
								"From: ".Apps_MailClientCommon::mime_header_decode(isset($structure->headers['from'])?$structure->headers['from']:$this->lang->ht('no from header specified'))."\n".
								"To: ".Apps_MailClientCommon::mime_header_decode(isset($structure->headers['to'])?$structure->headers['to']:$this->lang->ht('no from header specified'))."\n\n";
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
					$body = htmlspecialchars(preg_replace("/(http:\/\/[a-z0-9]+(\.[a-z0-9]+)+(\/[\.a-z0-9]+)*)/i", "<a href='\\1'>\\1</a>", $body));
					$body = '<html>'.
						'<head><meta http-equiv=Content-Type content="'.$body_ctype.'"></head>'.
						'<body><pre>'.$body.'</pre></body></html>';
				} else {
					$body = trim($body);
				}
				$body = preg_replace('/"cid:([^@]+@[^@]+)"/i','"preview.php?'.http_build_query($_GET).'&attachment_cid=$1"',$body);
				$body = preg_replace("/<a([^>]*)>(.*)<\/a>/i", '<a$1 target="_blank">$2</a>', $body);
		
				$f->setDefaults(array('body'=>$body,'subject'=>$subject, 'to_addr'=>$to_addr,'to_addr_ex'=>$to_addr_ex));
			}
		}

		if($f->validate()) {
			$v = $f->exportValues();
			if(!isset($v['to_addr'])) $v['to_addr'] = '';
			$save_folder = 'Drafts';
			$subject = isset($v['subject'])?$v['subject']:'no subject';
			$date = date('D M d H:i:s Y');
			
			if($v['from_addr']!='pm')
				$from = DB::GetRow('SELECT * FROM apps_mailclient_accounts WHERE id=%d',array($v['from_addr']));
			else
				$from=null;
			$to = explode(',',$v['to_addr']);
			$to_epesi = array();
			$to_epesi_names = array();
			if(ModuleManager::is_installed('CRM/Contacts')>=0) {
				$to_addr_ex = CRM_ContactsCommon::get_contacts(array('id'=>$v['to_addr_ex']),array('email','login','first_name','last_name','company_name'));
				foreach($to_addr_ex as $kk) {
					if(isset($kk['login']) && $kk['login']!=='') {
						$where = Base_User_SettingsCommon::get('Apps_MailClient','default_dest_mailbox',$kk['login']);
						if($where=='both' || $where=='pm') {
							$to_epesi[] = $kk['login'];
							$to_epesi_names[$kk['login']] = CRM_ContactsCommon::contact_format_default($kk,true).' <'.$kk['login'].'>';
						}
						if($where=='pm')
							continue;
						if($kk['email']=='') {
							$to[] = Base_User_LoginCommon::get_mail($kk['login']);
							continue;
						}
					}
					if($kk['email'])
						$to[] = $kk['email'];
				}
			} else {
				foreach($v['to_addr_ex'] as $kk) {
					$where = Base_User_SettingsCommon::get('Apps_MailClient','default_dest_mailbox',$kk);
					if($where=='both' || $where=='pm') {
						$to_epesi[] = $kk;
						$to_epesi_names[$kk] = $fav2[$kk].' <'.$kk.'>';
					}
					if($where=='pm')
						continue;
					$to[] = Base_User_LoginCommon::get_mail($kk);
				}
			}
/*			foreach($to as &$t)
				$t = trim($t);*/
			$to = array_map('trim',$to);
			$to = array_unique($to);
			$to = array_filter($to);
				
			$ret = true;
			if(ModuleManager::is_installed('CRM/Contacts')>=0) {
				$my = CRM_ContactsCommon::get_my_record();
				$name = CRM_ContactsCommon::contact_format_default($my,true);
			}
			if(!isset($name))
				$name = Base_UserCommon::get_my_user_login();

			if($v['from_addr']=='pm')
				$to_names = implode(', ',$to_epesi_names);
			else
				$to_names = implode(', ',array_merge($to,$to_epesi_names));

			if($v['action']=='send') {
				$save_folder = 'Sent';			
				//remote delivery
				
				if($v['from_addr']!='pm') {
					$mailer = Base_MailCommon::new_mailer();
					$mailer->From = $from['mail'];
					$mailer->FromName = $name;
					$mailer->Host = $from['smtp_server'];
					$mailer->Mailer = 'smtp';
					$mailer->Username = $from['smtp_login'];
					$mailer->Password = $from['smtp_password'];
					$mailer->SMTPAuth = $from['smtp_auth'];
					foreach($to as $m)
						$mailer->AddAddress($m);
					$mailer->Subject = $v['subject'];
					$mailer->IsHTML(true);
					$mailer->Body = $v['body'];
					$mailer->AltBody = strip_tags($v['body']);
					if($references)
						$mailer->AddCustomHeader('References: '.$references);
					$ret = $mailer->Send();
					if(!$ret) print($mailer->ErrorInfo.'<br>');
					unset($mailer);
				}
						
				//local delivery
				foreach($to_epesi as $e) {
					if(!Apps_MailClientCommon::drop_message(Apps_MailClientCommon::get_mail_dir($e).'internal/Inbox',$v['subject'],$name,$to_names,$date,$v['body']))
						print($this->lang->t('Unable to send message to %s',array($to_epesi_names[$e])).'<br>');
				}
			}
			if($ret) {
				if(Apps_MailClientCommon::drop_message(Apps_MailClientCommon::get_mailbox_dir($from?$from['mail']:'internal').$save_folder,$v['subject'],$from?$from['mail']:$this->lang->ht('private message'),$to_names,$date,$v['body'],true)) {
					if($id!==null && $type=='edit') $this->remove_mail($box,$id);
					return false;
				}
			}
		}
		$f->assign_theme('form', $theme);

		$theme->display('new');
		
		Base_ActionBarCommon::add('save','Save',' href="javascript:void(0)" onClick="$(\'new_mail_action\').value=\'save\';'.addcslashes($f->get_submit_form_js(),'"').'"');
		Base_ActionBarCommon::add('report','Send',' href="javascript:void(0)" onClick="$(\'new_mail_action\').value=\'send\';'.addcslashes($f->get_submit_form_js(),'"').'"');
		Base_ActionBarCommon::add('back','Back',$this->create_back_href());
		
		return true;
	}
	
	public function check_to_addr($f) {
		if(empty($f['to_addr']) && empty($f['to_addr_ex']))
			return array('to_addr'=>$this->lang->t('You must provide at least one recipient email address.'));
		return true;
	}
	
	private function set_open_mail_dir_callbacks(array & $str,$path='') {
		$opened_box = str_replace(array('__at__','__dot__'),array('@','.'),$this->get_module_variable('opened_box'));
		foreach($str as $k=>& $v) {
			$mpath = $path.'/'.$v['name'];
			if($mpath == $opened_box) {
				$v['visible'] = true;
				$v['selected'] = true;
			}
			if(isset($v['sub']) && is_array($v['sub'])) $this->set_open_mail_dir_callbacks($v['sub'],$mpath);
			if($path=='')
				$mpath .= '/Inbox';
			$v['name'] = '<a '.$this->create_callback_href(array($this,'open_mail_dir_callback'),str_replace(array('@','.'),array('__at__','__dot__'),$mpath)).'>'.(isset($v['label'])?$v['label']:$v['name']).'</a>';
		}
	}
	
	public function open_mail_dir_callback($path) {
		$this->set_module_variable('opened_box',$path);
	}
	
	public function remove_mail($box,$id,$quiet=false) {
		$box = trim($box,'/');
		$x = explode('/',$box,2);
		if($x[1]=='Trash') {
			if(Apps_MailClientCommon::remove_msg($x[0],$x[1],$id)) {
				$trashpath = Apps_MailClientCommon::get_mailbox_dir(trim($x[0],'/')).'Trash/.del';

				$in = @fopen($trashpath,'r');
				if($in!==false) {
					$ret = array();
					while (($data = fgetcsv($in, 700)) !== false) {
						$num = count($data);
						if($num!=2 || $data[0]==$id) continue;
						$ret[] = $data;
					}
					fclose($in);
					$out = @fopen($trashpath,'w');
					if($out!==false) {
						foreach($ret as $v)
							fputcsv($out,$v);
						fclose($out);
					}
				}
				if(!$quiet)
					Base_StatusBarCommon::message('Message deleted');
			} else {
				if(!$quiet)
					Base_StatusBarCommon::message('Unable to delete message','error');
			}
		} else {
			$id2 = Apps_MailClientCommon::move_msg($x[0],$x[1],$x[0],'Trash',$id);
			if($id2!==false) {
				$trashpath = Apps_MailClientCommon::get_mailbox_dir(trim($x[0],'/')).'Trash/.del';
				$out = @fopen($trashpath,'a');
				if($out!==false) {
					fputcsv($out,array($id2,$box));
					fclose($out);
				}
				if(!$quiet)
					Base_StatusBarCommon::message('Message moved to trash');
			} else {
				if(!$quiet)
					Base_StatusBarCommon::message('Unable to move message to trash','error');
			}
		}
	}

	////////////////////////////////////////////////////////////
	//account management
	public function account_manager() {
		$gb = $this->init_module('Utils/GenericBrowser',null,'accounts');
		$gb->set_table_columns(array(
			array('name'=>$this->lang->t('Mail'), 'order'=>'mail')
				));
		$ret = $gb->query_order_limit('SELECT id,mail FROM apps_mailclient_accounts WHERE user_login_id='.Acl::get_user(),'SELECT count(mail) FROM apps_mailclient_accounts WHERE user_login_id='.Acl::get_user());
		while($row=$ret->FetchRow()) {
			$r = & $gb->get_new_row();
			$r->add_data($row['mail']);
			$r->add_action($this->create_callback_href(array($this,'account'),array($row['id'],'edit')),'Edit');
//			$r->add_action($this->create_callback_href(array($this,'account'),array($row['id'],'view')),'View');
			$r->add_action($this->create_callback_href(array($this,'delete_account'),$row['id']),'Delete');
		}
		$this->display_module($gb);
		Base_ActionBarCommon::add('add','New account',$this->create_callback_href(array($this,'account'),array(null,'new')));
	}
	
	public function account($id,$action='view') {
		if($this->is_back()) return false;

		$f = $this->init_module('Libs/QuickForm');

		$defaults=null;
		if($action!='new') {
			$ret = DB::Execute('SELECT * FROM apps_mailclient_accounts WHERE id=%d',array($id));
			$defaults = $ret->FetchRow();
		}
		
		$native_support = true;
		if(!function_exists('imap_open')) {
			$native_support = false;
			$methods = array(
					array('auto'=>'Automatic', 'DIGEST-MD5'=>'DIGEST-MD5', 'CRAM-MD5'=>'CRAM-MD5', 'APOP'=>'APOP', 'PLAIN'=>'PLAIN', 'LOGIN'=>'LOGIN', 'USER'=>'USER'),
					array('auto'=>'Automatic', 'DIGEST-MD5'=>'DIGEST-MD5', 'CRAM-MD5'=>'CRAM-MD5', 'LOGIN'=>'LOGIN')
				);
			$methods_js = json_encode($methods);
			eval_js('Event.observe(\'mailclient_incoming_protocol\',\'change\',function(x) {'.
					'var methods = '.$methods_js.';'.
					'var opts = this.form.incoming_method.options;'.
					'opts.length=0;'.
					'$H(methods[this.value]).each(function(x,y) {opts[y] = new Option(x[1],x[0]);});'.
					'if(this.value==0) this.form.pop3_leave_msgs_on_server.disabled=false; else this.form.pop3_leave_msgs_on_server.disabled=true;'.
					'});'.
				'Event.observe(\'mailclient_smtp_auth\',\'change\',function(x) {'.
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
				array('name'=>'header','label'=>$this->lang->t(ucwords($action).' account'),'type'=>'header'),
				array('name'=>'mail','label'=>$this->lang->t('Mail address'),'rule'=>array(array('type'=>'email','message'=>$this->lang->t('This isn\'t valid e-mail address')))),
				array('name'=>'login','label'=>$this->lang->t('Login')),
				array('name'=>'password','label'=>$this->lang->t('Password'),'type'=>'password'),
				
				array('name'=>'in_header','label'=>$this->lang->t('Incoming mail'),'type'=>'header'),
				array('name'=>'incoming_protocol','label'=>$this->lang->t('Incoming protocol'),'type'=>'select','values'=>array(0=>'POP3',1=>'IMAP'), 'default'=>0,'param'=>array('id'=>'mailclient_incoming_protocol')),
				array('name'=>'incoming_server','label'=>$this->lang->t('Incoming server address')),
				array('name'=>'incoming_ssl','label'=>$this->lang->t('Receive with SSL')));
		if(!$native_support)
			$cols[] = array('name'=>'incoming_method','label'=>$this->lang->t('Authorization method'),'type'=>'select','values'=>$methods[(isset($defaults) && $defaults['incoming_protocol'])?1:0], 'default'=>'auto');
		$cols = array_merge($cols,
			array(array('name'=>'pop3_leave_msgs_on_server','label'=>$this->lang->t('Remove messages from server'),'type'=>'select',
					'values'=>array(0=>'immediately',1=>'after 1 day', 3=>'after 3 days', 7=>'after 1 week', 14=>'after 2 weeks', 30=>'after 1 month', -1=>'never'), 
					'default'=>'0','param'=>((isset($defaults) && $defaults['incoming_protocol']) || ($f->getSubmitValue('submited') && $f->getSubmitValue('incoming_protocol')))?array('disabled'=>1):array()),

				array('name'=>'out_header','label'=>$this->lang->t('Outgoing mail'),'type'=>'header'),
				array('name'=>'smtp_server','label'=>$this->lang->t('SMTP server address')),
				array('name'=>'smtp_ssl','label'=>$this->lang->t('Send with SSL')),
				array('name'=>'smtp_auth','label'=>$this->lang->t('SMTP authorization required'),'param'=>array('id'=>'mailclient_smtp_auth')),
				array('name'=>'smtp_login','label'=>$this->lang->t('Login'),'param'=>((isset($defaults) && $defaults['smtp_auth']==0) || ($f->getSubmitValue('submited') && !$f->getSubmitValue('smtp_auth')))?array('disabled'=>1):array()),
				array('name'=>'smtp_password','label'=>$this->lang->t('Password'),'type'=>'password','param'=>((isset($defaults) && $defaults['smtp_auth']==0) || ($f->getSubmitValue('submited') && !$f->getSubmitValue('smtp_auth')))?array('disabled'=>1):array())
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
					if(ereg("header$",$v['name'])) continue;
					if(isset($values[$v['name']]))
						$dbup[$v['name']] = $values[$v['name']];
					else
						$dbup[$v['name']] = 0;
				}
				DB::Replace('apps_mailclient_accounts', $dbup, array('id'), true,true);
				return false;	
			}
			Base_ActionBarCommon::add('save','Save',' href="javascript:void(0)" onClick="'.addcslashes($f->get_submit_form_js(),'"').'"');
		}
		$f->display();

		Base_ActionBarCommon::add('back','Back',$this->create_back_href());

		return true;
	}

	public function delete_account($id){
		if($this->is_back()) return false;
		$f = $this->init_module('Libs/QuickForm');
		$mail = DB::GetOne('SELECT mail FROM apps_mailclient_accounts WHERE id=%d',array($id));
		$f->addElement('header',null,$this->lang->t('Delete account %s',array($mail)));
		$f->addElement('checkbox','setup',$this->lang->t('Account setup'));
		$f->addElement('checkbox','data',$this->lang->t('Emails'));
		$f->setDefaults(array('setup'=>1));

		if($f->validate()) {
			$values = $f->exportValues();
			if(isset($values['setup']) && $values['setup'])
				DB::Execute('DELETE FROM apps_mailclient_accounts WHERE id=%d',array($id));
			if(isset($values['data']) && $values['data'])
				recursive_rmdir($this->get_data_dir().Acl::get_user().'/'.str_replace(array('@','.'),array('__at__','__dot__'),$mail));
			return false;
		}
		$f->display();

		Base_ActionBarCommon::add('back','Back',$this->create_back_href());
		Base_ActionBarCommon::add('delete','Delete',' href="javascript:void(0)" onClick="'.addcslashes($f->get_submit_form_js(),'"').'"');
		
		
		return true;
	}
	

	//////////////////////////////////////////////////////////////////
	//applet	
	public function applet($conf, $opts) {
		$opts['go'] = true;
		$accounts = array();
		$ret = array();
		foreach($conf as $key=>$on) {
			$x = explode('_',$key);
			if($x[0]=='account' && $on) {
				$id = $x[1];
				$mail = DB::GetOne('SELECT mail FROM apps_mailclient_accounts WHERE id=%d',array($id));
				if(!$mail) continue;
				$ret[$mail] = '<span id="mailaccount_'.$id.'"></span>';
				
				//interval execution
				eval_js_once('var mailclientcache = Array();'.
					'mailclientfunc = function(accid,cache){'.
					'if(!$(\'mailaccount_\'+accid)) return;'.
					'if(cache && typeof mailclientcache[accid] != \'undefined\')'.
						'$(\'mailaccount_\'+accid).innerHTML = mailclientcache[accid];'.
					'else '.
						'new Ajax.Updater(\'mailaccount_\'+accid,\'modules/Apps/MailClient/refresh.php\',{'.
							'method:\'post\','.
							'onComplete:function(r){mailclientcache[accid]=r.responseText},'.
							'parameters:{acc_id:accid}});'.
					'}');
				eval_js_once('setInterval(\'mailclientfunc('.$id.' , 0)\',300000)');

				//get rss now!
				eval_js('mailclientfunc('.$id.' , 1)');

			}
		}
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
		
		$form->addElement('header', 'module_header', $this->lang->t('Mail messages setup'));
		$s = array();
		for($i=5; $i<250; $i*=2) {
			$k = $i*1024*1024;
			$s[$k] = filesize_hr($k);
		}
		$form->addElement('select','max_mail_size',$this->lang->t('Max downloaded mail size'), $s);
		
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

	public function caption() {
		return "Mail client";
	}

}

?>