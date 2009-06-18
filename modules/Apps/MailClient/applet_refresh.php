<?php
if(!isset($_POST['acc_id']) || !is_numeric($_POST['acc_id']))
	die('Invalid request');

define('CID',false);
define('READ_ONLY_SESSION',true);
require_once('../../../include.php');
ModuleManager::load_modules();
if(!Acl::is_user()) return;

Apps_MailClientCommon::include_path();

$id = $_POST['acc_id'];

$num = Apps_MailClientCommon::get_number_of_new_messages_in_inbox($id);
print(($num===false)?'error':$num);

error_reporting(0);
?>
