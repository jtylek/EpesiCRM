<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-utils
 * @subpackage CustomMenu
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_CustomMenu extends Module {
	private $mid = null;
	private $function = null;
	private $arguments = null;
	
	/**
	 * Constructs new instance of CustomMenu module.
	 * Key specifies group of menu entries that will be operated with this instance.
	 * 
	 * @param string identifier of the menu entries group
	 */
	public function construct($id) {
		if(!isset($id)) {
			print($this->t('Menu Editor: no ID given - unable to edit menus'));
			return;
		}
		
		$this->mid = $id;
		
		if(!$this->isset_module_variable('data')) {
			$ret = DB::Execute('SELECT path FROM utils_custommenu_entry WHERE page_id=\''.md5($this->mid).'\'');
			$data = array();
			while($row = $ret->FetchRow())
				$data[] = $row['path'];
			$this->set_module_variable('data',$data);
		}
	}
	
	/**
	 * Menu entries from this group (specified in init_module) calls $function with $arguments.
	 *
	 * @param array or string arguments
	 * @param function name
	 */
	public function save($arguments,$function='body') {
		$id = md5($this->mid);
		$module = $this->parent->get_type();
		DB::Replace('utils_custommenu_page',array('id'=>$id,'module'=>$module,'function'=>$function,'arguments'=>serialize($arguments)),array('id'),true);
		DB::Execute('DELETE FROM utils_custommenu_entry WHERE page_id=%s',$id);
		$data = $this->get_module_variable('data');
		foreach($data as $row)
			DB::Execute('INSERT INTO utils_custommenu_entry(page_id,path) VALUES(%s, %s)',array($id,$row));		
	}

	/**
	 * Displays menu editor.
	 */
	public function body() {
		$edit = $this->get_module_variable_or_unique_href_variable('edit');
		if(isset($edit)) return $this->edit($edit);
		
		$gb = & $this->init_module('Utils/GenericBrowser',null,'custommenu');
		$data = $this->get_module_variable('data');
		$gb->set_table_columns(array(
			array('name'=>$this->t('Menu entry path'), 'width'=>70),
				));
		foreach($data as $row) {
			$r = & $gb->get_new_row();
			$r->add_data($row);
			$r->add_action($this->create_unique_href(array('edit'=>$row)),'Edit');
			$r->add_action($this->create_confirm_callback_href($this->ht('Are you sure?'),array($this,'delete_entry'),$row),'Delete');
		}
		$this->display_module($gb);
		
		Base_ActionBarCommon::add('add','New menu entry',$this->create_unique_href(array('edit'=>false)));
	}
	
	///////////////////////////////////////////////////////////////
	////////////////////   private area   /////////////////////////
	///////////////////////////////////////////////////////////////
	/**
	 * private function
	 */
	private function edit($path) {
		if($this->is_back()) {
			$this->unset_module_variable('edit');
			location(array());
			return;
		}
		
		$f = &$this->init_module('Libs/QuickForm');
		
		if($path)
			$f->setDefaults(array('path'=>$path));

		$f->addElement('text', 'path', $this->t('Menu entry path'),array('maxlength'=>255));
		$f->addRule('path',$this->t('This field is required'),'required');
		$f->addRule('path',$this->t('Field too long, max 255 chars'),'maxlength',255);
		$f->registerRule('check_path', 'callback', 'check_path', $this);
		$f->addRule('path',$this->t('Specified path already exists'),'check_path');
		
		$save_b = & HTML_QuickForm::createElement('submit', null, $this->ht('OK'));
		$back_b = & HTML_QuickForm::createElement('button', null, $this->ht('Cancel'), $this->create_back_href());
		$f->addGroup(array($save_b,$back_b),'submit_button');
		
		if($f->validate()) {
			$ret = $f->exportValue('path');
			$data = $this->get_module_variable('data');
			if($path) {
				foreach($data as & $row) {
					if($row==$path) $row = $ret;
				}
			} else {
				$data[] = $ret;
			}
			$this->set_module_variable('data',$data);
			$this->unset_module_variable('edit');
			location(array());
			return;
		}
		$f->display();
	}
	
	/**
	 * private function
	 */
	public function check_path($path) {
		$data = $this->get_module_variable('data');
		foreach($data as $row)
			if($row==$path) return false;
		$ret = DB::Execute('SELECT path FROM utils_custommenu_entry WHERE path=%s LIMIT 1',$path);
		if($ret->FetchRow()) return false;
		return true;
	}
	
	/**
	 * private function
	 */
	public function delete_entry($path) {
		DB::Execute('DELETE FROM utils_custommenu_entry WHERE path=%s',$path);
		$data = & $this->get_module_variable('data');
		foreach($data as $i=>$row) {
			if($row==$path) unset($data[$i]);
		}
	}
}

?>