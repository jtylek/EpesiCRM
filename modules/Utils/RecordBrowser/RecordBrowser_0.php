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

class Utils_RecordBrowser extends Module {
	private $table_rows = array();
	private $lang;
	private $SQL_chars = array('text'=>'%s', 'long text'=>'%s', 'date'=>'%D', 'integer'=>'%d');
	private $tab;
	private $browse_mode;
	private $display_callback_table = array();
	private $QFfield_callback_table = array();
	private $requires = array();
	private $recent = 0;
	private $caption = '';
	private $icon = '';
	private $favorites = false;
	private $full_history = true;
	private $action = 'Browsing';
	private $crits = array();
		
	public function get_val($field, $val, $id) {
		if (isset($this->display_callback_table[$field])) {
			return call_user_func($this->display_callback_table[$field], $val, $id);
		} else {
			return $val;
		}
	}

	public function construct($tab = null) {
		if (!isset($tab))
			trigger_error('RecordBrowser did not receive string name for the table '.$this->get_parent_type().'.<br>Use $this->init_module(\'Utils/RecordBrowser\',\'table name here\');',E_USER_ERROR);
		$this->tab = $tab;
	}
	
	public function init($admin=false) {
		if (!isset($this->lang)) $this->lang = $this->init_module('Base/Lang');
		$params = DB::GetRow('SELECT caption, icon, recent, favorites, full_history FROM recordbrowser_table_properties WHERE tab=%s', array($this->tab));
		if ($params==false) trigger_error('There is no such recordSet as '.$this->tab.'.', E_USER_ERROR);
		list($this->caption,$this->icon,$this->recent,$this->favorites,$this->full_history) = $params;
		
		$this->table_rows = Utils_RecordBrowserCommon::init($this->tab, $admin);
		$this->requires = array();
		$this->display_callback_table = array();
		$this->QFfield_callback_table = array();
		$ret = DB::Execute('SELECT * FROM '.$this->tab.'_callback');
		while ($row = $ret->FetchRow())
			if ($row['freezed']==1) $this->display_callback_table[$row['field']] = array($row['module'], $row['func']);
			else $this->QFfield_callback_table[$row['field']] = array($row['module'], $row['func']);
		$ret = DB::Execute('SELECT * FROM '.$this->tab.'_require');
		while ($row = $ret->FetchRow()) {
			if (!isset($this->requires[$row['field']]))
				$this->requires[$row['field']] = array();
			if (!isset($this->requires[$row['field']][$row['req_field']]))
				$this->requires[$row['field']][$row['req_field']] = array($row['value']);
			else 
				array_unshift($this->requires[$row['field']][$row['req_field']], $row['value']);
		}
	}
	// BODY //////////////////////////////////////////////////////////////////////////////////////////////////////
	public function body() {
		if(Base_AclCommon::i_am_user()) {
			$this->init();
			if (isset($_REQUEST['tab'])) {
				$this->tab = $_REQUEST['tab'];
				$this->call_callback_href(array($this,'view_entry'),array($_REQUEST['action'], $_REQUEST['id'], isset($_REQUEST['defaults'])?$_REQUEST['defaults']:array()));
			} else {
				Base_ActionBarCommon::add('add',$this->lang->t('New'), $this->create_callback_href(array($this,'view_entry'),array('add')));
				$this->browse_mode = $this->get_module_variable('browse_mode', 'all');
				if (($this->browse_mode=='recent' && $this->recent==0) || ($this->browse_mode=='favorites' && !$this->favorites)) $this->set_module_variable('browse_mode', $this->browse_mode='all'); 
				if ($this->browse_mode!=='recent' && $this->recent>0) Base_ActionBarCommon::add('history',$this->lang->t('Recent'), $this->create_callback_href(array($this,'switch_view'),array('recent')));
				if ($this->browse_mode!=='all') Base_ActionBarCommon::add('report',$this->lang->t('All'), $this->create_callback_href(array($this,'switch_view'),array('all')));
				if ($this->browse_mode!=='favorites' && $this->favorites) Base_ActionBarCommon::add('favorites',$this->lang->t('Favorites'), $this->create_callback_href(array($this,'switch_view'),array('favorites')));

				$filters = $this->show_filters();
				ob_start();
				$this->show_data($this->crits);
				$table = ob_get_contents();
				ob_end_clean();

				$theme = $this->init_module('Base/Theme');
				$theme->assign('filters', $filters);
				$theme->assign('table', $table);
				$theme->assign('caption', $this->caption);
				$theme->assign('icon', $this->icon);
				$theme->display('Browsing_records');
			}
		} else
			print($this->lang->t('You must log in to view this data.'));
	}
	public function switch_view($mode){
		$this->browse_mode = $mode;
		$this->set_module_variable('browse_mode', $mode);
	}
	//////////////////////////////////////////////////////////////////////////////////////////
	public function show_filters($filters_set = array(), $f_id='') {
		$ret = DB::Execute('SELECT field FROM '.$this->tab.'_field WHERE filter=1');
		$filters_all = array();
		while($row = $ret->FetchRow())
			if (!isset($filters_set[$row['field']]) || $filters_set[$row['field']]) {
				$filters_all[] = $row['field'];
				if (isset($filters_set[$row['field']])) unset($filters_set[$row['field']]);
			}
		foreach($filters_set as $k=>$v)
			if ($v) $filters_all[] = $k;
		if (empty($filters_all)) {
			$this->crits = array();
			return '';
		}
		
		$form = $this->init_module('Libs/QuickForm', null, $this->tab.'filters');
		$filters = array();
		foreach ($filters_all as $filter) {
			if ($this->table_rows[$filter]['type'] == 'select' || $this->table_rows[$filter]['type'] == 'multiselect') {
				$arr = array('__NULL__'=>'--');
				list($tab, $col) = explode('::',$this->table_rows[$filter]['param']);
				if ($tab=='__COMMON__') {
					$arr = Utils_CommonDataCommon::get_array($col);
				} else {
					$ret2 = DB::Execute('SELECT '.$tab.'_id, value FROM '.$tab.'_data WHERE field=%s', array($col));
					while ($row2 = $ret2->FetchRow()) $arr[$row2[$tab.'_id']] = $row2['value'];
				}
			} else {
				$ret2 = DB::Execute('SELECT value FROM '.$this->tab.'_data WHERE field=%s ORDER BY value', array($filter));
				$arr = array('__NULL__'=>'--');
				while ($row2 = $ret2->FetchRow()) $arr[$row2['value']] = $row2['value'];
			}
			$form->addElement('select', str_replace(' ','_',$filter), $filter, $arr);
			$filters[] = str_replace(' ','_',$filter);
		}
		$form->addElement('submit', 'submit', 'Show');
		$this->crits = array();
		if ($form->validate()) {
			$vals = $form->exportValues();
			unset($vals['submit']);
			unset($vals['submited']);
			foreach($vals as $k=>$v)
				if ($v!=='__NULL__') $this->crits[str_replace('_',' ',$k)] = $v;
		}
		$theme = $this->init_module('Base/Theme');
		$form->assign_theme('form',$theme);
		$theme->assign('filters', $filters);
		$theme->assign('id', $f_id);
		return $this->get_html_of_module($theme, 'Filter', 'display');
	}
	//////////////////////////////////////////////////////////////////////////////////////////
	public function show_data($crits = array(), $cols = array(), $order = array(), $fs_links = false, $admin = false, $special = false) {
		$this->init();
		$this->action = 'Browse';
		if (!Base_AclCommon::i_am_admin() && $admin) {
			print($this->lang->t('You don\'t have permission to access this data.'));
		}
		$gb = $this->init_module('Utils/GenericBrowser', null, $this->tab);
		
		if ($special)
			$table_columns = array(array('name'=>'Add', 'width'=>1, 'order'=>'Add'));
		elseif (!$admin && $this->favorites)
			$table_columns = array(array('name'=>'Fav', 'width'=>1, 'order'=>'Fav'));
		$table_columns_SQL = array();
		$quickjump = DB::GetOne('SELECT quickjump FROM recordbrowser_table_properties WHERE tab=%s', array($this->tab));
		foreach($this->table_rows as $field => $args) {
			if ($field === 'id') continue;
			if (!$args['visible'] && (!isset($cols[$args['name']]) || $cols[$args['name']] === false)) continue;
			if (isset($cols[$args['name']]) && $cols[$args['name']] === false) continue;
			$arr = array('name'=>$args['name'], 'order'=>$field);
			if ($quickjump!=='' && $args['name']===$quickjump) $arr['quickjump'] = $args['name'];
			$table_columns[] = $arr;
			array_push($table_columns_SQL, 'e.'.$field);
		}

		$table_columns_SQL = join(', ', $table_columns_SQL);
		if ($this->browse_mode == 'recent')
			$table_columns[] = array('name'=>$this->lang->t('Visited on')); 
		$gb->set_table_columns( $table_columns );
		$gb->set_default_order( $order );
		$crits = array_merge($crits, $gb->get_search_query(true));

		$records = Utils_RecordBrowserCommon::get_records($this->tab, $crits, $admin);
		if ($admin) $this->browse_mode = 'all'; 
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
		if ($special) $rpicker_ind = array();
		foreach ($records as $row) {
			$gb_row = $gb->get_new_row();
			if (!$admin && $this->favorites) {
				$isfav = DB::GetOne('SELECT user_id FROM '.$this->tab.'_favorite WHERE user_id=%d AND '.$this->tab.'_id=%d', array(Base_UserCommon::get_my_user_id(), $row['id']));
				$row_data = array('<a '.Utils_TooltipCommon::open_tag_attrs(($isfav?$this->lang->t('This item is on your favourites list<br>Click to remove it from your favorites'):$this->lang->t('Click to add this item to favorites'))).' '.$this->create_callback_href(array($this,($isfav?'remove_from_favs':'add_to_favs')), array($row['id'])).'><img border="0" src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','star_'.($isfav==false?'no':'').'fav.png').'" /></a>');
			} else $row_data = array();
			if ($special) { 
				$func = $this->get_module_variable('format_func');
				$element = $this->get_module_variable('element');
				$row_data = array('<a href="javascript:addto_'.$element.'('.$row['id'].', \''.call_user_func($func, $row['id']).'\');"><img src="null"  border="0" name="leightbox_rpicker_'.$element.'_'.$row['id'].'" /></a>');
				$rpicker_ind[] = $row['id'];
			}
			
			foreach($this->table_rows as $field => $args)
				if (($args['visible'] && !isset($cols[$args['name']])) || (isset($cols[$args['name']]) && $cols[$args['name']] === true)) {
					$ret = $row[$field];
					if ($args['type']=='select' || $args['type']=='multiselect') {
						if (empty($row[$field])) {
							$row_data[] = '--';
							continue;
						}
						list($tab, $col) = explode('::',$args['param']);
						$arr = $row[$field];
						if (!is_array($arr)) $arr = array($arr);
						if ($tab=='__COMMON__') $data = Utils_CommonDataCommon::get_array($col);
						$ret = '';
						$first = true;
						foreach ($arr as $k=>$v){
							if ($first) $first = false;
							else $ret .= ', ';
							if ($tab=='__COMMON__') $ret .= $data[$v];
							else $ret .= Utils_RecordBrowserCommon::create_linked_label($tab, $col, $v, $special);
						}
					}
					if ($args['type']=='commondata') {
						if (!$row[$field]) {
							$ret = '';
						} else {
							$arr = explode('::',$args['param']);
							$path = array_shift($arr);
							foreach($arr as $v) $path .= '/'.$row[$v];
							$path .= '/'.$row[$field];
							$ret = Utils_CommonDataCommon::get_value($path);
						}
					}
					if ($special) $row_data[] = $ret;
					else $row_data[] = $this->get_val($field, $ret, $row['id']);
				}
			if ($this->browse_mode == 'recent')
				$row_data[] = $row['visited_on'];
			$gb_row->add_data_array($row_data);
			if (!$special) {
				if ($fs_links===false) {
					$gb_row->add_action($this->create_callback_href(array($this,'view_entry'),array('view',$row['id'])),$this->lang->t('View'));
					$gb_row->add_action($this->create_callback_href(array($this,'view_entry'),array('edit',$row['id'])),$this->lang->t('Edit'));
					if ($admin) {
						if (!$row['active']) $gb_row->add_action($this->create_callback_href(array($this,'set_active'),array($row['id'],true)),$this->lang->t('Activate'), null, 'active-off');
						else $gb_row->add_action($this->create_callback_href(array($this,'set_active'),array($row['id'],false)),$this->lang->t('Deactivate'), null, 'active-on');
						$gb_row->add_action($this->create_callback_href(array($this,'view_edit_history'),array($row['id'])),$this->lang->t('View edit history'),null,'history');
					} else 
					$gb_row->add_action($this->create_confirm_callback_href($this->lang->t('Are you sure you want to delete this record?'),array('Utils_RecordBrowserCommon','delete_record'),array($this->tab, $row['id'])),$this->lang->t('Delete'));
				} else {
					$gb_row->add_action($this->create_href(array('box_main_module'=>'Utils_RecordBrowser', 'box_main_constructor_arguments'=>array($this->tab), 'tab'=>$this->tab, 'id'=>$row['id'], 'action'=>'view')),$this->lang->t('View'));
					$gb_row->add_action($this->create_href(array('box_main_module'=>'Utils_RecordBrowser', 'box_main_constructor_arguments'=>array($this->tab), 'tab'=>$this->tab, 'id'=>$row['id'], 'action'=>'edit')),$this->lang->t('Edit'));
					$gb_row->add_action($this->create_confirm_callback_href($this->lang->t('Are you sure you want to delete this record?'),array('Utils_RecordBrowserCommon','delete_record'),array($this->tab, $row['id'])),$this->lang->t('Delete'));
				}
			}
			$gb_row->add_info(Utils_RecordBrowserCommon::get_html_record_info($this->tab, $row['id']));
		}
		if ($special) {
			$this->set_module_variable('rpicker_ind',$rpicker_ind);
			return $this->get_html_of_module($gb, null, 'automatic_display');
		} else $this->display_module($gb, null, 'automatic_display');
	}
	//////////////////////////////////////////////////////////////////////////////////////////
	public function view_entry($mode='view', $id = null, $defaults = array()) {
		$js = true;
		if ($this->is_back())
			return false;
		$this->init();
		switch ($mode) {
			case 'add': $this->action = 'New record'; break;
			case 'edit': $this->action = 'Edit record'; break;
			case 'view': $this->action = 'View record'; break;
		}
		$theme = $this->init_module('Base/Theme');

		if($mode!='add')
			Utils_RecordBrowserCommon::add_recent_entry($this->tab, Base_UserCommon::get_my_user_id(),$id);

		$tb = $this->init_module('Utils/TabbedBrowser');
		$form = $this->init_module('Libs/QuickForm',null, $mode);
		if($mode=='add')
			$form->setDefaults($defaults);

		$this->prepare_view_entry_details($mode, $id, $form);

		if ($form->validate()) {
			$values = $form->exportValues();
			$dpm = DB::GetOne('SELECT data_process_method FROM recordbrowser_table_properties WHERE tab=%s', array($this->tab));
			if ($dpm!=='') {
				$method = explode('::',$dpm);
				if (is_callable($method)) $values = call_user_func($method, $values, $mode);
			}
			if ($mode=='add') {
				Utils_RecordBrowserCommon::new_record($this->tab, $values);
			} else {
				$this->update_record_data($id,$values);
			}
			return false;
		}

		if ($mode=='view') { 
			Base_ActionBarCommon::add('edit', $this->lang->ht('Edit'), $this->create_callback_href(array($this,'view_entry'), array('edit',$id)));
			Base_ActionBarCommon::add('back', $this->lang->ht('Back'), $this->create_back_href());
		} else {
			Base_ActionBarCommon::add('save', $this->lang->ht('Save'), $form->get_submit_form_href());
			Base_ActionBarCommon::add('delete', $this->lang->ht('Cancel'), $this->create_back_href());
		}

		if ($mode!='add') {
			$isfav = DB::GetOne('SELECT user_id FROM '.$this->tab.'_favorite WHERE user_id=%d AND '.$this->tab.'_id=%d', array(Base_UserCommon::get_my_user_id(), $id));
			$theme -> assign('info_tooltip', '<a '.Utils_TooltipCommon::open_tag_attrs(Utils_RecordBrowserCommon::get_html_record_info($this->tab, $id)).'><img border="0" src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','info.png').'" /></a>');
			$row_data = array();
			$fav = DB::GetOne('SELECT user_id FROM '.$this->tab.'_favorite WHERE user_id=%d AND '.$this->tab.'_id=%s', array(Base_UserCommon::get_my_user_id(), $id));
			
			if ($this->favorites)
				$theme -> assign('fav_tooltip', '<a '.Utils_TooltipCommon::open_tag_attrs(($isfav?$this->lang->t('This item is on your favourites list<br>Click to remove it from your favorites'):$this->lang->t('Click to add this item to favorites'))).' '.$this->create_callback_href(array($this,($isfav?'remove_from_favs':'add_to_favs')), array($id)).'><img border="0" src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','star_'.($isfav==false?'no':'').'fav.png').'" /></a>');
		}

		if ($mode=='view') $form->freeze();
		if(!isset($renderer)) $renderer = new HTML_QuickForm_Renderer_TCMSArraySmarty(); 
		$form->accept($renderer);
		$data = $renderer->toArray();
		
		print($data['javascript'].'<form '.$data['attributes'].'>'.$data['hidden']."\n");

		$last_page = DB::GetOne('SELECT MIN(position) FROM '.$this->tab.'_field WHERE type = \'page_split\' AND field != \'General\'');
		$label = DB::GetOne('SELECT field FROM '.$this->tab.'_field WHERE position=%s', array($last_page));
		$this->view_entry_details(1, $last_page, $data, $theme, true);
		$ret = DB::Execute('SELECT position, field FROM '.$this->tab.'_field WHERE type = \'page_split\' AND position > %d', array($last_page));
		$row = true;
		if ($mode=='view')
			print("</form>\n");
		while ($row) {
			$row = $ret->FetchRow();
			if ($row) $pos = $row['position'];
			else $pos = DB::GetOne('SELECT MAX(position) FROM '.$this->tab.'_field')+1;
			if ($pos - $last_page>1) $tb->set_tab($this->lang->t($label),array($this,'view_entry_details'), array($last_page, $pos+1, $data), $js);
			$last_page = $pos;
			if ($row) $label = $row['field'];
		}
		if ($mode!='add' && $mode!='edit') {
			$record = Utils_RecordBrowserCommon::get_record($this->tab, $id, true);
			$ret = DB::Execute('SELECT * FROM '.$this->tab.'_addon');
			while ($row = $ret->FetchRow()) {
				$mod = $this->init_module($row['module']);
				$tb->set_tab($this->lang->t($row['label']),array($this, 'display_module'), array($mod, array($record), $row['func']), $js);
			}
		}
		$this->display_module($tb);
		if ($mode=='add' || $mode=='edit') print("</form>\n");
		$tb->tag();

		return true;
	} //view_entry
	
	public function view_entry_details($from, $to, $data, $theme=null, $main_page = false){
		if ($theme==null) $theme = $this->init_module('Base/Theme');
		$fields = array();
		foreach($this->table_rows as $field => $args) 
			if ($args['position'] >= $from && ($to == -1 || $args['position'] < $to)) 
			{	
				if (!isset($data[$args['id']])) $data[$args['id']] = array('label'=>'', 'html'=>'');
				$fields[$args['id']] = array(	'label'=>$data[$args['id']]['label'],
												'element'=>$args['id'],
												'html'=>$data[$args['id']]['html'],
												'error'=>isset($data[$args['id']]['error'])?$data[$args['id']]['error']:null,
												'required'=>isset($args['required'])?$args['required']:null,
												'type'=>$args['type']);
			}
		$theme->assign('fields', $fields);
		$theme->assign('Form_data', $data);
		$theme->assign('required_note', $this->lang->t('Indicates required fields.'));
		if ($main_page) $tpl = DB::GetOne('SELECT tpl FROM recordbrowser_table_properties WHERE tab=%s', array($this->tab));
		else $tpl = '';
		$theme->display(($tpl!=='')?$tpl:'View_entry', ($tpl!==''));
	}

	public function prepare_view_entry_details($mode, $id, $form){
		if ($mode!=='add') $records = Utils_RecordBrowserCommon::get_records($this->tab);
		$init_js = '';
		foreach($this->table_rows as $field => $args){
			if (isset($this->QFfield_callback_table[$field])) {
				call_user_func($this->QFfield_callback_table[$field], $form, $args['id'], $this->lang->t($args['name']), $mode, $mode=='add'?0:$records[$id][$field]);
				continue;
			}
			if ($mode!=='add' && $mode!=='edit') $records[$id][$field] = $this->get_val($field, $records[$id][$field], $id);
			if (isset($this->requires[$field])) 
				if ($mode=='add' || $mode=='edit') {
					foreach($this->requires[$field] as $k=>$v) {
						if (!is_array($v)) $v = array($v);
						$r_id = strtolower(str_replace(' ','_',$k));
						$js = 	'Event.observe(\''.$r_id.'\',\'change\', onchange_'.$args['id'].'__'.$k.');'.
								'function onchange_'.$args['id'].'__'.$k.'() {'.
								'if (0';
						foreach ($v as $w)
							$js .= ' || document.forms[\''.$form->getAttribute('name').'\'].'.$r_id.'.value == \''.$w.'\'';
						$js .= 	') { '.
								'document.forms[\''.$form->getAttribute('name').'\'].'.$args['id'].'.style.display = \'inline\';'.
								'document.getElementById(\'_'.$args['id'].'__label\').style.display = \'inline\';'.
								'} else { '.
								'document.forms[\''.$form->getAttribute('name').'\'].'.$args['id'].'.style.display = \'none\';'.
								'document.getElementById(\'_'.$args['id'].'__label\').style.display = \'none\';'.
								'}};';
						$init_js .= 'onchange_'.$args['id'].'__'.$k.'();';
						eval_js($js);
					}
				} else {
					$hidden = false;
					foreach($this->requires[$field] as $k=>$v) {
						if (!is_array($v)) $v = array($v);
						$r_id = strtolower(str_replace(' ','_',$k));
						foreach ($v as $w) {
							if ($records[$id][$k] != $w) {
								$hidden = true;
								break;
							}
						}
						if ($hidden) break;
					}
					if ($hidden) continue;
				}
			switch ($args['type']) {
				case 'integer':		$form->addElement('text', $args['id'], '<span id="_'.$args['id'].'__label">'.$this->lang->t($args['name']).'</span>', array('id'=>$args['id'], 'style'=>'width: 99%'));
									$form->addRule($args['id'], $this->lang->t('Only numbers are allowed.'), 'numeric');
									if ($mode!=='add') $form->setDefaults(array($args['id']=>$records[$id][$field]));
									break;
				case 'text':		if ($mode!=='view') $form->addElement('text', $args['id'], '<span id="_'.$args['id'].'__label">'.$this->lang->t($args['name']).'</span>', array('id'=>$args['id'], 'maxlength'=>$args['param'], 'style'=>'width: 99%'));
									else $form->addElement('static', $args['id'], '<span id="_'.$args['id'].'__label">'.$this->lang->t($args['name']).'</span>', array('id'=>$args['id']));
									$form->addRule($args['id'], $this->lang->t('Maximum length for this field is '.$args['param'].'.'), 'maxlength', $args['param']);
									if ($mode!=='add') $form->setDefaults(array($args['id']=>$records[$id][$field]));
									break;
				case 'long text':	$form->addElement('textarea', $args['id'], '<span id="_'.$args['id'].'__label">'.$this->lang->t($args['name']).'</span>', array('id'=>$args['id']));
									if ($mode!=='add') $form->setDefaults(array($args['id']=>$records[$id][$field]));
									break;
				case 'date':		$form->addElement('datepicker', $args['id'], '<span id="_'.$args['id'].'__label">'.$this->lang->t($args['name']).'</span>', array('id'=>$args['id']));
									if ($mode!=='add') $form->setDefaults(array($args['id']=>$records[$id][$field]));
									break;
				case 'commondata':	$param = explode('::',$args['param']);
									foreach ($param as $k=>$v) if ($k!==0) $param[$k] = strtolower(str_replace(' ','_',$v));
									$form->addElement($args['type'], $args['id'], '<span id="_'.$args['id'].'__label">'.$this->lang->t($args['name']).'</span>', $param, array('empty_option'=>$args['required'], 'id'=>$args['id']));
									if ($mode!=='add') $form->setDefaults(array($args['id']=>$records[$id][$field]));
									break;
				case 'select':		
				case 'multiselect':	$comp = array();
									if (!$args['required'] && $args['type']==='select') $comp[''] = '--';
									list($tab, $col) = explode('::',$args['param']);
									if ($tab=='__COMMON__') {
										$data = Utils_CommonDataCommon::get_array($col);
										if (!is_array($data)) $data = array();
									}
									if ($mode=='add' || $mode=='edit') {
										if ($tab=='__COMMON__')
											$comp = $comp+$data;
										else {
											$ret = DB::Execute('SELECT * FROM '.$tab.'_data WHERE field=%s ORDER BY value', array($col));
											vprintf('SELECT * FROM '.$tab.'_data WHERE field=%s ORDER BY value', array($col));
											while ($row = $ret->FetchRow()) $comp[$row[$tab.'_id']] = $row['value'];
										}
										$form->addElement($args['type'], $args['id'], '<span id="_'.$args['id'].'__label">'.$this->lang->t($args['name']).'</span>', $comp, array('id'=>$args['id']));
										if ($mode!=='add') $form->setDefaults(array($args['id']=>$records[$id][$field]));
									} else {
										$form->addElement('static', $args['id'], '<span id="_'.$args['id'].'__label">'.$this->lang->t($args['name']).'</span>', array('id'=>$args['id']));
										if (isset($this->display_callback_table[$field])) {
											$form->setDefaults(array($args['id']=>call_user_func($this->display_callback_table[$field], $records[$id][$field])));
											continue;
										}
										if (!is_array($records[$id][$field])) {
											if ($tab=='__COMMON__') $form->setDefaults(array($args['id']=>$data[$records[$id][$field]]));
											else $form->setDefaults(array($args['id']=>Utils_RecordBrowserCommon::create_linked_label($tab, $col, $records[$id][$field])));
										} else {
											$def = '';
											$first = true;
											foreach($records[$id][$field] as $k=>$v){
												if ($first) $first = false;
												else $def .= ', ';
												if ($tab=='__COMMON__') $def .= $data[$v];
												else $def .= Utils_RecordBrowserCommon::create_linked_label($tab, $col, $v);
											}
											$form->setDefaults(array($args['id']=>$def));
										}
									}
									break;
			}
			if ($args['required'])
				$form->addRule($args['id'], $this->lang->t('Field required'), 'required');
		}
		eval_js($init_js);	
	}
	public function add_to_favs($id) {
		DB::Execute('INSERT INTO '.$this->tab.'_favorite (user_id, '.$this->tab.'_id) VALUES (%d, %d)', array(Base_UserCommon::get_my_user_id(), $id));
	}
	public function remove_from_favs($id) {
		DB::Execute('DELETE FROM '.$this->tab.'_favorite WHERE user_id=%d AND '.$this->tab.'_id=%d', array(Base_UserCommon::get_my_user_id(), $id));
	}
	public function update_record_data($id,$values) {
		DB::StartTrans();	
		$this->init();
		$records = Utils_RecordBrowserCommon::get_records($this->tab, null, true);
		$diff = array();
		foreach($this->table_rows as $field => $args){
			if ($args['id']=='id') continue;
			if (!isset($values[$args['id']])) $values[$args['id']] = '';
			if ($records[$id][$field]!==$values[$args['id']]) {
				DB::StartTrans();
				$val = DB::GetOne('SELECT value FROM '.$this->tab.'_data WHERE '.$this->tab.'_id=%d AND field=%s',array($id, $field));
				if ($val!==false) DB::Execute('DELETE FROM '.$this->tab.'_data WHERE '.$this->tab.'_id=%d AND field=%s',array($id, $field));
				if ($values[$args['id']] !== '') {
					if (!is_array($values[$args['id']])) $values[$args['id']] = array($values[$args['id']]);
					foreach ($values[$args['id']] as $v) 
						DB::Execute('INSERT INTO '.$this->tab.'_data(value, '.$this->tab.'_id, field) VALUES (%s, %d, %s)',array($v, $id, $field));
				}
				DB::CompleteTrans();
				$diff[$field] = $records[$id][$field];
			}
		}
		if (!empty($diff)) {
			DB::Execute('INSERT INTO '.$this->tab.'_edit_history(edited_on, edited_by, '.$this->tab.'_id) VALUES (%T,%d,%d)', array(date('Y-m-d G:i:s'), Base_UserCommon::get_my_user_id(), $id));
			$edit_id = DB::Insert_ID(''.$this->tab.'_edit_history','id');
			foreach($diff as $k=>$v) {
				if (!is_array($v)) $v = array($v);
				foreach($v as $c)  
					DB::Execute('INSERT INTO '.$this->tab.'_edit_history_data(edit_id, field, old_value) VALUES (%d,%s,%s)', array($edit_id, $k, $c));
			}
		}
		DB::CompleteTrans();
	}
	//////////////////////////////////////////////////////////////////////////////////////////
	public function admin() {
		$this->init();
		$tb = $this->init_module('Utils/TabbedBrowser');
		
		$tb->set_tab($this->lang->t('Manage Records'),array($this, 'show_data'), array(array(), array(), array(), false, true) );
		$tb->set_tab($this->lang->t('Manage Fields'),array($this, 'setup_loader') );
		
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
		$form = $this->init_module('Libs/QuickForm', null, 'edit_page');
		
		$form->addElement('header', null, $this->lang->t('Edit page properties'));
		$form->addElement('text', 'label', $this->lang->t('Label'));
		$form->registerRule('check_if_column_exists', 'callback', 'check_if_column_exists', $this);
		$form->registerRule('check_if_no_id', 'callback', 'check_if_no_id', $this);
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
		$gb = $this->init_module('Utils/GenericBrowser', null, 'fields');
		$gb->set_table_columns(array(
			array('name'=>$this->lang->t('Field'), 'width'=>20),
			array('name'=>$this->lang->t('Type'), 'width'=>20),
			array('name'=>$this->lang->t('Table view'), 'width'=>5),
			array('name'=>$this->lang->t('Required'), 'width'=>5),
			array('name'=>$this->lang->t('Filter'), 'width'=>5),
			array('name'=>$this->lang->t('Parameters'), 'width'=>5))
		);

		//read database
		$rows = count($this->table_rows);
		$max_p = DB::GetOne('SELECT position FROM '.$this->tab.'_field WHERE field = \'Details\'');
		foreach($this->table_rows as $field=>$args) {
			$gb_row = $gb->get_new_row();
			if($args['extra']) {
				if ($args['type'] != 'page_split') {
					$gb_row->add_action($this->create_callback_href(array($this, 'view_field'),array('edit',$field)),'Edit');
				} else {
					$gb_row->add_action($this->create_callback_href(array($this, 'delete_page'),array($field)),'Delete');
					$gb_row->add_action($this->create_callback_href(array($this, 'edit_page'),array($field)),'Edit');
				}
			} else {
				if ($field!='General' && $args['type']=='page_split')
					$gb_row->add_action($this->create_callback_href(array($this, 'edit_page'),array($field)),'Edit');
			}
			if ($args['type']!=='page_split'){
				if ($args['active']) $gb_row->add_action($this->create_callback_href(array($this, 'set_field_active'),array($field, false)),'Deactivate', null, 'active-on');
				else $gb_row->add_action($this->create_callback_href(array($this, 'set_field_active'),array($field, true)),'Activate', null, 'active-off');
			}
			if ($args['position']>$max_p && $args['position']<=$rows || ($args['position']<$max_p-1 && $args['position']>2))
				$gb_row->add_action($this->create_callback_href(array($this, 'move_field'),array($field, $args['position'], +1)),'Move down', null, 'move-down');
			if ($args['position']>$max_p+1 || ($args['position']<$max_p && $args['position']>3))
				$gb_row->add_action($this->create_callback_href(array($this, 'move_field'),array($field, $args['position'], -1)),'Move up', null, 'move-up');
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
		
		if (!isset($this->lang)) $this->lang = $this->init_module('Base/Lang');
		$form = $this->init_module('Libs/QuickForm');
		
		switch ($action) {
			case 'add': $form->addElement('header', null, $this->lang->t('Add new field'));
						break;
			case 'edit': $form->addElement('header', null, $this->lang->t('Edit field properties'));
						break;
		}
		$form->addElement('text', 'field', $this->lang->t('Field'));
		$form->registerRule('check_if_column_exists', 'callback', 'check_if_column_exists', $this);
		$form->registerRule('check_if_no_id', 'callback', 'check_if_no_id', $this);
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
		$form->addElement('checkbox', 'visible', $this->lang->t('Table view'));
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
				if ($form->process(array($this, 'submit_add_field')))
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
	public function view_edit_history($id){
		if ($this->is_back()) return false;
		$this->init();
		$gb = $this->init_module('Utils/GenericBrowser', null, $this->tab);
		
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
		
		$created = Utils_RecordBrowserCommon::get_record($this->tab, $id, true);
		$created['created_by_login'] = DB::GetOne('SELECT login FROM user_login WHERE id=%d',array($created['created_by']));
		$row_data = array(
						array('value'=>'--','style'=>'border-top: 1px solid black;'),
						array('value'=>'--','style'=>'border-top: 1px solid black;'),
						array('value'=>$this->lang->t('Current'),'style'=>'border-top: 1px solid black;')
						);
		foreach($this->table_rows as $field => $args) {
			if ($field=='id') continue;
			if (!isset($created[$field])) $created[$field] = '';
			if (is_array($created[$field])) {
				$val = '';
				foreach($created[$field] as $v)
					$val .= ($val==''?'':', ').$v;
			} else {
				$val = $created[$field];
			}
			$row_data[] = array('DBvalue'=>$created[$field],'value'=>$val,'style'=>'font-weight: bold; border-top: 1px solid black;','hint'=>$val);
		}
		$edit_history = array($row_data);
		$ret = DB::Execute('SELECT ul.login, c.id, c.edited_on, c.edited_by FROM '.$this->tab.'_edit_history AS c LEFT JOIN user_login AS ul ON ul.id=c.edited_by WHERE c.'.$this->tab.'_id=%d ORDER BY edited_on DESC',array($id));
		$no_edits = true;
		$counter = 0;
		while ($row=$ret->FetchRow()) {
			$no_edits = false;
			foreach($created as $k=>$c) {
				if (is_array($c)) {
					$v = '';
					foreach($c as $c2)
						$v .= ($v==''?'':', ').$c2;
				} else $v = $c;
				$new_created[$k] = array('DBvalue'=>$c,'value'=>$v,'style'=>'color: #CCCCCC;','hint'=>$v);
			}
			$row_data = array('Edited',$row['login'],$row['edited_on']);
			$ret2 = DB::Execute('SELECT * FROM '.$this->tab.'_edit_history_data WHERE edit_id=%d',array($row['id']));
			$changed = array();
			while($row2 = $ret2->FetchRow()) {
				if (isset($changed[$row2['field']])) {
					if (is_array($created[$row2['field']]))
						array_unshift($created[$row2['field']], $row2['old_value']);
					else
						$created[$row2['field']] = array($row2['old_value'], $created[$row2['field']]);
				} else {
					$changed[$row2['field']] = true;
					$new_created[$row2['field']]['style'] = 'font-weight: bold; background-color: #EFEFFF;';
					$created[$row2['field']] = $row2['old_value'];
				}
				if (is_array($created[$row2['field']]))
					sort($created[$row2['field']]);
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
			if (is_array($created[$field])) {
				$v = '';
				foreach($created[$field] as $c)
					$v .= ($v==''?'':', ').$c;
			} else $v = $created[$field];
			$row_data[] = array('DBvalue'=>$created[$field],'value'=>$v,'style'=>'font-weight: bold;','hint'=>$v);
		}
		array_unshift($edit_history, $row_data);
		foreach($edit_history as $row_data) {
			$gb_row = $gb->get_new_row();
			$gb_row->add_data_array($row_data);
			if ($counter > 0) $gb_row->add_action($this->create_callback_href(array($this, 'restore_record'), array($row_data, $id)),'Restore');
			$counter--;
		}
		
		$this->display_module($gb);
		Base_ActionBarCommon::add('back',$this->lang->t('Back'),$this->create_back_href());
		return true;
	}
	
	public function set_active($id, $state=true){
		DB::Execute('UPDATE '.$this->tab.' SET active=%d WHERE id=%d',array($state?1:0,$id));
		return false;
	}
	public function restore_record($data, $id) {
		$this->init();
		$i = 3;
		$values = array();
		foreach($this->table_rows as $field => $args) {
			if ($field=='id') continue;
			$values[$args['id']] = $data[$i++]['DBvalue'];
		}
		$this->update_record_data($id,$values);
		return false;
	}
	public function caption(){
		return $this->caption.': '.$this->action;
	}
	public function recordpicker($element, $label, $format, $filters=array(), $crits=array()) {
		if (!isset($this->lang)) $this->lang = $this->init_module('Base/Lang');
		$this->init();
		$icon_on = Base_ThemeCommon::get_template_file('Utils_RecordBrowser','active-on.png');
		$icon_off = Base_ThemeCommon::get_template_file('Utils_RecordBrowser','active-off.png');
		$this->set_module_variable('element',$element);
		$this->set_module_variable('format_func',$format);
		$theme = $this->init_module('Base/Theme');
		$theme->assign('header', $this->lang->t('Select records').': '.$this->caption);
		$theme->assign('filters', $this->show_filters($filters, $element));
		$theme->assign('table', $this->show_data($crits, array(), array(), false, false, true));
		$theme->assign('close_button','<a href="javascript:leightbox_deactivate(\'leightbox_'.$element.'\')">Close</a>');

		$rpicker_ind = $this->get_module_variable('rpicker_ind');
		eval_js(
			'rpicker_init_'.$element.' = function(id){'.
			'	img = document.getElementsByName(\'leightbox_rpicker_'.$element.'_\'+id)[0];'.
			'	tolist = document.getElementsByName(\''.$element.'to[]\')[0];'.
			'	k = 0;'.
			'	img.src = "'.$icon_off.'";'.
			'	while (k!=tolist.length) {'.
			'		if (tolist.options[k].value == id) {'.
			'			img.src = "'.$icon_on.'";'.
			'			break;'.
			' 		}'. 
			'		k++;'.
			'	}'.
			'}');
		$init_func = 'init_all_rpicker_'.$element.' = function(id, cstring){';
		foreach($rpicker_ind as $v)
			$init_func .= 'rpicker_init_'.$element.'('.$v.');';
		$init_func .= '}';
		eval_js($init_func.';init_all_rpicker_'.$element.'();');
		eval_js(
			'addto_'.$element.' = function(id, cstring){'.
			'tolist = document.getElementsByName(\''.$element.'to[]\')[0];'.
			'fromlist = document.getElementsByName(\''.$element.'from[]\')[0];'.
			'img = document.getElementsByName(\'leightbox_rpicker_'.$element.'_\'+id)[0];'.
			'list = \'\';'.
			'k = 0;'.
			'while (k!=tolist.length) {'.
			'	if (tolist.options[k].value == id) {'.
			'		x = 0;'.
			'		while (x!=tolist.length) tolist.options[x].selected = (k==x++);'.
			'		remove_selected_'.$element.'();'.
			'		img.src = "'.$icon_off.'";'.
			'		return;'.
			' 	}'. 
			'	k++;'.
			'}'.
			'k = 0;'.
			'i = false;'.
			'while (k!=fromlist.length) {'.
			'	fromlist.options[k].selected = false;'.
			'	if (fromlist.options[k].value == id) {'.
			'		fromlist.options[k].selected = true;'.
			'		i = true;'.
			' 	}'. 
			'	k++;'.
			'}'.
			'if (!i) {'.
			'	fromlist.options[k] = new Option();'.
			'	fromlist.options[k].selected = true;'.
			'	fromlist.options[k].text = cstring;'.
			'	fromlist.options[k].value = id;'.
			'}'.
			'img.src = "'.$icon_on.'";'.
			'add_selected_'.$element.'();'.
			'};');
		$theme->display('Record_picker');
	}

}
?>
