<?php
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // date in the past

if(!isset($_GET['id']) || !is_numeric($_GET['id']))
    die('Invalid request');

define('CID',false);
define('READ_ONLY_SESSION',true);
require_once('../../../include.php');
ModuleManager::load_modules();

if(!Acl::is_user()) die('Not logged in');

if (isset($_GET['field']) && $_GET['field']=='headers') {
	$rec = Utils_RecordBrowserCommon::get_record('rc_mails', $_GET['id']);
	$html = Utils_RecordBrowserCommon::get_val('rc_mails', 'headers_data', $rec, false, null);
//	$html = DB::GetOne('SELECT f_headers_data FROM rc_mails_data_1 WHERE id=%d',array($_GET['id']));
} else {
//	$html = Utils_RecordBrowserCommon::get_val('rc_mails', 'body', $rec, false, null);
	$html = DB::GetOne('SELECT f_body FROM rc_mails_data_1 WHERE id=%d',array($_GET['id']));
}

if(!$html) die('Invalid e-mail id.');
$images = DB::GetAssoc('SELECT mime_id,name FROM rc_mails_attachments WHERE mail_id=%d AND attachment=1 AND type '.DB::like().' %s',array($_GET['id'], 'image/%'));
foreach($images as $k=>&$n)
    $n = '<img src="get.php?'.http_build_query(array('mime_id'=>$k,'mail_id'=>$_GET['id'])).'" onload="fix_height();"/><br />';
$html = str_ireplace('<img ','<img onload="fix_height();" ',$html);
$html = str_replace('__MAIL_ID__',$_GET['id'],$html);
$html = preg_replace("/<a([^>]*)>(.*)<\/a>/i", '<a$1 target="_blank">$2</a>', $html);
$html = '<html>'.
        '<head><meta http-equiv=Content-Type content="text/html; charset=utf-8" />'.
        '<script type="text/javascript">function fix_height(){parent.$("rc_mail_body").height = Math.max(document.body.offsetHeight,document.body.scrollHeight)+30;}</script>'.
        '</head><body>'.$html.($images?'<hr />'.implode('<br />',$images):'').
        '<script type="text/javascript">fix_height();</script>'.
        '</body>'.
        '</html>';
print($html);
?>
