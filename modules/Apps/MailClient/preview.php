<?php
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // date in the past

if(!isset($_GET['msg_id']) || !isset($_GET['mbox']) || !is_numeric($_GET['msg_id']))
	die('Invalid request');

define('CID',false);
require_once('../../../include.php');
session_commit();
ModuleManager::load_modules();

if(!Acl::is_user()) die('Not logged in');

ini_set('include_path',dirname(__FILE__).'/PEAR'.PATH_SEPARATOR.ini_get('include_path'));
require_once('Mail/Mbox.php');
require_once('Mail/mimeDecode.php');

$mbox = new Mail_Mbox(Apps_MailClientCommon::get_mail_dir().ltrim($_GET['mbox'],'/').'.mbox');
if(($ret = $mbox->setTmpDir(Apps_MailClientCommon::Instance()->get_data_dir().'tmp'))===true && ($ret = $mbox->open())===true) {
	register_shutdown_function(array($mbox,'close'));
	
	$message = null;
	if(PEAR::isError($message = $mbox->get($_GET['msg_id']))) {
		Epesi::alert($message->getMessage());
		Epesi::send_output();
		exit();
	}

	$decode = new Mail_mimeDecode($message, "\r\n");
	$structure = $decode->decode(array('decode_bodies'=>true,'include_bodies'=>true));
	if(!isset($structure->headers['from']))
		$structure->headers['from'] = '';
	if(!isset($structure->headers['to']))
		$structure->headers['to'] = '';
	if(!isset($structure->headers['date']))
		$structure->headers['date'] = '';

	
	if(isset($_GET['attachment_cid']) || isset($_GET['attachment_name'])) {
		if(isset($structure->parts)) {
			$parts = $structure->parts;
			for($i=0; $i<count($parts); $i++) {
				$part = $parts[$i];
				if($part->ctype_primary=='multipart' && isset($part->parts))
					$parts = array_merge($parts,$part->parts);
				//if(isset($part->disposition) && $part->disposition=='attachment' && $part->ctype_parameters['name']==$_GET['attachment']) {
				if((isset($part->headers['content-id']) && trim($part->headers['content-id'],'<>')==$_GET['attachment_cid']) || (isset($part->ctype_parameters['name']) && $part->ctype_parameters['name']==$_GET['attachment_name'])) {
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
				//if(isset($part->disposition) && $part->disposition=='attachment')
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
		
		$ret_attachments = '';
		if($attachments) {
			foreach($attachments as $name=>$a) {
				if($a===true)
					$ret_attachments .= '<a target="_blank" href="modules/Apps/MailClient/preview.php?'.http_build_query(array_merge($_GET,array('attachment_name'=>$name))).'">'.$name.'</a><br>';
				else
					$ret_attachments .= '<a target="_blank" href="modules/Apps/MailClient/preview.php?'.http_build_query(array_merge($_GET,array('attachment_cid'=>$a))).'">'.$name.'</a><br>';
			}
		}

		if(ereg('(Sent|Drafts)$',$_GET['mbox']))
			$address = $structure->headers['to'];
		else
			$address = $structure->headers['from'];
		
		$subject = isset($structure->headers['subject'])?Apps_MailClientCommon::mime_header_decode($structure->headers['subject']):'no subject';
		$address = Apps_MailClientCommon::mime_header_decode($address);
		
		$script = 'parent.$(\''.$_GET['pid'].'_subject\').innerHTML=\''.Epesi::escapeJS(htmlentities($subject),false).'\';'.
			'parent.$(\''.$_GET['pid'].'_address\').innerHTML=\''.Epesi::escapeJS(htmlentities($address),false).'\';'.
			'parent.$(\''.$_GET['pid'].'_attachments\').innerHTML=\''.Epesi::escapeJS($ret_attachments,false).'\';';
		
		header("Content-type: text/html");
		if($body_type=='plain') {
			$body = htmlspecialchars(preg_replace("/(http:\/\/[a-z0-9]+(\.[a-z0-9]+)+(\/[\.a-z0-9]+)*)/i", "<a href='\\1'>\\1</a>", $body));
			$body = '<html>'.
				'<head><meta http-equiv=Content-Type content="'.$body_ctype.'"></head>'.
				'<body><pre>'.$body.'</pre></body>';
		} else {
			$body = trim($body);
			if(ereg('</html>$',$body))
				$body = substr($body,0,strlen($body)-7);
		}
		$body = preg_replace('/"cid:([^@]+@[^@]+)"/i','"preview.php?'.http_build_query($_GET).'&attachment_cid=$1"',$body);
		$body = preg_replace("/<a([^>]*)>(.*)<\/a>/is", "<a\\1 target='_blank'>\\2</a>", $body);
		
		$body .= '<script>'.$script.'</script>'.
				'</html>';

		echo $body;//.'<pre>'.htmlentities(print_r($structure,true)).'</pre>';
	}
	Apps_MailClientCommon::read_msg($_GET['mbox'],$_GET['msg_id']);
} else {
	die($ret->getMessage());
}

?>