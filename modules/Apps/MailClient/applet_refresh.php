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

$new_msgs = Apps_MailClientCommon::get_number_of_new_messages_in_inbox($id);
if(!$new_msgs) {
	$num=false;
} else {
	list($num,$list) = $new_msgs;
}
$listing = '';
if($num) {
	$listing .= '<small>';
	foreach($list as $l) {
		if(!$l) continue;
		$listing .= htmlspecialchars($l['from']).': <i>'.Apps_MailClientCommon::mime_header_decode($l['subject']).'</i><br>';
	}
	$listing .= '</small>';
}
print(($num===false)?'error':Utils_TooltipCommon::create($num,$listing));
error_reporting(0);
?>
