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

$mailbox = @imap_open('{'.$rec['server'].'/imap/readonly/novalidate-cert'.($rec['security']?'/'.$rec['security']:'').':'.$port.'}',$rec['login'],$rec['password'],OP_READONLY || OP_SILENT);
$err = imap_errors();
if(!$mailbox || $err) die(Utils_TooltipCommon::create(__('Connection error'), $err, false));
$uns = @imap_search($mailbox,'UNSEEN ALL');
$unseen = array();
if($uns) {
    $l=@imap_fetch_overview($mailbox,implode(',',$uns),0);
    $err = imap_errors();
    if(!$l || $err) die('error reading messages overview');
    foreach($l as $msg) {
        $from = isset($msg->from) ? $msg->from : '<unknown>';
        $subject = isset($msg->subject) ? $msg->subject : '<no subject>';
        $unseen[] = htmlspecialchars(imap_utf8($from)).': <i>'.htmlspecialchars(imap_utf8($subject)).'</i>';
    }
}
print(Utils_TooltipCommon::create(count($unseen),implode('<br />',$unseen)));

$err = imap_errors();
print_r('<span style="display:none;">'.$err.'</span>');

?>
