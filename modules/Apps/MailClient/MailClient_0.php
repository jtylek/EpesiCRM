<?php
/**
 * Simple mail client
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package apps-mail
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

ini_set('include_path',dirname(__FILE__).'/PEAR'.PATH_SEPARATOR.ini_get('include_path'));
require_once('Mail/Mbox.php');
require_once('Mail/mimeDecode.php');

class Apps_MailClient extends Module {
	private $lang;
	
	public function construct() {
		$this->lang = $this->init_module('Base/Lang');
	}
	
	public function body() {
		$def_mbox = Apps_MailClientCommon::get_default_mbox();
		if($def_mbox===null) {
			print($this->lang->t('No mailboxes defined'));
			return;
		}

		$mbox_file = $this->get_module_variable('opened_mbox',$def_mbox);
		$preview_id = $this->get_path().'preview';

		$th = $this->init_module('Base/Theme');
		$tree = $this->init_module('Utils/Tree');
		$str = Apps_MailClientCommon::get_mail_dir_structure();
		$this->set_open_mail_dir_callbacks($str);
		$tree->set_structure($str);
		$tree->sort();
		$th->assign('tree', $this->get_html_of_module($tree));
		
		$mbox = new Mail_Mbox(Apps_MailClientCommon::get_mail_dir().ltrim($mbox_file,'/').'.mbox');
		if(($ret = $mbox->setTmpDir($this->get_data_dir().'tmp'))===true && ($ret = $mbox->open())===true) {
			$gb = $this->init_module('Utils/GenericBrowser',null,'list');
			$gb->set_table_columns(array(
				array('name'=>$this->lang->t('Subject')),
				array('name'=>$this->lang->t('From')),
				array('name'=>$this->lang->t('Date')),
				array('name'=>$this->lang->t('Size'))
				));
		
			$limit_max = $mbox->size();
			$limit = $gb->get_limit($limit_max);
			$limit_max2 = $limit['offset']+$limit['numrows'];
			if($limit_max2>$limit_max) $limit_max2=$limit_max;
		
			$message = null;
		
			for ($n = $limit['offset']; $n < $limit_max2; $n++) {
				if(PEAR::isError($message = $mbox->get($n))) {
					$limit_max2 +=1;
					if($limit_max2>$limit_max) $limit_max2=$limit_max;
					continue;
				}
				$decode = new Mail_mimeDecode($message, "\r\n");
				$structure = $decode->decode();
			
				$r = $gb->get_new_row();
				$r->add_data($structure->headers['subject'],$structure->headers['from'],$structure->headers['date'],strlen($message));
				$r->add_action('href="javascript:void(0)" onClick="new Ajax.Request(\''.$this->get_module_dir().'preview.php\',{'.
					'method:\'post\','.
					'parameters:{'.
						'\'mbox\':\''.Epesi::escapeJS($mbox_file).'\','.
						'\'msg_id\':\''.$n.'\','.
						'\'mc_id\':\''.$preview_id.'\''.
					'}})"','View');
			}
		
		} else {
			print($ret->getMessage());
			return;
		}
		$mbox->close();

		$th->assign('list', $this->get_html_of_module($gb));
		
		$th->assign('preview',array(
			'subject'=>'<span id="'.$preview_id.'subject"></span>',
			'from'=>'<span id="'.$preview_id.'from"></span>',
			'body'=>'<span id="'.$preview_id.'body"></span>'));
		$th->display();
		
		Base_ActionBarCommon::add('folder',$this->lang->t('Check'),$this->create_callback_href(array($this,'check_mail')));
	}
	
	public function check_mail() {
		$accounts = DB::GetAll('SELECT * FROM apps_mailclient_accounts WHERE user_login_id=%d',array(Base_UserCommon::get_my_user_id()));
		foreach($accounts as $account) {
			$host = explode(':',$account['incoming_server']);
			if(isset($host[1])) $port=$host[1];
				else $port = null;
			$host = $host[0];
			$user = $account['login'];
			$pass = $account['password'];
			$ssl = $account['incoming_ssl'];
			$method = $account['incoming_method']!='auto'?$account['incoming_method']:null;
			$pop3 = ($account['incoming_protocol']==0);

			$mbox = new Mail_Mbox(Apps_MailClientCommon::get_mail_dir().str_replace(array('@','.'),array('__at__','__dot__'),$account['mail']).'/Inbox.mbox');
			if(($ret = $mbox->setTmpDir($this->get_data_dir().'tmp'))===false 
				|| ($ret = $mbox->open())===false) {
				Base_StatusBarCommon::message($account['mail'].' - unable to open Inbox file');
				continue;	
			}

			if($pop3) { //pop3
				require_once('Net/POP3.php');
				$in = new Net_POP3();
	
				if($port==null) {
					if($ssl) $port=995;
					else $port=110;
				}
			} else { //imap
				require_once('Net/IMAP.php');
				if($port==null) {
					if($ssl) $port=993;
					else $port=143;
				}
				$in = new Net_IMAP();
			}

			if(PEAR::isError( $ret= $in->connect(($ssl?'ssl://':'').$host , $port) )) {
				Base_StatusBarCommon::message($account['mail'].' - (connect error) '.$ret->getMessage());
				continue;
			}
	
			if(PEAR::isError( $ret= $in->login($user , $pass, $method))) {
				Base_StatusBarCommon::message($account['mail'].' - (login error) '.$ret->getMessage());
				continue;
			}

			$num = 0;
			if($pop3) {
				$l = $in->getListing();
				foreach($l as $msgl) {
					$mbox->insert("From - ".date('D M d H:i:s Y')."\n".$in->getMsg($msgl['msg_id']));
					$num++;
				}
			} else { //imap
			}
			$in->disconnect();
			$mbox->close();
			Base_StatusBarCommon::message($account['mail'].' - ok, got '.$num.' messages ');
		}
		return false;
	}
	
	private function set_open_mail_dir_callbacks(array & $str,$path='') {
		$opened_mbox = str_replace(array('__at__','__dot__'),array('@','.'),$this->get_module_variable('opened_mbox'));
		foreach($str as $k=>& $v) {
			$mpath = $path.'/'.$v['name'];
			if($mpath == $opened_mbox) {
				$v['visible'] = true;
				$v['selected'] = true;
			}
			if(isset($v['sub']) && is_array($v['sub'])) $this->set_open_mail_dir_callbacks($v['sub'],$mpath);
			if($path=='')
				$mpath .= '/Inbox';
			$v['name'] = '<a '.$this->create_callback_href(array($this,'open_mail_dir_callback'),str_replace(array('@','.'),array('__at__','__dot__'),$mpath)).'>'.$v['name'].'</a>';
		}
	}
	
	public function open_mail_dir_callback($path) {
		$this->set_module_variable('opened_mbox',$path);
	}

	////////////////////////////////////////////////////////////
	//account management
	public function account_manager() {
		$gb = $this->init_module('Utils/GenericBrowser',null,'accounts');
		$ret = $gb->query_order_limit('SELECT id,mail FROM apps_mailclient_accounts WHERE user_login_id='.Base_UserCommon::get_my_user_id(),'SELECT count(mail) FROM apps_mailclient_accounts WHERE user_login_id='.Base_UserCommon::get_my_user_id());
		$gb->set_table_columns(array(
			array('name'=>$this->lang->t('Mail'), 'order'=>'mail')
				));
		while($row=$ret->FetchRow()) {
			$r = & $gb->get_new_row();
			$r->add_data($row['mail']);
			$r->add_action($this->create_callback_href(array($this,'account'),array($row['id'],'edit')),'Edit');
			$r->add_action($this->create_callback_href(array($this,'account'),array($row['id'],'view')),'View');
			$r->add_action($this->create_confirm_callback_href($this->lang->ht('Are you sure?'),array($this,'delete_account'),$row['id']),'Delete');
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
				'})');

		$cols = array(
				array('name'=>'header','label'=>$this->lang->t(ucwords($action).' account'),'type'=>'header'),
				array('name'=>'mail','label'=>$this->lang->t('Mail address'),'rule'=>array(array('type'=>'email','message'=>$this->lang->t('This isn\'t valid e-mail address')))),
				array('name'=>'login','label'=>$this->lang->t('Login')),
				array('name'=>'password','label'=>$this->lang->t('Password'),'type'=>'password'),
				
				array('name'=>'in_header','label'=>$this->lang->t('Incoming mail'),'type'=>'header'),
				array('name'=>'incoming_protocol','label'=>$this->lang->t('Incoming protocol'),'type'=>'select','values'=>array(0=>'POP3',1=>'IMAP'), 'default'=>0,'param'=>array('id'=>'mailclient_incoming_protocol')),
				array('name'=>'incoming_server','label'=>$this->lang->t('Incoming server address')),
				array('name'=>'incoming_ssl','label'=>$this->lang->t('Receive with SSL')),
				array('name'=>'incoming_method','label'=>$this->lang->t('Authorization method'),'type'=>'select','values'=>$methods[(isset($defaults) && $defaults['incoming_protocol'])?1:0], 'default'=>'auto'),

				array('name'=>'out_header','label'=>$this->lang->t('Outgoing mail'),'type'=>'header'),
				array('name'=>'smtp_server','label'=>$this->lang->t('SMTP server address')),
				array('name'=>'smtp_auth','label'=>$this->lang->t('SMTP authorization required')),
				array('name'=>'smtp_ssl','label'=>$this->lang->t('Send with SSL'))
			);
		
		$f->add_table('apps_mailclient_accounts',$cols);
		$f->setDefaults($defaults);
		
		if($action=='view') {
			Base_ActionBarCommon::add('edit','Edit',$this->create_callback_href(array($this,'account'),array($id,'edit')));
			$f->freeze();
		} else {
			$f->addElement('submit',null,'Save','style="display:none"'); //provide on ENTER submit event
			if($f->validate()) {
				$values = $f->exportValues();
				$dbup = array('id'=>$id, 'user_login_id'=>Base_UserCommon::get_my_user_id());
				foreach($cols as $v) {
					if(ereg("header$",$v['name'])) continue;
					if(isset($values[$v['name']]))
						$dbup[$v['name']] = DB::qstr($values[$v['name']]);
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
		DB::Execute('DELETE FROM apps_mailclient_accounts WHERE id=%d',array($id));
	}
	

	//////////////////////////////////////////////////////////////////
	//applet	
	public function applet($conf) {
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
				eval_js_once('setInterval(\'mailclientfunc('.$id.' , 0)\',600002)');

				//get rss now!
				eval_js('mailclientfunc('.$id.' , 1)');

			}
		}
		$th = $this->init_module('Base/Theme');
		$th->assign('accounts',$ret);
		$th->display('applet');
	}
}

?>