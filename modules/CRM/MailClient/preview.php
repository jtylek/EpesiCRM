<?php
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // date in the past

if(!isset($_GET['msg_id']) || !is_numeric($_GET['msg_id']))
	die('Invalid request');

define('CID',false);
define('READ_ONLY_SESSION',true);
require_once('../../../include.php');
ModuleManager::load_modules();

if(!Acl::is_user()) die('Not logged in');

$msg = DB::GetRow('SELECT * FROM crm_mailclient_mails WHERE id=%d',array($_GET['msg_id']));

if(!$msg) {
	$err = 'Invalid message';
	header("Content-type: text/html");
	$body = '<html>'.
			'<body>'.$err.'</body>'.
			'</html>';
	print($body);
	exit();
}

$attach = true;
if(isset($_GET['attachment_cid']))
	$attach = DB::GetRow('SELECT * FROM crm_mailclient_attachments WHERE mail_id=%d AND cid=%s',array($_GET['msg_id'],$_GET['attachment_cid']));
elseif(isset($_GET['attachment_name']))
	$attach = DB::GetRow('SELECT * FROM crm_mailclient_attachments WHERE mail_id=%d AND name=%s',array($_GET['msg_id'],$_GET['attachment_name']));
if($attach!==true) {
	if(!$attach)
		die('Invalid attachment');
	header('Content-Type: '.$attach['type']);
	header('Content-disposotion: '.$attach['disposition']);
	print(file_get_contents('data/CRM_MailClient/'.$attach['id']));
	exit();
} else {
	$body = $msg['body'];
	$body_type = $msg['body_type'];
	$body_ctype = $msg['body_ctype'];

	header("Content-type: text/html");
	if($body_type=='plain') {
		$body = htmlspecialchars(preg_replace("/(http:\/\/[a-z0-9]+(\.[a-z0-9]+)+(\/[\.a-z0-9]+)*)/i", "<a href='\\1' target=\"_blank\">\\1</a>", $body));
		$body = '<html>'.
			'<head><meta http-equiv=Content-Type content="'.$body_ctype.'"></head>'.
			'<body><pre>'.$body.'</pre></body>';
	} else {
		$body = trim($body);
		if(preg_match('/^<html>/i',$body))
			$body = substr($body,6);
		if(preg_match('/<\/html>$/i',$body))
			$body = substr($body,0,strlen($body)-7);
		$body = '<html>'.
			'<head><meta http-equiv=Content-Type content="'.$body_ctype.'"></head>'.$body;
	}
	$body = preg_replace('/"cid:([^@]+@[^@]+)"/i','"preview.php?'.http_build_query($_GET).'&attachment_cid=$1"',$body);
	$body = preg_replace("/<a([^>]*)>(.*)<\/a>/i", '<a$1 target="_blank">$2</a>', $body);

	$body .= '<script>'.
			'parent.$("crm_mailclient_view").height = Math.max(document.body.offsetHeight,document.body.scrollHeight)+30;'.
			'</script>'.
			'</html>';

	echo Utils_BBCodeCommon::parse($body);//.'<pre>'.htmlentities(print_r($structure,true)).'</pre>';
}

?>