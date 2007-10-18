<?php
if(!isset($_POST['acc_id']))
	die('Invalid request');

require_once('../../../include.php');
session_write_close(); //don't messup session
Epesi::init();
if(!Acl::is_user()) return;

ini_set('include_path',dirname(__FILE__).'/PEAR'.PATH_SEPARATOR.ini_get('include_path'));

$id = $_POST['acc_id'];
$account = DB::GetAll('SELECT * FROM apps_mailclient_accounts WHERE id=%d AND user_login_id=%d',array($id,Base_UserCommon::get_my_user_id()));
if(!$account) continue;
$account = $account[0];
	
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
	$num_msgs = $in->numMsg();
	if($num_msgs===false) die('unknown error');
//	does anybody know how to handle leave on server messages?
//	print('<pre>');
//	print_r($in->getListing());
//	for($i=1; $i<=$num_msgs; $i++) {
//		$headers = $in->getParsedHeaders(1);
//		print_r($headers);
//	}
//	print('</pre>');
} else { //imap
	if(PEAR::isError($num_msgs = $in->getNumberOfUnSeenMessages()))
		die('(connection error) '.$num_msgs->getMessage());
}
$in->disconnect();

print($num_msgs);
?>