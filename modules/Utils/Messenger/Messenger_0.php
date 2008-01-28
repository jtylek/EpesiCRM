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
		
		if($autosave || !$this->isset_module_variable('data')) {
			$data = DB::GetAll('SELECT * FROM utils_messenger_messege WHERE page_id=\''.$this->mid.'\'');
			$this->set_module_variable('data',$data);
		}
	}
	
	public function save() {
		if($this->autosave) return;
		DB::Execute('DELETE FROM utils_messenger_message WHERE page_id=\''.$this->mid.'\'');
		$data = $this->get_module_variable('data');
		foreach($data as $row)
			DB::Execute('INSERT INTO utils_messenger_message(page_id) VALUES(%s)',array($this->mid));		
	}
	
	public function edit($row) {
		if($this->is_back()) {
			$this->unset_module_variable('edit');
			return false;
		}
		
		$f = &$this->init_module('Libs/QuickForm');
		
		if($row)
			$f->setDefaults($row);

		$f->addElement('text', 'topic', $this->lang->t('Topic'),array('maxlength'=>128));
		$f->addRule('path',$this->lang->t('This field is required'),'required');
		$f->addRule('path',$this->lang->t('Field too long, max 128 chars'),'maxlength',128);

		$f->addElement('textarea', 'message', $this->lang->t('Message'));
		
/*		$save_b = & HTML_QuickForm::createElement('submit', null, $this->lang->ht('OK'));
		$back_b = & HTML_QuickForm::createElement('button', null, $this->lang->ht('Cancel'), $this->create_back_href());
		$f->addGroup(array($save_b,$back_b),'submit_button');
*/		
		if($f->validate()) {
			$ret = array_merge($row,$f->exportValues());
			if($this->autosave) {
				if($row)
					DB::Execute('UPDATE utils_messenger_message SET topic=%s,message=%s WHERE page_id=\''.$this->mid.'\' AND id=%d',array($ret['topic'],$ret['message'],$row['id']));
				else
					DB::Execute('INSERT INTO utils_messenger_message(page_id,message,topic) VALUES(%s,%s,%s)',array($this->mid,$ret['message'],$ret['topic']));
			} else {
				$data = $this->get_module_variable('data');
				if($path) {
					foreach($data as & $row) {
						if($row==$path) $row = $ret;
					}
				} else {
					$data[] = $ret;
				}
			}
			$this->set_module_variable('data',$data);
			$this->unset_module_variable('edit');
			return false;
		}
		
		$f->display();
		
		return true;
	}
	
	public function delete_entry($id) {
	
	}

	public function body() {
		$edit = $this->get_module_variable_or_unique_href_variable('edit');
		if(isset($edit) && $this->edit($edit)) return true;
		
		$gb = & $this->init_module('Utils/GenericBrowser',null,'messages');
		$data = $this->get_module_variable('data');
		$gb->set_table_columns(array(
			array('name'=>$this->lang->t('Topic'), 'width'=>30),
			array('name'=>$this->lang->t('Message'), 'width'=>70),
				));
		foreach($data as $row) {
			$r = & $gb->get_new_row();
			$r->add_data($row['topic'],$row['message']);
			$r->add_action($this->create_callback_href(array($this,'edit'),array($row)),'Edit');
			$r->add_action($this->create_confirm_callback_href($this->lang->ht('Are you sure?'),array($this,'delete_entry'),$row['id']),'Delete');
		}
		$this->display_module($gb);
		
		Base_ActionBarCommon::add('add','New message',$this->create_callback_href(array($this,'edit'),array(false)));	
	}
}

?>