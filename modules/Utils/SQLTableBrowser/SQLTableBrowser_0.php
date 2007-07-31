<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.8
 * @licence SPL
 * @package epesi-utils
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_SQLTableBrowser extends Module {
	private $lang;
	private $gb;
	private $sql;
	private $t_properties;
	private $t_structure;
	private $default_order;
	private $search;

	/**
	 * Module constructor.
	 */
	public function construct(){
		$name = $this->get_instance_id();
		if (is_numeric($name))
			trigger_error('SQLTableBrowser did not receive name for instance in module '.$this->get_parent_type().'.<br>Use $this->init_module(\'Utils/SQLTableBrowser\',\'instance name here\');',E_USER_ERROR);
		$this->gb = & $this->init_module('Utils/GenericBrowser',null,$name);
	}
	
	/**
	 * Sets table properties.
	 * An argument should be an array and following keys are used as preferences:
	 * table_name - SQL table name
	 * view - sets whether user can see details about each row
	 * add - sets whether user can add new row to the table
	 * edit - sets whether user can edit rows
	 * delete - sets whether user can delete rows
	 * paging - sets whether paging is enabled for table display
	 * id_row - name of primary key column (used to perform joins)
	 * form - QuickForm element that displays view/edit/new page for a row
	 * 
	 * @param array table properties
	 */
	public function set_table_properties($arg){
		if (!is_array($arg))
			trigger_error('invalid argument for set_table_properties.',E_USER_ERROR);
		$this->t_properties = $arg;
	}

	/**
	 * Sets table display properties.
	 * 
	 * An argument should be an array, each array field represents one column.
	 * Each column is represented by an array and following fields may be used:
	 * label - column label
	 * cloumn - SQL column name
	 * width - width of the column (percentage of the whole table)
	 * search - sets whether search is performed on this column
	 * order - sets whether table can be sort according to this column
	 * quickjump - sets this column as the one which is served using quickjump, only one column can be served this way
	 * wrapmode - what wrap method should be used (nowrap, wrap, cut)
	 * 
	 * @param array columns definiton
	 */
	public function set_table_format($arg){
		if (!is_array($arg))
			trigger_error('invalid argument for set_table_format.',E_USER_ERROR);
		$this->t_structure = $arg;
	}
	
	/**
	 * Sets default order for the table.
	 * This function can be called multiple times 
	 * and only at the first call 
	 * it will manipulate current order.
	 * 
	 * The default order should be provided as an array 
	 * containing column labels
	 * 
	 * @param array array with column labels
	 */
	public function set_default_order($arg){
		if (!is_array($arg))
			trigger_error('invalid argument for set_default_order.',E_USER_ERROR);
		$this->default_order = $arg;
	}

	/**
	 * Displays the table or associated form, depending on action.
	 */	
	public function body($arg) {
		$this->lang = &$this->pack_module('Base/Lang');
		$theme = &$this->pack_module('Base/Theme');
		if ($this->is_back())
			$this->unset_module_variable('action');
		$action = $this->get_module_variable_or_unique_href_variable('action');
		if ($action) {
			$id = $this->get_module_variable_or_unique_href_variable('id');
			$this->set_module_variable('id',$id);
			$this->set_module_variable('action',$action);
			ob_start();
			$this->$action($id);
			$form = ob_get_contents();
			$theme->assign('form', $form);
			ob_end_clean();
		} else {
			if ($this->t_properties['view'] || $this->t_properties['edit'] || $this->t_properties['delete']) $actions_on=true;
			if ($this->t_properties['add']) Base_ActionBarCommon::add('add',$this->lang->t('Add record'),$this->create_unique_href(array('action'=>'add')));
			$sql = '';
			foreach($this->t_structure as $k=>$v){
				preg_match('/[\s]?([\.a-zA-Z_]*)$/',$v['column'],$match);
				$this->t_structure[$k]['column_name'] = $match[1];
				$header[] = array('name'=>$v['label'],'width'=>$v['width'],'warpmode'=>$v['wrapmode'],'display'=>isset($v['display'])?$v['display']:1,'order'=>$v['order']?$match[1]:null,'quickjump'=>$v['quickjump']?$match[1]:null,'search'=>$v['search']?$match[1]:null);
				if (!$v['reference']) $sql .= ($sql?', ':'').$v['column'];
				else {
					$ref = $v['reference'];
					$sql .= ($sql?', ':'').'(SELECT '.$ref[2].' FROM '.$ref[0].' WHERE '.$ref[1].'='.$v['column'].') AS '.$v['column'];
				}
			}
			$this->gb->set_table_columns($header);
			if ($this->t_properties['paging']) {
				$qty = DB::GetOne('SELECT COUNT(*) FROM '.$this->t_properties['table_name']);
				$limit = $this->gb->get_limit($qty);
			}
			$search_sql = $this->gb->get_search_query();
			if ($this->search) 
				if ($search_sql) $search_sql .= ' AND ('.$this->search.')';
				else $search_sql = $this->search;
			$order_sql = $this->gb->get_query_order();
			$sql = 'SELECT '.$this->t_properties['id_row'].', '.$sql.' FROM '.$this->t_properties['table_name'].($search_sql?' WHERE '.$search_sql:'').$order_sql;
			$this->sql = $sql;
			if ($this->t_properties['paging'])
				$ret = DB::SelectLimit($sql,$limit['numrows'],$limit['offset']);
			else
				$ret = DB::Execute($sql);
			while ($row = $ret->FetchRow()){
				$id = $row[0];
				$gb_row = $this->gb->get_new_row();
				if ($actions_on) {
					if ($this->t_properties['view']) $gb_row->add_action($this->create_unique_href(array('action'=>'view','id'=>$id)),'View');
					if ($this->t_properties['edit']) $gb_row->add_action($this->create_unique_href(array('action'=>'edit','id'=>$id)),'Edit');
					if ($this->t_properties['delete']) $gb_row->add_action($this->create_unique_href(array('action'=>'delete','id'=>$id)),'Delete');
				}
				$gb_data_row = array();
				foreach(range(1,count($this->t_structure)) as $v) {
					$act = $this->t_structure[$v-1]['action'];
					if (!$act) $gb_data_row[] = $row[$v];
					else {
						$gb_data_row[] = '<a '.$this->create_href(array('box_main_module'=>$act['module'],'action'=>$act['action'],'id'=>$row[$act['id']])).'>'.$row[$v].'</a>';
					}
				}
				$gb_row->add_data_array($gb_data_row);
			}
			ob_start();
			$this->display_module($this->gb);
			$gb_contents = ob_get_contents();
			ob_end_clean();
			$theme->assign('generic_browser', $gb_contents);
		}
		$theme->display();
	}
	
	/**
	 * Allows for additional WHERE conditions to be added.
	 */
	public function search_by($arg){
		$this->search = $arg;
	}
	
	private function & get_form($id) {
		$f = &$this->t_properties['form'];
		$sql = 'SELECT * FROM '.$this->t_properties['table_name'].' WHERE '.$this->t_properties['id_row'].'='.DB::qstr($id);
		$row = DB::Execute($sql)->FetchRow();
		foreach($row as $k=>$v)
			$f -> setDefaults(array($k=>$v));
		return $f;
	}

	private function view($id){
		print('<b>'.$this->lang->t('View record').'</b>');
		$f = & $this->get_form($id);
		$f -> addElement('button', 'back', 'Back',$this->create_back_href());
		$f -> freeze();
		$f -> display();
	}

	private function edit($id){
		print('<b>'.$this->lang->t('Edit record').'</b>');
		$f = & $this->get_form($id);
		$action_button[] = &HTML_QuickForm::createElement('submit', 'submit', 'Save changes');
		$action_button[] = &HTML_QuickForm::createElement('button', 'cancel', 'Cancel',$this->create_back_href());
		$f->addGroup($action_button, 'action_button', '', ' ');
		if ($f->validate()){
			$values = $f->exportValues();
			unset($values['submited']);
			unset($values['action_button']);
			$sql = '';
			foreach ($values as $k=>$v){
				if (is_string($v)) $v = DB::qstr($v);
				$sql .= ($sql?', ':'').$k.'='.$v;
			}
			$sql = 'UPDATE '.$this->t_properties['table_name'].' SET '.$sql.' WHERE '.$this->t_properties['id_row'].'='.DB::qstr($id);
			DB::Execute($sql);
			$this->unset_module_variable('action');
			location(array());
		} else $f -> display();
	}

	private function add($id){
		print('<b>'.$this->lang->t('Add record').'</b>');
		$f = &$this->t_properties['form'];
		$action_button[] = &HTML_QuickForm::createElement('submit', 'submit', 'Save changes');
		$action_button[] = &HTML_QuickForm::createElement('button', 'cancel', 'Cancel',$this->create_back_href());
		$f->addGroup($action_button, 'action_button', '', ' ');
		if ($f->validate()){
			$values = $f->exportValues();
			unset($values['submited']);
			unset($values['action_button']);
			$first = '';
			$second = '';
			foreach ($values as $k=>$v){
				if (is_string($v)) $v = DB::qstr($v);
				$first .= ($first?', ':'').$k;
				$second .= ($second?', ':'').$v;
			}
			preg_match('/^([a-zA-Z_]*)[\s]?/',$this->t_properties['table_name'],$tn);
			$sql = 'INSERT INTO '.$tn[1].'('.$first.')'.' VALUES ('.$second.')';
			DB::Execute($sql);
			$this->unset_module_variable('action');
			location(array());
		} else $f -> display();
	}

	private function delete($id){
		preg_match('/^([a-zA-Z_]*)[\s]?/',$this->t_properties['table_name'],$tn);
		preg_match('/[\s\.]?([a-zA-Z_]*)$/',$this->t_properties['id_row'],$ir);
		$sql = 'DELETE FROM '.$tn[1].' WHERE '.$ir[1].'='.DB::qstr($id);
		DB::Execute($sql);
		$this->unset_module_variable('action');
		location(array());
	}
}

?>