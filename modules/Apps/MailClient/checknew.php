<?php
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // date in the past
if(!isset($_GET['id']))
	die('Invalid request');

define('CID',false);
require_once('../../../include.php');
session_commit();
ModuleManager::load_modules();
set_time_limit(0);

if(!Acl::is_user()) die('Not logged in');

ini_set('include_path',dirname(__FILE__).'/PEAR'.PATH_SEPARATOR.ini_get('include_path'));
require_once('Mail/Mbox.php');
require_once('Mail/mimeDecode.php');

function message($id,$text) {
	echo('<script>parent.Apps_MailClient.progress_bar.set_text(parent.$(\''.$_GET['id'].'progresses\'),\''.$id.'\',\''.Epesi::escapeJS($text,false).'\')</script>');
	flush();
}

function rm_lock($lock) {
	@unlink(dirname(dirname(dirname(__FILE__))).'/'.$lock);
}

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
	
	$box = str_replace(array('@','.'),array('__at__','__dot__'),$account['mail']).'/Inbox';
	
	//check if mbox is not locked
	$lock = Apps_MailClientCommon::get_mail_dir().$box.'.lock';
	if(file_exists($lock)) {
		message($account['id'],$account['mail'].': mailbox locked');
		continue;	
	}
	file_put_contents($lock,'');
	register_shutdown_function('rm_lock',$lock); //be sure that lock was deleted
	
	//open mbox
	$mbox = new Mail_Mbox(Apps_MailClientCommon::get_mail_dir().$box.'.mbox');
	if(($ret = $mbox->setTmpDir('data/Apps_MailClient/tmp'))===false 
		|| ($ret = $mbox->open())===false) {
		message($account['id'],$account['mail'].': unable to open Inbox file');
		unlink($lock);
		continue;	
	}

	message($account['id'],$account['mail'].': login');

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
		message($account['id'],$account['mail'].': (connect error) '.$ret->getMessage());
		unlink($lock);
		continue;
	}

	if(PEAR::isError( $ret= $in->login($user , $pass, $method))) {
		message($account['id'],$account['mail'].': (login error) '.$ret->getMessage());
		unlink($lock);
		continue;
	}

	$num = 0;
	$error = false;
	if($pop3) {
		$l = $in->getListing();
		//check uidls and unset already downloaded messages
		$uidls_file = Apps_MailClientCommon::get_mail_dir().$box.'.uilds';
		if($account['pop3_leave_msgs_on_server']>0 && file_exists($uidls_file)) {
			$uidls = explode("\n",file_get_contents($uidls_file));
			$count = count($l);
			for($k=0; $k<$count; $k++)
				if(in_array($l[$k]['uidl'],$uidls)) unset($l[$k]);
		} else $uidls=array();
		$count = count($l);
		foreach($l as $msgl) {
			message($account['id'],$account['mail'].': getting message '.$num.' of '.$count);
			$msg = $in->getMsg($msgl['msg_id']);
			$msg_id = $mbox->size();
			$mbox->insert("From - ".date('D M d H:i:s Y')."\n".$msg);
			$decode = new Mail_mimeDecode($msg, "\r\n");
			$structure = $decode->decode();
			if(!Apps_MailClientCommon::append_msg_to_index($box,$msg_id,$structure->headers['subject'],$structure->headers['from'],$structure->headers['date'],strlen($msg))) {
				message($account['id'],$account['mail'].': broken index file');
				$mbox->remove($msg_id);
				$error = true;
				break;
			}
			$num++;
			$uidls[] = $msgl['uidl'];
			echo('<script>parent.Apps_MailClient.progress_bar.set_progress(parent.$(\''.$_GET['id'].'progresses\'),\''.$account['id'].'\', '.ceil($num*100/$count).')</script>');
			flush();
		}
		if($account['pop3_leave_msgs_on_server']>0)
			file_put_contents($uidls_file,implode("\n",$uidls));
	} else { //imap
	}
	$in->disconnect();
	$mbox->close();
	if(!$error)
		message($account['id'],$account['mail'].': ok, got '.$num.' new messages');
	unlink($lock);
}
echo('<a href="javascript:parent.$(\''.$_GET['id'].'X\').src=\'\';parent.leightbox_deactivate(\''.$_GET['id'].'\')">hide</a>');
?>