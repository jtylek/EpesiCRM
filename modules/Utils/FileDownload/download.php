<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage FileDownload
 */
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // date in the past

$id = $_REQUEST['client_id'];
$path = $_REQUEST['path'];
if(!isset($id) || !isset($path)) die('Invalid usage');

define('CID',$id);
require_once('../../../include.php');

$download_id = Module::static_get_module_variable($path,'download_id',null);
session_write_close();
$file = DB::GetOne('SELECT path FROM utils_filedownload_files WHERE id=%d',array($download_id));

$headers = array_change_key_case(get_headers($file, 1),CASE_LOWER);
if(strpos($headers[0],'404')!==false) {
	DB::Execute('UPDATE utils_filedownload_files SET size=-2 WHERE id=%d',array($download_id));
	print('File not found: '.$headers[0]);
	exit();
} elseif ((!array_key_exists("content-length", $headers))) $size = -1;
else $size = $headers["content-length"];

print('Size: '.$size.'<br>');
flush();

$in = fopen($file,'rb');

$dest_filename  = $download_id.'.tmp';
$dest_path  = DATA_DIR.'/Utils_FileDownload/'.$dest_filename;
$out = fopen($dest_path,'wb');

print('Connected<br>');
flush();

$x = 0;
$t = microtime(true);
$curr_t = $last_t = $t;

DB::Execute('UPDATE utils_filedownload_files SET size=%d, time=%f, view_time=%f WHERE id=%d',array($size,$t,$t,$download_id));

while(!feof($in)) {
	if(!ini_get('safe_mode')) @set_time_limit(60);
	$cont = fread($in,8096);
	fwrite($out, $cont, 8096); //block 8kB
	$x+=strlen($cont);
	$curr_t = microtime(true);

	if($curr_t-$last_t>3) {
		$view_time = DB::GetOne('SELECT view_time FROM utils_filedownload_files WHERE id=%d',array($download_id));
		if($view_time===false || $view_time===null) break;
		if($view_time+60<$curr_t) {
			DB::Execute('DELETE FROM utils_filedownload_files WHERE id=%d',array($download_id));
			break;
		}
		DB::Execute('UPDATE utils_filedownload_files SET curr=%d, time=%f, rate=%f  WHERE id=%d',array($x,$curr_t,$x/($curr_t-$t),$download_id));
		$last_t = $curr_t;
	}
}
DB::Execute('UPDATE utils_filedownload_files SET curr=%d, time=%f, rate=%f  WHERE id=%d',array($x,$curr_t,$x/($curr_t-$t),$download_id));

fclose($in);
fclose($out);

?>
