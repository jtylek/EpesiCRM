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

$rec = Utils_RecordBrowserCommon::get_record('rc_mails', $_GET['mail_id']);
if (!$rec) die('Invalid e-mail id.');

$access_fields = Utils_RecordBrowserCommon::get_access('rc_mails', 'view', $rec);
if (!isset($access_fields['body']) || !$access_fields['body'])
    die('Access forbidden');

[$mimetype, $name, $attachment, $file_id] = DB::GetRow('SELECT type,name,attachment,file_id FROM rc_mails_attachments WHERE mail_id=%d AND mime_id=%s',array($_GET['mail_id'],$_GET['mime_id']));

$disposition = $attachment?'attachment':'inline';
// Lets a caller ask for the other disposition regardless of the stored 'attachment' flag - used
// by the AdminLTE "View e-mail" template's attachment list (CRM_MailCommon::get_attachments_html())
// to offer both a View (inline) and Download (attachment) link for the same file via a leightbox
// prompt, instead of the flag alone always forcing one or the other. Whitelisted, not just
// trusted as-is - this ends up straight in a response header below.
if (isset($_GET['disposition']) && in_array($_GET['disposition'], array('inline', 'attachment'), true))
    $disposition = $_GET['disposition'];

if(headers_sent())
    die('Some data has already been output to browser, can\'t send file');

if($file_id && Utils_FileStorageCommon::file_exists($file_id)) {
    $buffer = Utils_FileStorageCommon::read_content($file_id);
} else { // legacy attachment not yet migrated to Utils_FileStorage
    $filename = DATA_DIR.'/CRM_Mail/attachments/'.$_GET['mail_id'].'/'.$_GET['mime_id'];
    if(!file_exists($filename))
        die('File doesn\'t exists');
    $buffer = file_get_contents($filename);
}
header('Content-Type: '.$mimetype);
header('Content-Length: '.strlen($buffer));
header('Content-disposition: '.$disposition.'; filename="'.$name.'"');
echo $buffer;
?>