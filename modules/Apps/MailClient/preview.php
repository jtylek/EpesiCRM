<?php
if(!isset($_POST['msg_id']) || !isset($_POST['mbox']))
	die('Invalid request');

require_once('../../../include.php');
session_write_close(); //don't messup session
Epesi::init();

if(!Acl::is_user()) die('Not logged in');

ini_set('include_path',dirname(__FILE__).'/PEAR'.PATH_SEPARATOR.ini_get('include_path'));
require_once('Mail/Mbox.php');
require_once('Mail/mimeDecode.php');

$mbox = new Mail_Mbox(Apps_MailClientCommon::get_mail_dir().ltrim($_POST['mbox'],'/').'.mbox');
if(($ret = $mbox->setTmpDir(Apps_MailClientCommon::Instance()->get_data_dir().'tmp'))===true && ($ret = $mbox->open())===true) {
	$message = null;
	if(PEAR::isError($message = $mbox->get($_POST['msg_id'])))
		die($message->getMessage());

	$decode = new Mail_mimeDecode($message, "\r\n");
	$structure = $decode->decode();
	
	print_r($structure);
				
} else {
	print($ret->getMessage());
}
$mbox->close();

?>