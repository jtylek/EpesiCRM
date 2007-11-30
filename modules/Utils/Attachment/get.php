<?php
if(!isset($_REQUEST['cid']) || !isset($_REQUEST['id']) || !isset($_REQUEST['path']))
    die('Invalid usage');
$cid = $_REQUEST['cid'];
$path = $_REQUEST['path'];
$id = $_REQUEST['id'];
$rev = $_REQUEST['revision'];
$disposition = (isset($_REQUEST['view']) && $_REQUEST['view'])?'inline':'attachment';

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

function get_mime_type($filepath) {
    //new method, but not compiled in by default
    if(extension_loaded('fileinfo')) {
        $fff = new finfo(FILEINFO_MIME);
        $ret = $fff->file($filepath);
        $fff->close();
        return $ret;
    }

    //deprecated method
    if(function_exists('mime_content_type'))
        return mime_content_type($filepath);

    //unix system
    ob_start();
    system("file -i -b {$filepath}");
    $output = ob_get_clean();
    $output = explode("; ",$output);
    if ( is_array($output) ) {
        $output = $output[0];
    }
    return $output;
}
$f_filename = 'data/Utils_Attachment/'.$filename;
$buffer = file_get_contents($f_filename);
header('Content-Type: '.get_mime_type($f_filename));
header('Content-Length: '.strlen($buffer));
header('Content-disposition: '.$disposition.'; filename="'.$original.'"');
echo $buffer;
?>
