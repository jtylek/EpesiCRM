<?php
header("Content-type: text/javascript");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // date in the past

if(!isset($_POST['msg_id']) || !isset($_POST['mbox']) || !isset($_POST['mc_id']))
	die('Invalid request');

define('JS_OUTPUT',1);
define('CID',false);
require_once('../../../include.php');
session_commit();
ModuleManager::load_modules();

if(!Acl::is_user()) die('Not logged in');

ini_set('include_path',dirname(__FILE__).'/PEAR'.PATH_SEPARATOR.ini_get('include_path'));
require_once('Mail/Mbox.php');
require_once('Mail/mimeDecode.php');

$mc_id = $_POST['mc_id'];

$mbox = new Mail_Mbox(Apps_MailClientCommon::get_mail_dir().ltrim($_POST['mbox'],'/').'.mbox');
if(($ret = $mbox->setTmpDir(Apps_MailClientCommon::Instance()->get_data_dir().'tmp'))===true && ($ret = $mbox->open())===true) {
	$message = null;
	if(PEAR::isError($message = $mbox->get($_POST['msg_id']))) {
		Epesi::alert($message->getMessage());
		Epesi::send_output();
		exit();
	}

	$decode = new Mail_mimeDecode($message, "\r\n");
	$structure = $decode->decode(array('decode_bodies'=>true,'include_bodies'=>true));
	
	Epesi::text($structure->body,$mc_id.'body');
	Epesi::text($structure->headers['subject'],$mc_id.'subject');
	Epesi::text($structure->headers['from'],$mc_id.'from');
} else {
	Epesi::alert($ret->getMessage());
}
$mbox->close();

Epesi::send_output();

?>