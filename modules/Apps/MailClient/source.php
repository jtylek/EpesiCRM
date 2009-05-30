<?php
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // date in the past
header("Content-Type: text/plain");

if(!isset($_GET['msg_id']) || !isset($_GET['box']) || !is_numeric($_GET['box']) || !isset($_GET['dir']) || strpos($_GET['dir'],'..')!==false || !is_numeric($_GET['msg_id']))
	die('Invalid request');

define('CID',false);
define('READ_ONLY_SESSION',true);
require_once('../../../include.php');
ModuleManager::load_modules();

if(!Acl::is_user()) die('Not logged in');

$box_dir = Apps_MailClientCommon::get_mailbox_dir($_GET['box']);
if($box_dir===false) {
	die('Invalid mailbox');
}
$box = $box_dir.$_GET['dir'];

$message = @file_get_contents($box.$_GET['msg_id']);
if($message!==false)
	echo $message;//.'<pre>'.htmlentities(print_r($structure,true)).'</pre>';
else
	echo "Invalid message";
?>
