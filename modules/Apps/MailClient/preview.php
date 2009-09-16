<?php
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // date in the past

if(!isset($_GET['msg_id']) || !isset($_GET['box']) || !is_numeric($_GET['box']) || !isset($_GET['dir']) || strpos($_GET['dir'],'..')!==false || !is_numeric($_GET['msg_id']))
	die('Invalid request');

define('CID',false);
define('READ_ONLY_SESSION',true);
require_once('../../../include.php');
ModuleManager::load_modules();

if(!Acl::is_user()) die('Not logged in');

$structure = Apps_MailClientCommon::get_message_structure($_GET['box'],$_GET['dir'],$_GET['msg_id']);

if($structure===false) {
	$err = 'Invalid message';
	header("Content-type: text/html");
	$script = 'parent.$(\''.$_GET['pid'].'_subject\').innerHTML=\''.Epesi::escapeJS(htmlspecialchars($err),false).'\';'.
			'parent.$(\''.$_GET['pid'].'_address\').innerHTML=\''.Epesi::escapeJS(htmlspecialchars($err),false).'\';'.
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

	if(preg_match('/^(Sent|Drafts)/',$_GET['dir']))
		$address = $structure->headers['to'];
	else
		$address = $structure->headers['from'];

	$subject = Apps_MailClientCommon::mime_header_decode($msg['subject']); //it's in utf
	$address = Apps_MailClientCommon::mime_header_decode($address); //it's in utf
	
	//convert to utf-8

	$script = 'parent.$(\''.$_GET['pid'].'_subject\').innerHTML=\''.Epesi::escapeJS(htmlspecialchars($subject),false).'\';'.
			'parent.$(\''.$_GET['pid'].'_address\').innerHTML=\''.Epesi::escapeJS(htmlspecialchars($address),false).'\';'.
			'parent.$(\''.$_GET['pid'].'_attachments\').innerHTML=\''.Epesi::escapeJS($ret_attachments,false).'\';'.
			'parent.$("mail_view_body").height = Math.max(document.body.offsetHeight,document.body.scrollHeight)+30;';

	header("Content-type: text/html");
	if($body_type=='plain') {
		if(preg_match("/charset=([a-z0-9\-]+)/i",$body_ctype,$reqs)) {
			$charset = $reqs[1];
			$body_ctype = "text/plain; charset=utf-8";
			$body = iconv($charset,'UTF-8',$body);
		}
		$body = preg_replace("/(http:\/\/[a-z0-9]+(\.[a-z0-9]+)+(\/[\.a-z0-9?%=&;]+)*)/i", "<a href='\\1' target=\"_blank\">\\1</a>", htmlspecialchars($body));
		$body = '<html>'.
			'<head><meta http-equiv="Content-Type" content="'.$body_ctype.'"></head>'.
			'<body><pre>'.$body.'</pre></body>';
	} else {
		$body = trim($body);
		if(preg_match('/^<html>/i',$body))
			$body = substr($body,6);
		if(preg_match('/<\/html>$/i',$body))
			$body = substr($body,0,strlen($body)-7);
		if(!preg_match('/<\/body>$/i',$body) && !preg_match('/<body>/i',$body))
			$body = '<body>'.$body.'</body>';
		$body = '<html>'.
			'<head><meta http-equiv=Content-Type content="'.$body_ctype.'"></head>'.$body;
	}
	$body = preg_replace('/"cid:(.+)"/i','"preview.php?'.http_build_query($_GET).'&attachment_cid=$1"',$body);
	$body = preg_replace("/<a([^>]*)>(.*)<\/a>/i", '<a$1 target="_blank">$2</a>', $body);

	$body .= '<script>'.$script.'</script>'.
			'</html>';

	echo Utils_BBCodeCommon::parse($body);//.'<pre>'.htmlentities(print_r($structure,true)).'</pre>';
}
Apps_MailClientCommon::read_msg($_GET['box'],$_GET['dir'],$_GET['msg_id']);

?>
