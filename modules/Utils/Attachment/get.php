<?php
if(!isset($_REQUEST['cid']) || !isset($_REQUEST['original']) || !isset($_REQUEST['path']) || !isset($_REQUEST['filename'])) die('Invalid usage');
$cid = $_REQUEST['cid'];
$path = $_REQUEST['path'];
$filename = $_REQUEST['filename'];
$original = $_REQUEST['original'];

define('CID', $cid);
require_once('../../../include.php');

$allow = Module::static_get_module_variable($path,'download',false);
session_commit();
if(!$allow)
    die('Permission denied');

if(headers_sent())
    die('Some data has already been output to browser, can\'t send file');
$buffer = file_get_contents('data/Utils_Attachment/'.$filename);
//header('Content-Type: application/pdf');
header('Content-Length: '.strlen($buffer));
header('Content-disposition: inline; filename="'.$original.'"');
echo $buffer;
?>
