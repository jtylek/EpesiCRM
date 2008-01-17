<?php
/**
 * Popup message to the user
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package utils-messenger
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_Messenger extends Module {
	private $mid;
	private $autosave;

	public function construct($id,$autosave=true) {
		$this->lang = & $this->init_module('Base/Lang');
		if(!isset($id)) {
			print($this->lang->t('Messenger: no ID given - unable to attach messeges editor'));
			return;
		}
		
		$this->mid = md5($id);
		$this->autosave = $autosave;
		
		if(!$this->isset_module_variable('data')) {
			$data = DB::GetAll('SELECT * FROM utils_messenger_messege WHERE page_id=\''.$this->mid.'\'');
			$this->set_module_variable('data',$data);
		}
	}
	
	public function save() {
		DB::Execute('DELETE FROM utils_messenger_message WHERE page_id=%s',$this->mid);
		$data = $this->get_module_variable('data');
		foreach($data as $row)
			DB::Execute('INSERT INTO utils_messenger_message(page_id) VALUES(%s)',array($this->mid));		
	}


	public function body() {
	
	}

}

?>