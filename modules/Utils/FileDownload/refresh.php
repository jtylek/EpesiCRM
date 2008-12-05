<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage FileDownload
 */
if(!isset($_POST['path']))
	die('Invalid request');
$path = $_POST['path'];
require_once('../../../include.php');
if(!Module::static_isset_module_variable($path,'download_id')) return;
$download_id = Module::static_get_module_variable($path,'download_id');
session_write_close();
$ret = DB::Execute('SELECT size,curr,time,rate FROM utils_filedownload_files WHERE id=%d',array($download_id));
$row = $ret->FetchRow();

if($row['size']==-2) {
	print('File not found.');
	return;
}

if($row['size']==-1) {
	print('Connecting...');
	return;
}
if($row['curr']==$row['size']) {
	print('Finished');
	return;
}

$t = microtime(true);
if($row['time']+60<$t) {
	print('Timeout');
	return;
}
		
$m = array('B','kB','MB','GB','TB');
$curr=$row['curr'];
for($i=0; $i<count($m) && $curr>1024; $i++)
	$curr /= 1024;

$size=$row['size'];
for($j=0; $j<count($m) && $size>1024; $j++)
	$size /= 1024;

$rate=$row['rate'];
for($k=0; $k<count($m) && $rate>1024; $k++)
	$rate /= 1024;
		
print(number_format($rate,2).$m[$k].'/s, '.number_format($curr,2).$m[$i].' z '.number_format($size,2).$m[$j]);
DB::Execute('UPDATE utils_filedownload_files SET view_time=%f  WHERE id=%d',array($t,$download_id));
exit();
?>
