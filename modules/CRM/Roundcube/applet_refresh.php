<?php
if(!isset($_POST['acc_id']) || !is_numeric($_POST['acc_id']))
	die('Invalid request');

define('CID',false);
define('READ_ONLY_SESSION',true);
require_once('../../../include.php');
ModuleManager::load_modules();
if(!Acl::is_user()) die('not logged');

@set_time_limit(0);

$rec = Utils_RecordBrowserCommon::get_record('rc_accounts',$_POST['acc_id']);
if($rec['epesi_user']!==Acl::get_user()) die('invalid account id');

$port = $rec['security']=='ssl'?993:143;

$mailbox = imap_open('{'.$rec['server'].'/imap/readonly/novalidate-cert'.($rec['security']?'/'.$rec['security']:'').':'.$port.'}INBOX',$rec['login'],$rec['password'],OP_READONLY);
if(!$mailbox) die('connection error');
$check = imap_check($mailbox);
if(!$check) die('error reading messages');
$msgCount = $check->Nmsgs;
$unseen = array();
if($msgCount>=1) {
    $l=imap_fetch_overview($mailbox,'1:'.$msgCount,0);
    if(!$l) die('error reading messages overview');
    foreach($l as $msg) {
        if(isset($msg->seen) && !$msg->seen) {
            $array = imap_mime_header_decode($msg->subject);
            $subject = "";
            foreach ($array as $key => $part) {
                $subject .= $part->text;
            }
        	$unseen[] = htmlspecialchars($msg->from).': <i>'.$subject.'</i>';
        }
    }
}
print(Utils_TooltipCommon::create(count($unseen),implode('<br />',$unseen)));
?>
