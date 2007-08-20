<?php
/**
 * Download file
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package utils
 * @subpackage file-download
 */
$id = $_REQUEST['client_id'];
$path = $_REQUEST['path'];
if(!isset($id) || !isset($path)) die('Invalid usage');

require_once('../../../include.php');

$download_id = Module::static_get_module_variable($id,$path,'download_id');
session_write_close();
$file = DB::GetOne('SELECT path FROM utils_filedownload_files WHERE id=%d',array($download_id));

$in = fopen($file,'rb');

$size = filesize($file);
$headers = array_change_key_case(get_headers($file, 1),CASE_LOWER);
if ((!array_key_exists("content-length", $headers))) $size = -1;
$size = $headers["content-length"];

print('Size: '.$size.'<br>');
flush();
ob_flush();

$dest_filename  = 'tmp_'.microtime(true);
$dest_path  = 'data/Utils_FileDownload/'.$dest_filename;
$out = fopen($dest_path,'wb');

print('Connected<br>');
flush();
ob_flush();

$x = 0;
$t = microtime(true);
$last_t = $t;

DB::Execute('UPDATE utils_filedownload_files SET size=%d, time=%f, view_time=%f WHERE id=%d',array($size,$t,$t,$download_id));

while(!feof($in)) {
	if(!ini_get('safe_mode')) set_time_limit(60);
	$cont = fread($in,8096);
	fwrite($out, $cont, 8096); //block 8kB
	$x+=strlen($cont);
	$curr_t = microtime(true);

	if($curr_t-$last_t>3) {
		$view_time = DB::GetOne('SELECT view_time FROM utils_filedownload_files WHERE id=%d',array($download_id));
		if($view_time===false) break;
		if($view_time+60<$curr_t) {
			DB::Execute('DELETE FROM utils_filedownload_files WHERE id=%d',array($download_id));
			break;
		}
		DB::Execute('UPDATE utils_filedownload_files SET curr=%d, time=%f, rate=%f  WHERE id=%d',array($x,$curr_t,$x/($curr_t-$t)/1024,$download_id));
		$last_t = $curr_t;
	}
}
fclose($in);
fclose($out);

?>
