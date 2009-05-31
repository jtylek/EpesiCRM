<?php
header("Content-type: text/javascript");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // date in the past

define('CID',false);
define('READ_ONLY_SESSION',true);
define('READ_ONLY_SESSION',true);
require_once('../../../include.php');
ModuleManager::load_modules();
@set_time_limit(0);
$mail_size_limit = Variable::get('max_mail_size');
ini_set("memory_limit",$mail_size_limit+32*1024*1024); // max mail size is

if(!Acl::is_user()) die('Not logged in');

$accounts = DB::GetAll('SELECT * FROM apps_mailclient_accounts WHERE user_login_id=%d AND incoming_protocol=1',array(Acl::get_user()));
if(empty($accounts)) {
	print('Apps_MailClient.cache_mailboxes_working=false;'); //we don't need it, turn it off, so it can be turned on
	exit();
}
$refresh = false;
foreach($accounts as $a) {
	//sync dirs
	if(Apps_MailClientCommon::imap_sync_mailbox_dir($a['id']))
		$refresh=true;
	//sync inbox(without subdirs) messages
	if(Apps_MailClientCommon::imap_get_new_messages($a['id'],'Inbox/'))
		$refresh=true;
}

foreach($accounts as $a) {
	//sync all messages (including inbox again)
	if(Apps_MailClientCommon::imap_sync_messages($a['id']))
		$refresh=true;
}

print(($refresh?'Apps_MailClient.refresh_ui();':'').'setTimeout(\'Apps_MailClient.cache_mailboxes()\',30000);');//30s
?>
