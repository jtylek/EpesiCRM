<?php
header("Content-type: text/javascript");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // date in the past

if(!isset($_POST['cid']) || !is_numeric($_POST['cid']))
	die('Client id not defined.');

define('CID',false);
define('READ_ONLY_SESSION',true);
define('MAILCLIENT_CACHE',true);
require_once('../../../include.php');
ModuleManager::load_modules();
@set_time_limit(0);
$mail_size_limit = Variable::get('max_mail_size');
ini_set("memory_limit",$mail_size_limit*3+32*1024*1024); // max mail size is

if(!Acl::is_user()) {
	exit();
}

$accounts = DB::GetAll('SELECT * FROM apps_mailclient_accounts WHERE user_login_id=%d AND incoming_protocol<2',array(Acl::get_user()));
if(empty($accounts)) {
	exit();
}
foreach($accounts as $a) {
	if($a['incoming_protocol']) {//imap
		//sync dirs
		Apps_MailClientCommon::imap_sync_mailbox_dir($a['id']);
		//sync inbox(without subdirs) messages
		$local_dirs = Apps_MailClientCommon::get_mailbox_structure($a['id']);
		$inbox = null;
		foreach($local_dirs as $k=>$arr)
			if(strcasecmp($k,'inbox')==0) {
				$inbox = $k;
				break;
			}
		if($inbox) Apps_MailClientCommon::imap_get_new_messages($a['id'],$inbox.'/');
	} else { //pop3
		Apps_MailClientCommon::get_number_of_new_messages_in_inbox($id);
	}
}

?>
