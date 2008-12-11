<?php
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // date in the past

if(!isset($_GET['msg_id']) || !isset($_GET['box']) || !is_numeric($_GET['box']) || !isset($_GET['dir']) || strpos($_GET['dir'],'..')!==false || !is_numeric($_GET['msg_id']))
	die('Invalid request');

define('CID',false);
require_once('../../../include.php');
session_commit();
ModuleManager::load_modules();

if(!Acl::is_user()) die('Not logged in');

$structure = Apps_MailClientCommon::get_message_structure($_GET['box'],$_GET['dir'],$_GET['msg_id']);

if($structure===false) {
	$err = 'Invalid message';
	header("Content-type: text/html");
	$script = 'parent.$(\''.$_GET['pid'].'_subject\').innerHTML=\''.Epesi::escapeJS(htmlentities($err),false).'\';'.
			'parent.$(\''.$_GET['pid'].'_address\').innerHTML=\''.Epesi::escapeJS(htmlentities($err),false).'\';'.
			'parent.$(\''.$_GET['pid'].'_attachments\').innerHTML=\''.Epesi::escapeJS('',false).'\';';

	$body = '<html>'.
			'<body>'.$err.'</body>'.
			'<script>'.$script.'</script>'.
			'</html>';
	print($body);
	exit();
}


if(isset($_GET['attachment_cid']) || isset($_GET['attachment_name'])) {
	if(isset($structure->parts)) {
		$parts = $structure->parts;
		for($i=0; $i<count($parts); $i++) {
			$part = $parts[$i];
			if($part->ctype_primary=='multipart' && isset($part->parts))
				$parts = array_merge($parts,$part->parts);
			//if(isset($part->disposition) && $part->disposition=='attachment' && $part->ctype_parameters['name']==$_GET['attachment']) {
			if((isset($_GET['attachment_cid']) && isset($part->headers['content-id']) && trim($part->headers['content-id'],'<>')==$_GET['attachment_cid']) || (isset($_GET['attachment_name']) && isset($part->ctype_parameters['name']) && $part->ctype_parameters['name']==$_GET['attachment_name'])) {
				if(isset($part->headers['content-type']))
					header('Content-Type: '.$part->headers['content-type']);
				if(isset($part->headers['content-dispositon']))
					header('Content-disposotion: '.$part->headers['content-disposition']);
				echo $part->body;
				exit();
			}
		}
	}
	die('Invalid attachment');
} else {
	$msg = Apps_MailClientCommon::parse_message_structure($structure,false);
	$body = $msg['body'];
	$body_type = $msg['type'];
	$body_ctype = $msg['ctype'];
	$attachments = $msg['attachments'];

	if($body===false) die('invalid message');

	$ret_attachments = '';
	if($attachments) {
		foreach($attachments as $name=>$a) {
			if($a==='')
				$ret_attachments .= '<a target="_blank" href="modules/Apps/MailClient/preview.php?'.http_build_query(array_merge($_GET,array('attachment_name'=>$name))).'">'.$name.'</a><br>';
			else
				$ret_attachments .= '<a target="_blank" href="modules/Apps/MailClient/preview.php?'.http_build_query(array_merge($_GET,array('attachment_cid'=>$a))).'">'.$name.'</a><br>';
		}
	}

	if(ereg('^(Sent|Drafts)',$_GET['dir']))
		$address = $structure->headers['to'];
	else
		$address = $structure->headers['from'];

	$subject = $msg['subject'];
	$address = Apps_MailClientCommon::mime_header_decode($address);

	$script = 'parent.$(\''.$_GET['pid'].'_subject\').innerHTML=\''.Epesi::escapeJS(htmlentities($subject),false).'\';'.
			'parent.$(\''.$_GET['pid'].'_address\').innerHTML=\''.Epesi::escapeJS(htmlentities($address),false).'\';'.
			'parent.$(\''.$_GET['pid'].'_attachments\').innerHTML=\''.Epesi::escapeJS($ret_attachments,false).'\';';

	header("Content-type: text/html");
	if($body_type=='plain') {
		$body = htmlspecialchars(preg_replace("/(http:\/\/[a-z0-9]+(\.[a-z0-9]+)+(\/[\.a-z0-9]+)*)/i", "<a href='\\1' target=\"_blank\">\\1</a>", $body));
		$body = '<html>'.
			'<head><meta http-equiv=Content-Type content="'.$body_ctype.'"></head>'.
			'<body><pre>'.$body.'</pre></body>';
	} else {
		$body = trim($body);
		if(ereg('^<html>',$body))
			$body = substr($body,6);
		if(ereg('</html>$',$body))
			$body = substr($body,0,strlen($body)-7);
		$body = '<html>'.
			'<head><meta http-equiv=Content-Type content="'.$body_ctype.'"></head>'.$body;
	}
	$body = preg_replace('/"cid:([^@]+@[^@]+)"/i','"preview.php?'.http_build_query($_GET).'&attachment_cid=$1"',$body);
	$body = preg_replace("/<a([^>]*)>(.*)<\/a>/i", '<a$1 target="_blank">$2</a>', $body);

	$body .= '<script>'.$script.'</script>'.
			'</html>';

	echo Utils_BBCodeCommon::parse($body);//.'<pre>'.htmlentities(print_r($structure,true)).'</pre>';
}
Apps_MailClientCommon::read_msg($_GET['box'],$_GET['dir'],$_GET['msg_id']);

?>
