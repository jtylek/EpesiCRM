<?php
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // date in the past

if(!isset($_GET['msg_id']) || !isset($_GET['mbox']))
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
	
	if(isset($_GET['attachment'])) {
		if(isset($structure->parts)) {
			$parts = $structure->parts;
			for($i=0; $i<count($parts); $i++) {
				$part = $parts[$i];
				if($part->ctype_primary=='multipart' && isset($part->parts))
					$parts = array_merge($parts,$part->parts);
				//if(isset($part->disposition) && $part->disposition=='attachment' && $part->ctype_parameters['name']==$_GET['attachment']) {
				if(isset($part->ctype_parameters['name']) && $part->ctype_parameters['name']==$_GET['attachment']) {
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
		if(isset($structure->body) && $structure->ctype_primary=='text') {
			$body = $structure->body;
			$body_type = $structure->ctype_secondary;
			$body_ctype = isset($structure->headers['content-type'])?$structure->headers['content-type']:'text/'.$body_type;
		}
		
		if(isset($structure->parts)) {
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
					continue;
				}
				//if(isset($part->disposition) && $part->disposition=='attachment')
				if(isset($part->ctype_parameters['name']))
					$attachments[] = $part->ctype_parameters['name']; //nie moze byc po name, powinno byc po jakims id
			}
		}
		if($body===false) die('invalid message');
		header("Content-type: text/html");
		if($body_type=='plain')
			$body = '<html>'.
				'<head><meta http-equiv=Content-Type content="'.$body_ctype.'"></head>'.
				'<body><pre>'.$body.'</pre></body>'.
				'</html>';
		
		$ret_attachments = '';
		if($attachments) {
			$ret_attachments = '<hr>';
			foreach($attachments as $a)
				$ret_attachments .= '<a href="preview.php?'.http_build_query(array_merge($_GET,array('attachment'=>$a))).'">'.$a.'</a><br>';
		}
		echo $body.$ret_attachments;
	}
	
} else {
	die($ret->getMessage());
}

?>