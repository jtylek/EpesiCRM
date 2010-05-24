<?php
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // date in the past

if(!isset($_GET['mail_id']) || !is_numeric($_GET['mail_id']) || !isset($_GET['mime_id']) || (!is_numeric($_GET['mime_id']) && strlen($_GET['mime_id'])!=32))
    die('Invalid request');

define('CID',false);
define('READ_ONLY_SESSION',true);
require_once('../../../include.php');
ModuleManager::load_modules();

if(!Acl::is_user()) die('Not logged in');

list($mimetype,$name,$attachment) = DB::GetRow('SELECT type,name,attachment FROM rc_mails_attachments WHERE mail_id=%d AND mime_id=%s',array($_GET['mail_id'],$_GET['mime_id']));

$disposition = $attachment?'attachment':'inline';

$filename = DATA_DIR.'/CRM_Roundcube/attachments/'.$_GET['mail_id'].'/'.$_GET['mime_id'];

if(headers_sent())
    die('Some data has already been output to browser, can\'t send file');

if(!file_exists($filename))
    die('File doesn\'t exists');
$buffer = file_get_contents($filename);
header('Content-Type: '.$mimetype);
header('Content-Length: '.strlen($buffer));
header('Content-disposition: '.$disposition.'; filename="'.$name.'"');
echo $buffer;?>
