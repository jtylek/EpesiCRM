<?php
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // date in the past
header("Content-Type: text/plain");

if(!isset($_GET['msg_id']) || !isset($_GET['box']) || strpos($_GET['box'],'..')!==false || !is_numeric($_GET['msg_id']))
	die('Invalid request');

define('CID',false);
require_once('../../../include.php');
session_commit();
ModuleManager::load_modules();

if(!Acl::is_user()) die('Not logged in');

$box = Apps_MailClientCommon::get_mail_dir().trim($_GET['box'],'/');
	
$message = @file_get_contents($box.'/'.$_GET['msg_id']);
if($message!==false)
	echo $message;//.'<pre>'.htmlentities(print_r($structure,true)).'</pre>';
else
	echo "Invalid message";
?>
