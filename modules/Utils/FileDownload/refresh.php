<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package utils
 * @subpackage file-download
 * @licence SPL
 */
class myFunctions extends Epesi {
	public function refresh($cl_id,$path) {
		//initialize Epesi
		$this->init($cl_id);
		if(!Module::static_isset_module_variable($cl_id,$path,'download_id')) return;
		$download_id = Module::static_get_module_variable($cl_id,$path,'download_id');
		$ret = DB::Execute('SELECT size,curr,time,rate FROM utils_filedownload_files WHERE id=%d',array($download_id));
		$row = $ret->FetchRow();
		if($row['size']==-1) {
			print('Connecting...');
			return;
		}

		$t = microtime(true);
		if($row['time']+60<$t) {
			print('Timeout');
			return;
		}
		print($row['rate'].'kB/s, '.$row['curr'].' z '.$row['size']);
		DB::Execute('UPDATE utils_filedownload_files SET view_time=%f  WHERE id=%d',array($t,$download_id));
	}
}
?>
