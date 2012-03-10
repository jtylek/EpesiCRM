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
if(!isset($_REQUEST['token']) || !isset($_REQUEST['id']))
	die('Invalid usage');
$id = $_REQUEST['id'];
$token = $_REQUEST['token'];

define('CID', false);
define('READ_ONLY_SESSION',true);
require_once('../../../include.php');

$query = 'SELECT ual.local,uaf.revision,uaf.id,uaf.attach_id,uaf.original,uad.ip_address,uad.attach_file_id,uad.created_by,uad.created_on,uad.description FROM utils_attachment_file uaf INNER JOIN (utils_attachment_download uad,utils_attachment_link ual) ON (uad.attach_file_id=uaf.id AND uaf.attach_id=ual.id) WHERE uad.id='.DB::qstr($id).' AND uad.token='.DB::qstr($token).' AND uad.expires_on>'.DB::DBTimeStamp(time()).' AND uad.remote=';
$row = DB::GetRow($query.'1');
if($row==false) {
	$row = DB::GetRow($query.'2');
	if($row==false)
		die('No such file');
	$duplicate = true;
} else $duplicate = false;
$original = $row['original'];
$file_id = $row['attach_id'];
$rev = $row['revision'];
$local = $row['local'];
$filename = $local.'/'.$file_id.'_'.$rev;

if(headers_sent())
	die('Some data has already been output to browser, can\'t send file');

require_once('mime.php');

$t = time();
$remote_address = $_SERVER['REMOTE_ADDR'];
$remote_host = gethostbyaddr($_SERVER['REMOTE_ADDR']);
if($duplicate)
	DB::Execute('INSERT INTO utils_attachment_download(attach_file_id,created_by,created_on,download_on,description,ip_address,host_name,remote) VALUES (%d,%d,%T,%T,%s,%s,%s,2)',array($file_id,$row['created_by'],$row['created_on'],$t,$row['description'],$remote_address,$remote_host));
else
	DB::Execute('UPDATE utils_attachment_download SET remote=2, download_on=%T, ip_address=%s, host_name=%s WHERE id=%d',array($t,$remote_address,$remote_host,$id));
$f_filename = DATA_DIR.'/Utils_Attachment/'.$filename;
if(!file_exists($f_filename))
	die('File doesn\'t exists');
$buffer = file_get_contents($f_filename);
header('Content-Type: '.get_mime_type($f_filename,$original));
header('Content-Length: '.strlen($buffer));
header('Content-disposition: attachment; filename="'.$original.'"');
echo $buffer;
?>
