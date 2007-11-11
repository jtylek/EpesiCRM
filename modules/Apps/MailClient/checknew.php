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
	
	$mbox = new Mail_Mbox(Apps_MailClientCommon::get_mail_dir().$box.'.mbox');
	if(($ret = $mbox->setTmpDir('data/Apps_MailClient/tmp'))===false 
		|| ($ret = $mbox->open())===false) {
		message($account['id'],$account['mail'].': unable to open Inbox file');
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
		continue;
	}

	if(PEAR::isError( $ret= $in->login($user , $pass, $method))) {
		message($account['id'],$account['mail'].': (login error) '.$ret->getMessage());
		continue;
	}

	$num = 0;
	$error = false;
	if($pop3) {
		$l = $in->getListing();
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
				$mbox->delete($msg_id);
				$error = true;
				break;
			}
			$num++;
			echo('<script>parent.Apps_MailClient.progress_bar.set_progress(parent.$(\''.$_GET['id'].'progresses\'),\''.$account['id'].'\', '.ceil($num*100/$count).')</script>');
			flush();
		}
	} else { //imap
	}
	$in->disconnect();
	$mbox->close();
	if(!$error)
		message($account['id'],$account['mail'].': ok, got '.$num.' new messages');
}
echo('<a href="javascript:parent.leightbox_deactivate(\''.$_GET['id'].'\')">hide</a>');
?>