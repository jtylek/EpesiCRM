<?php
/**
 * RecordBrowser class.
 * 
 * @author Kuba Sławiński <ruud@o2.pl>, Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.99
 * @package tcms-extra
 */

defined("_VALID_ACCESS") || die();

class Base_RecordBrowser extends Module {
	private $table_rows = array();
	private $lang;
	private $SQL_chars = array('text'=>'%s', 'long text'=>'%s', 'date'=>'%D', 'integer'=>'%d');
	private $tab;
	private $browse_mode;

	public function construct($tab = null) {
		if (!isset($tab))
			trigger_error('RecordBrowser did not receive string name for the table '.$this->get_parent_type().'.<br>Use $this->init_module(\'Utils/RecordBrowser\',\'table name here\');',E_USER_ERROR);
		$this->tab = $tab;
	}
	
	public function init($admin=false) {
		if (!isset($this->lang)) $this->lang = & $this->init_module('Base/Lang');
		$this->table_rows = Base_RecordBrowserCommon::init($this->tab, $admin);
	}
	// BODY //////////////////////////////////////////////////////////////////////////////////////////////////////
	public function body($arg) {
		if(Base_AclCommon::i_am_user()) {
			$this->init();
			if (isset($_REQUEST['tab'])) {
				$this->tab = $_REQUEST['tab'];
				$this->init();
				$this->view_entry($_REQUEST['action'], $_REQUEST['id']);
			} else {
				Base_ActionBarCommon::add('add',$this->lang->t('New'), $this->create_callback_href(array($this,'view_entry'),array('add')));
				$this->browse_mode = $this->get_module_variable('browse_mode', 'all');
				if ($this->browse_mode!=='recent') Base_ActionBarCommon::add('report',$this->lang->t('Recent'), $this->create_callback_href(array($this,'switch_view'),array('recent')));
				if ($this->browse_mode!=='all') Base_ActionBarCommon::add('report',$this->lang->t('All'), $this->create_callback_href(array($this,'switch_view'),array('all')));
				if ($this->browse_mode!=='favorites') Base_ActionBarCommon::add('report',$this->lang->t('Favorites'), $this->create_callback_href(array($this,'switch_view'),array('favorites')));
				$crits = $this->show_filters();
				$this->show_data($crits);
			}
		} else
			print($this->lang->t('You must log in to view this data.'));
	}
	public function switch_view($mode){
		$this->browse_mode = $mode;
		$this->set_module_variable('browse_mode', $mode);
	}
	//////////////////////////////////////////////////////////////////////////////////////////
	public function show_filters() {
		$ret = DB::Execute('SELECT field FROM '.$this->tab.'_field WHERE filter=1');
		if ($ret->EOF) return array();
		$form = $this->init_module('Libs/QuickForm', null, $this->tab.'filters');
		$filters = array();
		while($row = $ret->FetchRow()) {
			if ($this->table_rows[$row['field']]['type'] == 'select') {
				$arr = array('__NULL__'=>'--');
				list($tab, $col) = explode('::',$this->table_rows[$row['field']]['param']);
				$ret2 = DB::Execute('SELECT '.$tab.'_id, value FROM '.$tab.'_data WHERE field=%s', array($col));
				while ($row2 = $ret2->FetchRow()) $arr[$row2[$tab.'_id']] = $row2['value'];
			} else {
				$ret2 = DB::Execute('SELECT value FROM '.$this->tab.'_data WHERE field=%s ORDER BY value', array($row['field']));
				$arr = array('__NULL__'=>'--');
				while ($row2 = $ret2->FetchRow()) $arr[$row2['value']] = $row2['value'];
			}
			$form->addElement('select', str_replace(' ','_',$row['field']), $row['field'], $arr);
			$filters[] = str_replace(' ','_',$row['field']);
		}
		$form->addElement('submit', 'submit', 'Show');
		$crits = array();
		if ($form->validate()) {
			$vals = $form->exportValues();
			unset($vals['submit']);
			unset($vals['submited']);
			foreach($vals as $k=>$v)
				if ($v!=='__NULL__') $crits[$k] = $v;
		}
		$theme = $this->init_module('Base/Theme');
		$form->assign_theme('form',$theme);
		$theme->assign('filters', $filters);
		$theme->display('Filter');
		return $crits;
	}
	//////////////////////////////////////////////////////////////////////////////////////////
	public function show_data($crits = array(), $cols = array(), $fs_links = false) {
		$this->init();
		$gb = & $this->init_module('Utils/GenericBrowser', null, $this->tab);
		
		$table_columns = array();
		
		$table_columns_SQL = array();
		$quickjump = DB::GetOne('SELECT col FROM recordbrowser_quickjump WHERE tab=%s', array($this->tab));
		foreach($this->table_rows as $field => $args) {
			if ($field === 'id') continue;
			if (!$args['visible'] && (!isset($cols[$args['name']]) || $cols[$args['name']] === false)) continue;
			if (isset($cols[$args['name']]) && $cols[$args['name']] === false) continue;
			$arr = array('name'=>$args['name'], 'order'=>$field);
			if ($quickjump!==false && $args['name']===$quickjump) $arr['quickjump'] = $args['name'];
			$table_columns[] = $arr;
			array_push($table_columns_SQL, 'e.'.$field);
		}

		$table_columns_SQL = join(', ', $table_columns_SQL);
		if ($this->browse_mode == 'recent')
			$table_columns[] = array('name'=>$this->lang->t('Visited on')); 
		$gb->set_table_columns( $table_columns );
		$crits = array_merge($crits, $gb->get_search_query(true));

		$get_fields = array();
		
		$records = Base_RecordBrowserCommon::get_records($this->tab, $crits);
		if ($this->browse_mode == 'recent') {
			$rec_tmp = array();
			$ret = DB::Execute('SELECT * FROM '.$this->tab.'_recent WHERE user_id=%d ORDER BY visited_on DESC', array(Base_UserCommon::get_my_user_id()));
			while ($row = $ret->FetchRow()) {
				if (!isset($records[$row[$this->tab.'_id']])) continue;
				$rec_tmp[$row[$this->tab.'_id']] = $records[$row[$this->tab.'_id']];
				$rec_tmp[$row[$this->tab.'_id']]['visited_on'] = $row['visited_on'];
			}
			$records = $rec_tmp;
		}
		if ($this->browse_mode == 'favorites') {
			$rec_tmp = array();
			$ret = DB::Execute('SELECT * FROM '.$this->tab.'_favorite WHERE user_id=%d', array(Base_UserCommon::get_my_user_id()));
			while ($row = $ret->FetchRow()) {
				if (!isset($records[$row[$this->tab.'_id']])) continue;
				$rec_tmp[$row[$this->tab.'_id']] = $records[$row[$this->tab.'_id']];
			}
			$records = $rec_tmp;
		}
		foreach ($records as $row) {
			$gb_row = & $gb->get_new_row();
			$row_data = array();
			foreach($this->table_rows as $field => $args)
				if (($args['visible'] && !isset($cols[$args['name']])) || (isset($cols[$args['name']]) && $cols[$args['name']] === true)) {
					if ($args['type']=='select') {
						list($tab, $col) = explode('::',$args['param']);
						$row[$field] = DB::GetOne('SELECT '.$tab.'_id FROM '.$tab.'_data WHERE field=%s AND '.$tab.'_id=%d', array($col, $row[$field]));
						if ($row[$field]===false) $row[$field] = '--';
						else {
							$row[$field] = $this->create_linked_label($tab, $col, $row[$field]);
						}
					}
					$row_data[] = $row[$field];
				}
			if ($this->browse_mode == 'recent')
				$row_data[] = $row['visited_on'];
			$gb_row->add_data_array($row_data);
			if ($fs_links===false) {
				$gb_row->add_action($this->create_callback_href(array($this,'view_entry'),array('view',$row['id'])),$this->lang->t('View'));
				$gb_row->add_action($this->create_callback_href(array($this,'view_entry'),array('edit',$row['id'])),$this->lang->t('Edit'));
				$gb_row->add_action($this->create_callback_href(array($this,'view_entry'),array('delete',$row['id'])),$this->lang->t('Delete'));
			} else {
				$gb_row->add_action($this->create_href(array('box_main_module'=>'Base_RecordBrowser', 'box_main_constructor_arguments'=>array($this->tab), 'tab'=>$this->tab, 'id'=>$row['id'], 'action'=>'view')),$this->lang->t('View'));
				$gb_row->add_action($this->create_href(array('box_main_module'=>'Base_RecordBrowser', 'box_main_constructor_arguments'=>array($this->tab), 'tab'=>$this->tab, 'id'=>$row['id'], 'action'=>'edit')),$this->lang->t('Edit'));
				$gb_row->add_action($this->create_href(array('box_main_module'=>'Base_RecordBrowser', 'box_main_constructor_arguments'=>array($this->tab), 'tab'=>$this->tab, 'id'=>$row['id'], 'action'=>'delete')),$this->lang->t('Delete'));
			}
		}
		$this->display_module($gb);
	} //show employees
	//////////////////////////////////////////////////////////////////////////////////////////
	public function view_entry($mode, $id = null) {
		$js = true;
		if($this->is_back())
			return false;
		$this->init();

		if($mode!='add')
			$this->add_recent_entry(Base_UserCommon::get_my_user_id(),$id);

		$tb = $this->init_module('Utils/TabbedBrowser');
		$form = & $this->init_module('Libs/QuickForm',null,$mode);

		$this->prepare_view_entry_details($mode, $id, &$form);

		if ($form->validate()) {
			$values = $form->exportValues();
			if ($mode=='add') {
				$this->add_record($values);
			} elseif ($mode=='delete') {
				$this->delete_record($id);
			} else {
				$this->update_record_data($id,$values);
			}
			return false;
		}

		if ($mode=='view') { 
			Base_ActionBarCommon::add('edit', $this->lang->ht('Edit'), $this->create_callback_href(array($this,'view_entry'), array('edit',$id)));
			Base_ActionBarCommon::add('back', $this->lang->ht('Back'), $this->create_callback_href(array('History', 'back')));
		} else if ($mode=='delete') {
			Base_ActionBarCommon::add('delete', $this->lang->ht('Delete'), $form->get_submit_form_href());
			Base_ActionBarCommon::add('back', $this->lang->ht('Back'), $this->create_callback_href(array('History', 'back')));
		} else {
			Base_ActionBarCommon::add('save', $this->lang->ht('Save'), $form->get_submit_form_href());
			Base_ActionBarCommon::add('delete', $this->lang->ht('Cancel'), $this->create_callback_href(array('History', 'back')));
		}
		
		if ($mode!='add') {
			$fav = DB::GetOne('SELECT user_id FROM '.$this->tab.'_favorite WHERE user_id=%d AND '.$this->tab.'_id=%s', array(Base_UserCommon::get_my_user_id(), $id));
			if ($fav===false)
				Base_ActionBarCommon::add('folder', $this->lang->ht('Add to Favs'), $this->create_callback_href(array($this,'add_to_favs'), array($id)));
			else
				Base_ActionBarCommon::add('folder', $this->lang->ht('Remove from Favs'), $this->create_callback_href(array($this,'remove_from_favs'), array($id)));
		}

		if ($mode=='delete' || $mode=='view') $form->freeze();
		if(!isset($renderer)) $renderer = & new HTML_QuickForm_Renderer_TCMSArraySmarty(); 
		$form->accept($renderer);
		$data = $renderer->toArray();
		
		print($data['javascript'].'<form '.$data['attributes'].'>'.$data['hidden']."\n");

		$last_page = DB::GetOne('SELECT MIN(position) FROM '.$this->tab.'_field WHERE type = \'page_split\' AND field != \'General\'');
		$label = DB::GetOne('SELECT field FROM '.$this->tab.'_field WHERE position=%s', array($last_page));
		$this->view_entry_details(1, $last_page, $data, true);
		$ret = DB::Execute('SELECT position, field FROM '.$this->tab.'_field WHERE type = \'page_split\' AND position > %d', array($last_page));
		$row = true;
		while ($row) {
			$row = $ret->FetchRow();
			if ($row) $pos = $row['position'];
			else $pos = DB::GetOne('SELECT MAX(position) FROM '.$this->tab.'_field')+1;
			if ($pos - $last_page>1) $tb->set_tab($this->lang->t($label),array($this,'view_entry_details'), array($last_page, $pos+1, $data), $js);
			$last_page = $pos;
			if ($row) $label = $row['field'];
		}
		if ($mode!='add') {
			$record = Base_RecordBrowserCommon::get_record($this->tab, $id, true);
			$ret = DB::Execute('SELECT * FROM '.$this->tab.'_addon');
			while ($row = $ret->FetchRow()) {
				$mod = $this->init_module($row['module']);
				$tb->set_tab($this->lang->t($row['label']),array($this, 'display_module'), array($mod, array($record), $row['func']), $js);
			}
		}
		$this->display_module($tb);
		$tb->tag();

		print("</form>\n");

		return true;
	} //view_entry
	
	public function view_entry_details($from, $to, $data, $main_page = false){
		$theme = $this->init_module('Base/Theme');
		$fields = array();
		foreach($this->table_rows as $field => $args) 
			if ($args['position'] >= $from && ($to == -1 || $args['position'] < $to)) 
			{	
				$fields[$args['id']] = array(	'label'=>$data[$args['id']]['label'],
												'html'=>$data[$args['id']]['html'],
												'type'=>$args['type']);
			}
		$theme->assign('fields', $fields);
		if ($main_page) $tpl = DB::GetOne('SELECT filename FROM recordbrowser_tpl WHERE tab=%s', array($this->tab));
		else $tpl = false;
		$theme->display(($tpl!==false)?$tpl:'View_entry', ($tpl!==false));
	}

	public function prepare_view_entry_details($mode, $id, &$form){
		$form->addElement('header', null, $this->lang->t($header));
		
		if ($mode!=='add') $records = Base_RecordBrowserCommon::get_records($this->tab);
		
		foreach($this->table_rows as $field => $args){ 
			switch ($args['type']) {
				case 'integer':		$form->addElement('text', $args['id'], $this->lang->t($args['name']));
									$form->addRule($args['id'], $this->lang->t('Only numbers are allowed.'), 'numeric');
									if ($mode!=='add') $form->setDefaults(array($args['id']=>$records[$id][$field]));
									break;
				case 'text':		$form->addElement('text', $args['id'], $this->lang->t($args['name']), array('maxlength'=>$args['param']));
									$form->addRule($args['id'], $this->lang->t('Maximum length for this field is '.$args['param'].'.'), 'maxlength', $args['param']);
									if ($mode!=='add') $form->setDefaults(array($args['id']=>$records[$id][$field]));
									break;
				case 'long text':	$form->addElement('textarea', $args['id'], $this->lang->t($args['name']));
									if ($mode!=='add') $form->setDefaults(array($args['id']=>$records[$id][$field]));
									break;
				case 'date':		$form->addElement('datepicker', $args['id'], $this->lang->t($args['name']));
									if ($mode!=='add') $form->setDefaults(array($args['id']=>$records[$id][$field]));
									break;
				case 'select':		$comp = array();
									if (!$args['required']) $comp[''] = '--';
									list($tab, $col) = explode('::',$args['param']);
									if ($mode=='add' || $mode=='edit') {
										$ret = DB::Execute('SELECT * FROM '.$tab.'_data WHERE field=%s ORDER BY value', array($col));
										while ($row = $ret->FetchRow()) $comp[$row[$tab.'_id']] = $row['value'];
										$form->addElement('select', $args['id'], $this->lang->t($args['name']), $comp);
										if ($mode!=='add') $form->setDefaults(array($args['id']=>$records[$id][$field]));
									} else {
										$form->addElement('static', $args['id'], $this->lang->t($args['name']));
										if ($mode!=='add') $form->setDefaults(array($args['id']=>$this->create_linked_label($tab, $col, $records[$id][$field])));
									}
									break;
			}
			if ($args['required'])
				$form->addRule($args['id'], $this->lang->t('Field required'), 'required');
		}		
	}
	public function add_to_favs($id) {
		DB::Execute('INSERT INTO '.$this->tab.'_favorite (user_id, '.$this->tab.'_id) VALUES (%d, %d)', array(Base_UserCommon::get_my_user_id(), $id));
	}
	public function remove_from_favs($id) {
		DB::Execute('DELETE FROM '.$this->tab.'_favorite WHERE user_id=%d AND '.$this->tab.'_id=%d', array(Base_UserCommon::get_my_user_id(), $id));
	}
	public function add_record($values) {
		DB::StartTrans();	
		$SQLcols = array();
		DB::Execute('INSERT INTO '.$this->tab.' (created_on, created_by, active) VALUES (%T, %d, %d)',array(date('Y-m-d G:i:s'), Base_UserCommon::get_my_user_id(), 1));
		$id = DB::Insert_ID($this->tab, 'id');
		$this->add_recent_entry(Base_UserCommon::get_my_user_id(), $id);
		foreach($this->table_rows as $field => $args)
			DB::Execute('INSERT INTO '.$this->tab.'_data ('.$this->tab.'_id, field, value) VALUES (%d, %s, %s)',array($id, $field, $values[$args['id']]));
		DB::CompleteTrans();
	}
	public function delete_record($id) {
		DB::Execute('UPDATE '.$this->tab.' SET active=0 where id=%d', array($id));
	}		
	public function update_record_data($id,$values) {
		DB::StartTrans();	
		$this->init();
		$records = Base_RecordBrowserCommon::get_records($this->tab);
		$diff = array();
		foreach($this->table_rows as $field => $args){
			if ($args['id']=='id') continue;
			if (!isset($values[$args['id']])) $values[$args['id']] = '';
			if ($records[$id][$field]!==$values[$args['id']]) {
				DB::StartTrans();
				$val = DB::GetOne('SELECT value FROM '.$this->tab.'_data WHERE '.$this->tab.'_id=%d AND field=%s',array($id, $field));
				if ($val!==false) DB::Execute('UPDATE '.$this->tab.'_data SET value=%s WHERE '.$this->tab.'_id=%d AND field=%s',array($values[$args['id']], $id, $field));
				else DB::Execute('INSERT INTO '.$this->tab.'_data(value, '.$this->tab.'_id, field) VALUES (%s, %d, %s)',array($values[$args['id']], $id, $field));
				DB::CompleteTrans();
				$diff[$field] = $records[$id][$field];
			}
		}
		if (!empty($diff)) {
			DB::Execute('INSERT INTO '.$this->tab.'_edit_history(edited_on, edited_by, '.$this->tab.'_id) VALUES (%T,%d,%d)', array(date('Y-m-d G:i:s'), Base_UserCommon::get_my_user_id(), $id));
			$edit_id = DB::Insert_ID(''.$this->tab.'_edit_history','id');
			foreach($diff as $k=>$v)
				DB::Execute('INSERT INTO '.$this->tab.'_edit_history_data(edit_id, field, old_value) VALUES (%d,%s,%s)', array($edit_id, $k, $v));
		}
		DB::CompleteTrans();
	}
	//////////////////////////////////////////////////////////////////////////////////////////
	public function admin() {
		$this->init();
		$tb = & $this->init_module('Utils/TabbedBrowser');
		
		$tb->set_tab($this->lang->t('Manage Fields'),array($this, 'setup_loader') );
		$tb->set_tab($this->lang->t('Manage Records'),array($this, 'show_data_table') );
		
		$tb->body();
		$tb->tag();
	}
	
	public function new_page() {
		DB::StartTrans();
		$max_f = DB::GetOne('SELECT MAX(position) FROM '.$this->tab.'_field');
		$num = 1;
		do {
			$num++;
			$x = DB::GetOne('SELECT position FROM '.$this->tab.'_field WHERE type = \'page_split\' AND field = %s', array('Details '.$num));
		} while ($x!==false);
		DB::Execute('INSERT INTO '.$this->tab.'_field (field, type, extra, position) VALUES(%s, \'page_split\', 1, %d)', array('Details '.$num, $max_f+1));
		DB::CompleteTrans();
	}	
	public function delete_page($id) {
		DB::StartTrans();
		$p = DB::GetOne('SELECT position FROM '.$this->tab.'_field WHERE field=%s', array($id));
		DB::Execute('UPDATE '.$this->tab.'_field SET position = position-1 WHERE position > %d', array($p));
		DB::Execute('DELETE FROM '.$this->tab.'_field WHERE field=%s', array($id));
		DB::CompleteTrans();
	}
	public function edit_page($id) {
		if ($this->is_back()) return false;
		$this->init();
		$form = & $this->init_module('Libs/QuickForm', null, 'edit_page');
		
		$form->addElement('header', null, $this->lang->t('Edit page properties'));
		$form->addElement('text', 'label', $this->lang->t('Label'));
		$form->registerRule('check_if_column_exists', 'callback', 'check_if_column_exists', &$this);
		$form->registerRule('check_if_no_id', 'callback', 'check_if_no_id', &$this);
		$form->addRule('label', $this->lang->t('Field required.'), 'required');
		$form->addRule('label', $this->lang->t('Field or Page with this name already exists.'), 'check_if_column_exists');
		$form->addRule('label', $this->lang->t('Only letters and space are allowed.'), 'regex', '/^[a-zA-Z ]*$/');
		$form->addRule('label', $this->lang->t('"ID" as page name is not allowed.'), 'check_if_no_id');
		$form->setDefaults(array('label'=>$id));

		$ok_b = HTML_QuickForm::createElement('submit', 'submit_button', $this->lang->ht('OK'));
		$cancel_b = HTML_QuickForm::createElement('button', 'cancel_button', $this->lang->ht('Cancel'), $this->create_back_href());
		$form->addGroup(array($ok_b, $cancel_b));
				
		if($form->validate()) {
			$data = $form->exportValues();
			foreach($data as $key=>$val)
				$data[$key] = htmlspecialchars($val);
			DB::Execute('UPDATE '.$this->tab.'_field SET field=%s WHERE field=%s',
						array($data['label'], $id));
			return false;
		}
		$form->display();
		return true;
	}
	public function setup_loader() {
		$this->init(true);
		$action = $this->get_module_variable_or_unique_href_variable('setup_action', 'show');
		$subject = $this->get_module_variable_or_unique_href_variable('subject', 'regular');
		
		Base_ActionBarCommon::add('add','New field',$this->create_callback_href(array($this, 'view_field')));
		Base_ActionBarCommon::add('add','New page',$this->create_callback_href(array($this, 'new_page')));
		$gb = & $this->init_module('Utils/GenericBrowser', null, 'fields');
		$gb->set_table_columns(array(
			array('name'=>$this->lang->t('Field'), 'width'=>20),
			array('name'=>$this->lang->t('Type'), 'width'=>20),
			array('name'=>$this->lang->t('Visible'), 'width'=>5),
			array('name'=>$this->lang->t('Required'), 'width'=>5),
			array('name'=>$this->lang->t('Filter'), 'width'=>5),
			array('name'=>$this->lang->t('Parameters'), 'width'=>5))
		);

		//read database
		$rows = count($this->table_rows);
		$max_p = DB::GetOne('SELECT position FROM '.$this->tab.'_field WHERE field = \'Details\'');
		foreach($this->table_rows as $field=>$args) {
			$gb_row = & $gb->get_new_row();
			if($args['extra']) {
				if ($args['type'] != 'page_split') {
					if ($args['active']) $gb_row->add_action($this->create_callback_href(array($this, 'set_field_active'),array($field, false)),'Deactivate');
					else $gb_row->add_action($this->create_callback_href(array($this, 'set_field_active'),array($field, true)),'Activate');
					$gb_row->add_action($this->create_callback_href(array($this, 'view_field'),array('edit',$field)),'Edit');
				} else {
					$gb_row->add_action($this->create_callback_href(array($this, 'delete_page'),array($field)),'Delete');
					$gb_row->add_action($this->create_callback_href(array($this, 'edit_page'),array($field)),'Edit');
				}
				if ($args['position']<=$rows)
					$gb_row->add_action($this->create_callback_href(array($this, 'move_field'),array($field, $args['position'], +1)),'Move down');
				if ($args['position']>$max_p+1)
					$gb_row->add_action($this->create_callback_href(array($this, 'move_field'),array($field, $args['position'], -1)),'Move up');
			} else {
				if ($field!='General' && $args['type']=='page_split')
					$gb_row->add_action($this->create_callback_href(array($this, 'edit_page'),array($field)),'Edit');
			}
			if ($args['type']=='text')
				$args['param'] = $this->lang->t('Length').' '.$args['param'];
			if ($args['type'] == 'page_split')
					$gb_row->add_data(
						array('style'=>'background-color: #DFDFFF;', 'value'=>$field), 
						array('style'=>'background-color: #DFDFFF;', 'value'=>$this->lang->t('Page Split')), 
						array('style'=>'background-color: #DFDFFF;', 'value'=>''), 
						array('style'=>'background-color: #DFDFFF;', 'value'=>''), 
						array('style'=>'background-color: #DFDFFF;', 'value'=>''), 
						array('style'=>'background-color: #DFDFFF;', 'value'=>'')
					);
				else 
					$gb_row->add_data( 
						$field, 
						$args['type'], 
						$args['visible']?$this->lang->t('<b>Yes</b>'):$this->lang->t('No'), 
						$args['required']?$this->lang->t('<b>Yes</b>'):$this->lang->t('No'), 
						$args['filter']?$this->lang->t('<b>Yes</b>'):$this->lang->t('No'),
						$args['param']
					);
		}
		$this->display_module($gb);
	}
	public function move_field($field, $pos, $dir){
		DB::StartTrans();	
		DB::Execute('UPDATE '.$this->tab.'_field SET position=%d WHERE position=%d',array($pos, $pos+$dir));
		DB::Execute('UPDATE '.$this->tab.'_field SET position=%d WHERE field=%s',array($pos+$dir, $field));
		DB::CompleteTrans();
	}
	//////////////////////////////////////////////////////////////////////////////////////////
	public function set_field_active($field, $set=true) {
		DB::Execute('UPDATE '.$this->tab.'_field SET active=%d WHERE field=%s',array($set?1:0,$field));
		return false;
	} //submit_delete_field
	//////////////////////////////////////////////////////////////////////////////////////////
	public function view_field($action = 'add', $id = null) {
		if ($this->is_back()) return false;

		$data_type = array(
			'date'=>'date', 
			'integer'=>'integer', 
			'text'=>'text',
			'long text'=>'long text'
		);
		asort($data_type);
		
		if (!isset($this->lang)) $this->lang = & $this->init_module('Base/Lang');
		$form = & $this->init_module('Libs/QuickForm');
		
		switch ($action) {
			case 'add': $form->addElement('header', null, $this->lang->t('Add new field'));
						break;
			case 'edit': $form->addElement('header', null, $this->lang->t('Edit field properties'));
						break;
		}
		$form->addElement('text', 'field', $this->lang->t('Field'));
		$form->registerRule('check_if_column_exists', 'callback', 'check_if_column_exists', &$this);
		$form->registerRule('check_if_no_id', 'callback', 'check_if_no_id', &$this);
		$form->addRule('field', $this->lang->t('Field required.'), 'required');
		$form->addRule('field', $this->lang->t('Field with this name already exists.'), 'check_if_column_exists');
		$form->addRule('field', $this->lang->t('Only letters and space are allowed.'), 'regex', '/^[a-zA-Z ]*$/');
		$form->addRule('field', $this->lang->t('"ID" as field name is not allowed.'), 'check_if_no_id');


		if ($action=='edit') {
			$row = DB::GetRow('SELECT field, type, visible, required, param, filter FROM '.$this->tab.'_field WHERE field=%s',array($id));
			$form->setDefaults($row);
			$form->addElement('static', 'select_data_type', $this->lang->t('Data Type'), $row['type']);
			$selected_data = $row['type'];
		} else {
			$form->addElement('select', 'select_data_type', $this->lang->t('Data Type'), $data_type);
			$selected_data = $form->exportValue('select_data_type');
			$form->setDefaults(array('visible'=>1));
		}
		switch($selected_data) {
			case 'text':
				if ($action=='edit') 
					$form->addElement('static', 'text_length', $this->lang->t('Length'), $row['param']);
				else {
					$form->addElement('text', 'text_length', $this->lang->t('Length'));
					$form->addRule('text_length', $this->lang->t('Field required'), 'required');
					$form->addRule('text_length', $this->lang->t('Must be a number greater than 0.'), 'regex', '/^[1-9][0-9]*$/');
				}
				break;	
		}
		$form->addElement('checkbox', 'visible', $this->lang->t('Visible'));
		$form->addElement('checkbox', 'required', $this->lang->t('Required'));
		$form->addElement('checkbox', 'filter', $this->lang->t('Filter enabled'));

		$ok_b = HTML_QuickForm::createElement('submit', 'submit_button', $this->lang->ht('OK'));
		$cancel_b = HTML_QuickForm::createElement('button', 'cancel_button', $this->lang->ht('Cancel'), $this->create_back_href());
		$form->addGroup(array($ok_b, $cancel_b));
				
		if($form->validate()) {
			if ($action=='edit') { 
				$data = $form->exportValues();
				if(!isset($data['visible']) || $data['visible'] == '') $data['visible'] = 0;
				if(!isset($data['required']) || $data['required'] == '') $data['required'] = 0;
				if(!isset($data['filter']) || $data['filter'] == '') $data['filter'] = 0;
				
				foreach($data as $key=>$val)
					$data[$key] = htmlspecialchars($val);
		
				DB::StartTrans();	
				DB::Execute('UPDATE '.$this->tab.'_field SET field=%s, visible=%d, required=%d, filter=%d WHERE field=%s',
							array($data['field'], $data['visible'], $data['required'], $data['filter'], $id));
				DB::Execute('UPDATE '.$this->tab.'_data SET field=%s WHERE field=%s',
							array($data['field'], $id));
				DB::Execute('UPDATE '.$this->tab.'_edit_history_data SET field=%s WHERE field=%s',
							array($data['field'], $id));
				DB::CompleteTrans();
				return false;
			} else { 
				if ($form->process(array(&$this, 'submit_add_field')))
					return false;				
			}
		}
		$form->display();
		return true;
	}
	public function check_if_no_id($arg){
		return !preg_match('/^[iI][dD]$/',$arg);
	}	
	public function check_if_column_exists($arg){
		$this->init(true);
		foreach($this->table_rows as $field=>$args)
			if (strtolower($args['name']) == strtolower($arg))
				return false;
		return true;	
	}	

	public function submit_add_field($data) {
		$param = '';
		switch($data['select_data_type']) {
			case 'text':
				$param = $data['text_length'];
				break;
		}
		if(!isset($data['visible']) || $data['visible'] == '') $data['visible'] = 0;
		if(!isset($data['required']) || $data['required'] == '') $data['required'] = 0;
		if(!isset($data['filter']) || $data['filter'] == '') $data['filter'] = 0;
		
		foreach($data as $key=>$val)
			$data[$key] = htmlspecialchars($val);

		DB::StartTrans();
		$max = DB::GetOne('SELECT MAX(position) FROM '.$this->tab.'_field')+1;
		DB::Execute('INSERT INTO '.$this->tab.'_field(field, type, visible, required, param, position, filter)'.
					' VALUES(%s, %s, %d, %d, %s, %d, %d)', 
					array($data['field'], $data['select_data_type'], $data['visible'], $data['required'], $param, $max, $data['filter']));
		DB::CompleteTrans();
		return true;
	} //submit_add_field
	public function show_data_table() {
		$this->init();
		if (!Base_AclCommon::i_am_admin()) {
			print($this->lang->t('You don\'t have permission to access this data.'));
		}
		$gb = & $this->init_module('Utils/GenericBrowser', null, $this->tab);
		
		$table_columns = array(array('name'=>'Active', 'width'=>1));
		
		$table_columns_SQL = array();
		foreach($this->table_rows as $field => $args) {
			if ($field === 'id') continue;
			$table_columns[] = array('name'=>$args['name'], 'order'=>$field);
			array_push($table_columns_SQL, 'e.'.$field);
		}
			
		$table_columns_SQL = join(', ', $table_columns_SQL);
		$gb->set_table_columns( $table_columns );
		
		$get_fields = array();
		
		//read database
		
		$records = Base_RecordBrowserCommon::get_records($this->tab, null, true);
		foreach ($records as $row) {
			$gb_row = & $gb->get_new_row();
			$row_data = array($row['active']?$this->lang->t('Yes'):$this->lang->t('No'));
			foreach($this->table_rows as $field => $args)
				$row_data[] = $row[$field];
			$gb_row->add_data_array($row_data);
			if (!$row['active']) $gb_row->add_action($this->create_callback_href(array($this,'set_active'),array($row['id'],true)),$this->lang->t('Activate'));
			else $gb_row->add_action($this->create_callback_href(array($this,'set_active'),array($row['id'],false)),$this->lang->t('Deactivate'));
			$gb_row->add_action($this->create_callback_href(array($this,'view_edit_history'),array($row['id'])),$this->lang->t('View edit history'),null,'info');
		}
		$this->display_module($gb);
	} //show data deactivated
	public function view_edit_history($id){
		if ($this->is_back()) return false;
		$this->init();
		$gb = & $this->init_module('Utils/GenericBrowser', null, $this->tab);
		
		$table_columns = array(	array('name'=>$this->lang->t('Action'), 'width'=>1, 'wrapmode'=>'nowrap'),
								array('name'=>$this->lang->t('User'), 'width'=>1, 'wrapmode'=>'nowrap'),
								array('name'=>$this->lang->t('Date'), 'width'=>1, 'wrapmode'=>'nowrap'));
		
		$table_columns_SQL = array();
		foreach($this->table_rows as $field => $args) {
			if ($field=='id') continue;
			$table_columns[] = array('name'=>$args['name']);
			array_push($table_columns_SQL, 'c.'.$field);
		}
		$table_columns_SQL = join(', ', $table_columns_SQL);
		$gb->set_table_columns( $table_columns );
		
		$created = Base_RecordBrowserCommon::get_record($this->tab, $id);
		$created['created_by_login'] = DB::GetOne('SELECT login FROM user_login WHERE id=%d',array($created['created_by']));
		$row_data = array(
						array('value'=>'--','style'=>'border-top: 1px solid black;'),
						array('value'=>'--','style'=>'border-top: 1px solid black;'),
						array('value'=>$this->lang->t('Current'),'style'=>'border-top: 1px solid black;')
						);
		foreach($this->table_rows as $field => $args) {
			if ($field=='id') continue;
			$row_data[] = array('value'=>$created[$field],'style'=>'font-weight: bold; border-top: 1px solid black;','hint'=>$created[$field]);
		}
		$edit_history = array($row_data);
		$ret = DB::Execute('SELECT ul.login, c.id, c.edited_on, c.edited_by FROM '.$this->tab.'_edit_history AS c LEFT JOIN user_login AS ul ON ul.id=c.edited_by WHERE c.'.$this->tab.'_id=%d ORDER BY edited_on DESC',array($id));
		$no_edits = true;
		$counter = 0;
		while ($row=$ret->FetchRow()) {
			$no_edits = false;
			foreach($created as $k=>$v)
				$new_created[$k] = array('value'=>$v,'style'=>'color: #CCCCCC;','hint'=>$v);
			$row_data = array('Edited',$row['login'],$row['edited_on']);
			$ret2 = DB::Execute('SELECT * FROM '.$this->tab.'_edit_history_data WHERE edit_id=%d',array($row['id']));
			while($row2 = $ret2->FetchRow()) {
				$new_created[$row2['field']]['style'] = 'font-weight: bold; background-color: #EFEFFF;';
				$created[$row2['field']] = $row2['old_value'];
			}
			foreach($this->table_rows as $field => $args) {
				if ($field=='id') continue;
				$row_data[] = $new_created[$field];
			}
			array_unshift($edit_history, $row_data);
			$counter++;
		}
		$row_data = array('Created',$created['created_by_login'],$created['created_on']);
		foreach($this->table_rows as $field => $args) {
			if ($field=='id') continue;
			$row_data[] = array('value'=>$created[$field],'style'=>'font-weight: bold;','hint'=>$created[$field]);
		}
		array_unshift($edit_history, $row_data);
		foreach($edit_history as $row_data) {
			$gb_row = & $gb->get_new_row();
			$gb_row->add_data_array($row_data);
			if ($counter > 0) $gb_row->add_action($this->create_callback_href(array($this, 'restore_record'), array($row_data, $id)),'Restore');
			$counter--;
		}
		
		$this->display_module($gb);
		Base_ActionBarCommon::add('back',$this->lang->t('Back'),$this->create_back_href());
		return true;
	}
	private function add_recent_entry($user_id ,$id){
		DB::StartTrans();
		DB::Execute('DELETE FROM '.$this->tab.'_recent WHERE user_id = %d AND '.$this->tab.'_id = %d',
					array($user_id,
					$id));
		$ret = DB::SelectLimit('SELECT visited_on FROM '.$this->tab.'_recent WHERE user_id = %d ORDER BY visited_on DESC',
					9,
					-1,
					array($user_id));
		while($row_temp = $ret->FetchRow()) $row = $row_temp;
		if (isset($row)) {
			DB::Execute('DELETE FROM '.$this->tab.'_recent WHERE user_id = %d AND visited_on < %T',
						array($user_id,	
						$row['visited_on']));
		}
		DB::Execute('INSERT INTO '.$this->tab.'_recent VALUES (%d, %d, %T)',
					array($id,
					$user_id,
					date('Y-m-d G:i:s')));
		DB::CompleteTrans();
	}
	
	public function set_active($id, $state=true){
		DB::Execute('UPDATE '.$this->tab.' SET active=%d WHERE id=%d',array($state,$id));
		return false;
	}
	public function restore_record($data, $id) {
		$this->init();
		$i = 3;
		$values = array();
		foreach($this->table_rows as $field => $args) {
			if ($field=='id') continue;
			$values[$args['id']] = $data[$i++]['value'];
		}
		$this->update_record_data($id,$values);
		return false;
	}
	public function create_linked_label($tab, $col, $id){
		$label = DB::GetOne('SELECT value FROM '.$tab.'_data WHERE field=%s', array($col));
		return '<a '.$this->create_href(array('box_main_module'=>'Base_RecordBrowser', 'box_main_constructor_arguments'=>array($tab), 'tab'=>$tab, 'id'=>$id, 'action'=>'view')).'>'.$label.'</a>';
	}
}
?>
