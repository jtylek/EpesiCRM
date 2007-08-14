<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package utils
 * @subpackage file-download
 * @licence SPL
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_FileDownload extends Module {

	public function body($file) {
		$l = & $this->init_module('Base/Lang');
		$file = "http://ftp.pwr.wroc.pl/pub/linux/ubuntu-others/Ubuntu7.05-desktop-i386-Fulmar.iso";
		$path = $this->get_path();
		$id = $this->create_unique_key('stat');
		print('<div id="'.$id.'"></div>');
		eval_js_once('utils_filedownload_refresh = function(id,path){if(!document.getElementById(id)) return;saja.updateIndicatorText(\''.$l->ht('Refreshing download status').'\');'.
			$GLOBALS['base']->run('refresh(client_id,path)->'.$id.':innerHTML','modules/Utils/FileDownload/refresh.php').
			'setTimeout("utils_filedownload_refresh(\'"+id+"\',\'"+path+"\')",3000);}');
		
		global $base;
		DB::Execute('INSERT INTO utils_filedownload_files(path,size) VALUES (%s,-1)',$file);
		$this->set_module_variable('download_id',DB::Insert_ID('utils_downloadfile_files','id'));
		print('<iframe src="'.$this->get_module_dir().'download.php?'.http_build_query(array('client_id'=>$base->get_client_id(),'path'=>$path)).'" width="0" height="0" frameborder="0" >');
		eval_js('setTimeout(\'utils_filedownload_refresh("'.$id.'","'.$path.'")\',3000)');

	}

}

?>