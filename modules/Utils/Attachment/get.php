<?php
if(!isset($_REQUEST['cid']) || !isset($_REQUEST['id']) || !isset($_REQUEST['path']))
    die('Invalid usage');
$cid = $_REQUEST['cid'];
$path = $_REQUEST['path'];
$id = $_REQUEST['id'];
$rev = $_REQUEST['revision'];

define('CID', $cid);
require_once('../../../include.php');

$allow = Module::static_get_module_variable($path,'download',false);
$key = Module::static_get_module_variable($path,'key',null);
$local = Module::static_get_module_variable($path,'group',null);
session_commit();
if(!$allow || !$key || $local===null)
    die('Permission denied');
$original = DB::GetOne('SELECT ual.original FROM utils_attachment_file ual WHERE ual.attach_id='.DB::qstr($id).' AND ual.revision='.DB::qstr($rev));
$filename = $local.'/'.$id.'_'.$rev;

if(headers_sent())
    die('Some data has already been output to browser, can\'t send file');
$buffer = file_get_contents('data/Utils_Attachment/'.$filename);
header('Content-Type: application/octet-stream');
header('Content-Length: '.strlen($buffer));
header('Content-disposition: inline; filename="'.$original.'"');
echo $buffer;
?>
