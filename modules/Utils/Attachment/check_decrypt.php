<?php
if(!isset($_REQUEST['cid']) || !isset($_REQUEST['id']) || !isset($_REQUEST['pass']))
    die('Invalid usage');
$cid = $_REQUEST['cid'];
$id = $_REQUEST['id'];
$pass = $_REQUEST['pass'];

define('CID', $cid);
define('READ_ONLY_SESSION',false);
require_once('../../../include.php');
ModuleManager::load_modules();

$row = Utils_RecordBrowserCommon::get_record('utils_attachment',$id);
if(!Utils_RecordBrowserCommon::get_access('utils_attachment','view',$row)) die(json_encode(array('error'=>__('Access denied'))));

$decoded = Utils_AttachmentCommon::decrypt($row['note'],$pass);
if($decoded!==false) {
    $_SESSION['client']['cp'.$row['id']] = $pass;
    ob_start();
    $note = Utils_AttachmentCommon::display_note($row,false, null, true);
    $note = ob_get_clean().$note;
    die(json_encode(array(
        'note'=>$note,
        'js'=>Epesi::get_output()
    )));
}
die(json_encode(array('error'=>__('Invalid password'))));