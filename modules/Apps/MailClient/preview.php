<?php
header("Content-type: text/javascript");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // date in the past

if(!isset($_POST['msg_id']) || !isset($_POST['mbox']) || !isset($_POST['mc_id']))
	die('Invalid request');

define('JS_OUTPUT',1);
require_once('../../../include.php');
Epesi::init();
session_write_close(); //don't messup session

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
	
//	print_r($structure);
	Epesi::text($structure->body,$mc_id);
} else {
	Epesi::alert($ret->getMessage());
}
$mbox->close();

Epesi::send_output();

?>