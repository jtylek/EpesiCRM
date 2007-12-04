<?php
if(!isset($_POST['acc_id']))
	die('Invalid request');

define('CID',false);
require_once('../../../include.php');
session_commit();
ModuleManager::load_modules();
if(!Acl::is_user()) return;

ini_set('include_path',dirname(__FILE__).'/PEAR'.PATH_SEPARATOR.ini_get('include_path'));

$id = $_POST['acc_id'];
$account = DB::GetRow('SELECT * FROM apps_mailclient_accounts WHERE id=%d AND user_login_id=%d',array($id,Acl::get_user()));
if(!$account) die('No such account');
	
$host = explode(':',$account['incoming_server']);
if(isset($host[1])) $port=$host[1];
	else $port = null;
$host = $host[0];
$user = $account['login'];
$pass = $account['password'];
$ssl = $account['incoming_ssl'];
$method = $account['incoming_method']!='auto'?$account['incoming_method']:null;
$pop3 = ($account['incoming_protocol']==0);

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

if(PEAR::isError( $ret= $in->connect(($ssl?'ssl://':'').$host , $port) ))
	die('(connect error) '.$ret->getMessage());
	
if(PEAR::isError( $ret= $in->login($user , $pass, $method)))
	die('(login error) '.$ret->getMessage());

if($pop3) {
	$l = $in->getListing();
	if($l===false) die('unknown error');
	$box = str_replace(array('@','.'),array('__at__','__dot__'),$account['mail']).'/Inbox';
	$uidls_file = Apps_MailClientCommon::get_mail_dir().$box.'.uilds';
	if($account['pop3_leave_msgs_on_server']!=0 && file_exists($uidls_file)) {
		$uidls = explode("\n",file_get_contents($uidls_file));
		$count = count($l);
		for($k=0; $k<$count; $k++)
			if(in_array($l[$k]['uidl'],$uidls)) unset($l[$k]);
	}
	$num_msgs = count($l);
} else { //imap
	if(PEAR::isError($num_msgs = $in->getNumberOfUnSeenMessages()))
		die('(connection error) '.$num_msgs->getMessage());
}
$in->disconnect();

print($num_msgs);
?>