<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_CommonData extends Module {
	private $lang;

	public function admin() {
		$this->lang = $this->pack_module('Base/Lang');
		$action = $this->get_module_variable_or_unique_href_variable('action','browse');
		$this->$action();
	}

	public function admin_array($name) {
		$this->lang = $this->pack_module('Base/Lang');
		$this->set_module_variable('edit_name',$name);
		$action = $this->get_module_variable_or_unique_href_variable('action','edit');
		$this->$action();
	}
	
	public function add(){
		if ($this->is_back()){
			$this->set_module_variable('action','browse');
			location(array());	
		}
		$f = $this->init_module('Libs/QuickForm',null,'name_for_array');
		$f->addElement('header', null, $this->lang->t('Create New Common Data Array'));
		$f->addElement('text', 'name', 'Name');
		$f->addRule('name', $this->lang->t('This field is required'), 'required');
		$action_button[] = &HTML_QuickForm::createElement('submit', 'submit', 'Submit');
		$action_button[] = &HTML_QuickForm::createElement('button', 'cancel', 'Back',$this->create_back_href());
		$f->addGroup($action_button, 'action_button', '', ' ');
		if ($f->validate()) {
			$this->set_module_variable('action','edit');
			$this->set_module_variable('edit_name',$f->exportValue('name'));
			Utils_CommonDataCommon::new_array($f->exportValue('name'),array());
			location(array());
		} else $f->display();
	}
	
	public function edit(){
		if ($this->is_back()){
			$this->set_module_variable('action','browse');
			location(array());	
		}
		$name = $this->get_module_variable_or_unique_href_variable('edit_name');
		print('Array: <b>'.$name.'</b>');
		$id = DB::GetOne('SELECT id FROM utils_commondata_arrays WHERE name=%s',$name);
		if (!$id) {
			print($this->lang->t('No such array'));
			return;
		}

		$gb = &$this->init_module('Utils/GenericBrowser','edit');
		$gb->set_table_columns(array(	array('name'=>'Key','width'=>20, 'order'=>'akey'),
										array('name'=>'Value','width'=>20, 'order'=>'value')));
		
		$array = array();
		$ret = DB::Execute('SELECT akey, value FROM utils_commondata_data WHERE array_id=%d'.$gb->get_query_order(),$id);
		while ($row=$ret->FetchRow())
			$array[$row['akey']] = $row['value'];

		foreach($array as $k=>$v){
			$gb_row = $gb->get_new_row();
			$gb_row->add_data($k,$v);
			$gb_row->add_action($this->create_unique_href(array('action'=>'edit_field','edit_id'=>$name,'field_id'=>$k,'field_value'=>$v)),'Edit');
			$gb_row->add_action($this->create_confirm_callback_href($this->lang->t('Delete entry').'  '.$k.'=>'.$v.'?',array('Utils_CommonData','remove_field_refresh'), array($name, $k)),'Delete');
		}
		$this->display_module($gb);
		$f = $this->init_module('Libs/QuickForm',null,'new_field');
		$f->addElement('header', null, $this->lang->t('Add values to the array'));
		$f->addElement('text', 'key', 'Key');
		$f->addElement('text', 'value', 'Value');
		$f->addRule('value', $this->lang->t('This field is required'), 'required');
		$f->addRule('key', $this->lang->t('This field is required'), 'required');
		$f->setDefaults(array('key'=>'','value'=>''));
		$action_button[] = &HTML_QuickForm::createElement('submit', 'submit', 'Add value');
		$action_button[] = &HTML_QuickForm::createElement('button', 'cancel', 'Back',$this->create_back_href());
		$f->addGroup($action_button, 'action_button', '', ' ');
		if ($f->validate()) {
			Utils_CommonDataCommon::extend_array($name,array($f->exportValue('key')=>$f->exportValue('value')),true);
			location(array());
		}
		$f->display();
	}
	
	public function edit_field(){
		if ($this->is_back()){
			$this->set_module_variable('action','edit');
			location(array());	
		}
		$name = $this->get_module_variable('edit_name');
		$key = $this->get_module_variable_or_unique_href_variable('field_id');
		$value = $this->get_module_variable_or_unique_href_variable('field_value');
		print('Array: <b>'.$name.'</b>');
		$f = $this->init_module('Libs/QuickForm',null,'new_field');
		$f->addElement('header', null, $this->lang->t('Edit value of the array'));
		$f->addElement('text', 'key', 'Key');
		$f->addElement('text', 'value', 'Value');
		$f->addRule('value', $this->lang->t('This field is required'), 'required');
		$f->addRule('key', $this->lang->t('This field is required'), 'required');
		$f->setDefaults(array('key'=>$key,'value'=>$value));
		$action_button[] = &HTML_QuickForm::createElement('submit', 'submit', 'Save changes');
		$action_button[] = &HTML_QuickForm::createElement('button', 'cancel', 'Back',$this->create_back_href());
		$f->addGroup($action_button, 'action_button', '', ' ');
		if ($f->validate()) {
			Utils_CommonDataCommon::remove_field($name,$key);
			Utils_CommonDataCommon::extend_array($name,array($f->exportValue('key')=>$f->exportValue('value')),true);
			$this->set_module_variable('action','edit');
			location(array());
		}
		$f->display();
	}

	public function browse(){
		$gb = & $this->init_module('Utils/GenericBrowser','browse');
		$gb->set_table_columns(array(	array('name'=>$this->lang->t('Name'), 'width'=>20),
										array('name'=>$this->lang->t('Records amount'), 'width'=>20),
										));
		$ret = DB::Execute('SELECT uca.id, uca.name, (SELECT COUNT(*) FROM utils_commondata_data ucd WHERE ucd.array_id=uca.id) AS amount FROM utils_commondata_arrays uca');
		while($row=$ret->FetchRow()){
			$gb_row = $gb->get_new_row();
			$gb_row->add_data($row['name'],$row['amount']);
			$gb_row->add_action($this->create_unique_href(array('action'=>'edit','edit_name'=>$row['name'])),'Edit');
			$gb_row->add_action($this->create_confirm_callback_href($this->lang->t('Delete array').' \''.$row['name'].'\'?',array('Utils_CommonData','remove_array_refresh'), array($row['name'])),'Delete');
		}
		$this->display_module($gb);
		print('<a '.$this->create_unique_href(array('action'=>'add')).'>'.$this->lang->t('Add table').'</a>');
	}
	
	public static function remove_field_refresh($arg,$key=false){
		Utils_CommonDataCommon::remove_field($arg,$key);
		location(array());
	}
	
	public static function remove_array_refresh($name){
		Utils_CommonDataCommon::remove_array($name);
		location(array());
	}

}

?>