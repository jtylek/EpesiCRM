<?php
/**
 * Use this module if you want to add attachments to some page.
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage attachment
 */

if(!isset($_REQUEST['cid']) || !isset($_REQUEST['id']))
	die('Invalid usage');
$cid = $_REQUEST['cid'];
$id = $_REQUEST['id'];

define('CID', $cid);
define('READ_ONLY_SESSION',true);
require_once('../../../include.php');
ModuleManager::load_modules();

if(!Acl::is_user())
	die('Permission denied');
$rec = Utils_RecordBrowserCommon::get_record('utils_attachment', $id);
if (!$rec) die('Invalid attachment.');
$access_fields = Utils_RecordBrowserCommon::get_access('utils_attachment', 'view', $rec);
if (!isset($access_fields['note']) || !$access_fields['note'])
    die('Access forbidden');

$password = '';
$crypted = $rec['crypted'];
if($crypted)
    $password = $_SESSION['client']['cp'.$rec['id']];
$files = DB::GetAssoc('SELECT id, original, filestorage_id FROM utils_attachment_file uaf WHERE uaf.attach_id=%d AND uaf.deleted=0', array($id));

$zip_filename = tempnam("tmp", "zip");

$zip = new ZipArchive();
//create the file and throw the error if unsuccessful
if ($zip->open($zip_filename, ZIPARCHIVE::OVERWRITE )!==TRUE) {
    die("cannot open $zip_filename for writing - contact with administrator");
}
//add each files of $file_name array to archive
$t = time();
$remote_address = get_client_ip_address();
$remote_host = gethostbyaddr($remote_address);
$local = $rec['id'];
$size = 0;
foreach($files as $fid=>$row)
{
    try {
        $meta = Utils_FileStorageCommon::meta($row['filestorage_id']);
    } catch(Exception $e) { continue; }
    $f_filename = $meta['file'];
    $size += filesize($f_filename);
    @ini_set('memory_limit',ceil($size*2/1024/1024+64).'M');
    $buffer = file_get_contents($f_filename);
    if($crypted) {
        $buffer = Utils_AttachmentCommon::decrypt($buffer,$password);
        if($buffer===false) continue;
    }
    DB::Execute('INSERT INTO utils_attachment_download(attach_file_id,created_by,created_on,download_on,description,ip_address,host_name) VALUES (%d,%d,%T,%T,%s,%s,%s)',array($fid,Acl::get_user(),$t,$t,'zip',$remote_address,$remote_host));
    $zip->addFromString($row['original'],$buffer);
}
$zip->close();

if(headers_sent())
    die('Some data has already been output to browser, can\'t send file');

header("Content-type: application/zip");
header("Content-Length: " . filesize($zip_filename));
header("Content-Disposition: attachment; filename=note_".$id.'.zip');
header("Pragma: no-cache");
header("Expires: 0");
@ob_end_flush();
@flush();
$fp = fopen($zip_filename, 'rb');
while (!feof($fp)) {
    print fread($fp, 1024);
}
fclose($fp);
@unlink($zip_filename);
