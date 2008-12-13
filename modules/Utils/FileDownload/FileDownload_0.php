<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage FileDownload
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_FileDownload extends Module {
	private $file;
	private $callback;
	
	public function set_file($file) {
		$this->file=$file;
	}
	
	public function set_on_complete($c) {
		$this->callback=$c;
	}
	
	public function body($file=null,$callback=null) {
		if(!isset($file)) {
			if(!isset($this->file)) trigger_error('You have to specify file to download',E_USER_ERROR);
			$file=$this->file;
		}
		if(!isset($callback)) {
			if(!isset($this->callback)) trigger_error('You have to specify callback to call on download complete',E_USER_ERROR);
			$callback=$this->callback;
		}
		if(isset($_REQUEST['download_complete_'.$this->get_path()])) {
			$did = $this->get_module_variable('download_id');
			$dd = $this->get_data_dir();
			if(!isset($did) || !file_exists($dd.$did.'.tmp'))
				print('Download error.');
			else {
				DB::Execute('DELETE FROM utils_filedownload_files WHERE id=%d',array($did));
				$tf = $dd.$did.'.tmp';
				call_user_func($callback,$tf,basename($file));
				@unlink($tf);
			}
			return;
		}

		$path = $this->get_path();
		$id = $this->create_unique_key('stat');
		print('<div id="'.$id.'"></div>');
		eval_js_once('utils_filedownload_refresh = function(id,path){var stat=$(id);if(!stat || stat.innerHTML==\'Processing downloaded file\') return;'.
			'new Ajax.Updater(id,\''.$this->get_module_dir().'refresh.php\',{method:\'post\', parameters:{path: path}});'.
			'setTimeout("utils_filedownload_refresh(\'"+id+"\',\'"+path+"\')",3000);}');
		eval_js_once('utils_filedownload_check_completed = function(id){stat=document.getElementById(id);'.
				'if(stat && stat.innerHTML==\'Finished\'){
					stat.innerHTML=\'Processing downloaded file\';'.
					$this->create_href_js(array('download_complete_'.$this->get_path()=>1),$this->t('Download finished'),'queue').
				'}setTimeout(\'utils_filedownload_check_completed("\'+id+\'")\',500);}');
		DB::Execute('INSERT INTO utils_filedownload_files(path,size) VALUES (%s,-1)',array($file));
		$this->set_module_variable('download_id',DB::Insert_ID('utils_downloadfile_files','id'));
		print('<iframe src="'.$this->get_module_dir().'download.php?'.http_build_query(array('client_id'=>CID,'path'=>$path)).'"  width=0 height=0 frameborder=0>');
		eval_js('utils_filedownload_refresh("'.$id.'","'.$path.'");utils_filedownload_check_completed("'.$id.'")');

	}

}

?>