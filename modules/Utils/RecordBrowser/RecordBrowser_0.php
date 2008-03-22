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
	private $tab;
	private $record;
	private $browse_mode;
	private $display_callback_table = array();
	private $QFfield_callback_table = array();
	private $requires = array();
	private $recent = 0;
	private $mode = 'view';
	private $caption = '';
	private $icon = '';
	private $favorites = false;
	private $full_history = true;
	private $action = 'Browsing';
	private $crits = array();
	private $access_callback;
	private $noneditable_fields = array();
	private $add_button = null;
	private $changed_view = false;
	private $is_on_main_page = false;
	private $custom_defaults = array();
	private $add_in_table = false;
	private $custom_filters = array();
	private $filter_field;
	private static $clone_result = null;	
	public $adv_search = false;
		
	public function get_val($field, $record, $id, $links_not_recommended = false, $args = null) {
		$val = $record[$args['id']];
		if (isset($this->display_callback_table[$field])) {
			$ret = call_user_func($this->display_callback_table[$field], $record, $id, $links_not_recommended, $this->table_rows[$field]);
		} else {
			$ret = $val;
			if ($args['type']=='select' || $args['type']=='multiselect') {
				if ((is_array($val) && empty($val)) || (!is_array($val) && $val=='')) {
					$ret = '--';
					return $ret;
				}
				list($tab, $col) = explode('::',$args['param']);
				if (!is_array($val)) $val = array($val);
				if ($tab=='__COMMON__') $data = Utils_CommonDataCommon::get_array($col, true);
				$ret = '';
				$first = true;
				foreach ($val as $k=>$v){
					if ($first) $first = false;
					else $ret .= ', ';
					if ($tab=='__COMMON__') $ret .= $data[$v];
					else $ret .= Utils_RecordBrowserCommon::create_linked_label($tab, $col, $v, $links_not_recommended);
				}
			}
			if ($args['type']=='commondata') {
				if (!isset($val) || $val==='') {
					$ret = '';
				} else {
					$arr = explode('::',$args['param']);
					$path = array_shift($arr);
					foreach($arr as $v) $path .= '/'.$record[strtolower(str_replace(' ','_',$v))];
					$path .= '/'.$record[$args['id']];
					$ret = Utils_CommonDataCommon::get_value($path);
				}
			}
			if ($args['type']=='currency') {
				$ret = Utils_CurrencyFieldCommon::format($val);
			}
			if ($args['type']=='checkbox') {
				$ret = $ret?$this->lang->t('Yes'):$this->lang->t('No');
			}
			if ($args['type']=='date') {
				if ($val!='') $ret = Base_RegionalSettingsCommon::time2reg($val, false);
			}
			if ($args['type']=='timestamp') {
				if ($val!='') $ret = Base_RegionalSettingsCommon::time2reg($val);
			}
		}
//		if (is_array($ret)) $ret = implode(', ', $ret);
		return $ret;
	}
	
	public function set_button($arg){
		$this->add_button = $arg;
	}
	
	public function get_access($action, $param=null){
		return Utils_RecordBrowserCommon::get_access($this->tab, $action, $param);
	}

	public function construct($tab = null) {
//		if (!isset($tab))
//			trigger_error('RecordBrowser did not receive string name for the table '.$this->get_parent_type().'.<br>Use $this->init_module(\'Utils/RecordBrowser\',\'table name here\');',E_USER_ERROR);
		$this->tab = $tab;
	}
	
	public function init($admin=false) {
		if (!isset($this->lang)) $this->lang = $this->init_module('Base/Lang');
		$params = DB::GetRow('SELECT caption, icon, recent, favorites, full_history FROM recordbrowser_table_properties WHERE tab=%s', array($this->tab));
		if ($params==false) trigger_error('There is no such recordSet as '.$this->tab.'.', E_USER_ERROR);
		list($this->caption,$this->icon,$this->recent,$this->favorites,$this->full_history) = $params;

		//If Caption or icon not specified assign default values
		if ($this->caption=='') $this->caption='Record Browser';
		if ($this->icon=='') $this->icon = Base_ThemeCommon::get_template_filename('Base_ActionBar','icons/settings.png');
		$this->icon = Base_ThemeCommon::get_template_dir().$this->icon;
		
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
	public function body($def_order=array()) {
		$this->init();
		if ($this->get_access('browse')===false) {
			print($this->lang->t('You are not authorised to browse this data.'));
			return;
		}
		$this->is_on_main_page = true;
		Base_ActionBarCommon::add('add',$this->lang->t('New'), $this->create_callback_href(array($this,'navigate'),array('view_entry', 'add', null, $this->custom_defaults)));

		$filters = $this->show_filters();

		if (isset($this->filter_field)) {
			$f = $this->pack_module('CRM/Filters');
			$ff = explode(',',trim($f->get(),'()'));
			$this->crits[$this->filter_field] = $ff;
		}

		ob_start();
		$this->show_data($this->crits, array(), $def_order);
		$table = ob_get_contents();
		ob_end_clean();

		$theme = $this->init_module('Base/Theme');
		$theme->assign('filters', $filters);
		$theme->assign('table', $table);
		$theme->assign('caption', $this->lang->t($this->caption).' - '.$this->lang->t(ucfirst($this->browse_mode)));
		$theme->assign('icon', $this->icon);
		$theme->display('Browsing_records');
	}
	public function switch_view($mode){
		$this->browse_mode = $mode;
		$this->changed_view = true;
		$this->set_module_variable('browse_mode', $mode);
	}
	//////////////////////////////////////////////////////////////////////////////////////////
	public function show_filters($filters_set = array(), $f_id='') {
		if ($this->get_access('browse')===false) {
			return;
		}
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
			$filter_id = strtolower(str_replace(' ','_',$filter));
			if (isset($this->custom_filters[$filter_id])) {
				$f = $this->custom_filters[$filter_id];
				if (!isset($f['label'])) $f['label'] = $filter;
				if (!isset($f['args'])) $f['args'] = null;
				$form->addElement($f['type'], $filter_id, $f['label'], $f['args']);
				$filters[] = $filter_id;
				continue;
			}
			$arr = array();
			if (!isset($this->QFfield_callback_table[$filter]) && ($this->table_rows[$filter]['type'] == 'select' || $this->table_rows[$filter]['type'] == 'multiselect')) {
				list($tab, $col) = explode('::',$this->table_rows[$filter]['param']);
				if ($tab=='__COMMON__') {
					$arr = array_merge($arr, Utils_CommonDataCommon::get_array($col, true));
				} else {
					$ret2 = DB::Execute('SELECT '.$tab.'_id, value FROM '.$tab.'_data WHERE field=%s ORDER BY value', array($col));
					while ($row2 = $ret2->FetchRow()) $arr[$row2[$tab.'_id']] = $row2['value'];
				}
			} else {
				$ret2 = DB::Execute('SELECT '.$this->tab.'_id, value FROM '.$this->tab.'_data WHERE field=%s ORDER BY value', array($filter));
				while ($row2 = $ret2->FetchRow()) if($row2['value'][0]!='_') $arr[$row2['value']] = $this->get_val($filter, array($this->table_rows[$filter]['id']=>$row2['value']), $row2[$this->tab.'_id'], true, $this->table_rows[$filter]);
			}
			if ($this->table_rows[$filter]['type']=='checkbox') $arr = array(''=>$this->lang->ht('No'), 1=>$this->lang->ht('Yes'));
			natcasesort($arr);
			$arr = array('__NULL__'=>'--')+$arr;
			$form->addElement('select', $filter_id, $filter, $arr);
			$filters[] = $filter_id;
		}
		$form->addElement('submit', 'submit', 'Show');
		$def_filt = $this->get_module_variable('def_filter', array());
		$form->setDefaults($def_filt);
		$this->crits = array();
		$vals = $form->exportValues();
		foreach ($filters_all as $filter) {
			$filter_id = strtolower(str_replace(' ','_',$filter));
			if (!isset($vals[$filter_id])) $vals[$filter_id]='__NULL__';
			if (isset($this->custom_filters[$filter_id])) {
				if (isset($this->custom_filters[$filter_id]['trans'][$vals[$filter_id]]))
					foreach($this->custom_filters[$filter_id]['trans'][$vals[$filter_id]] as $k=>$v)
						$this->crits[$k] = $v;
			} elseif ($vals[$filter_id]!=='__NULL__') $this->crits[$filter_id] = $vals[$filter_id];
		}
		$this->set_module_variable('def_filter', $this->crits);
		$theme = $this->init_module('Base/Theme');
		$form->assign_theme('form',$theme);
		$theme->assign('filters', $filters);
		$theme->assign('id', $f_id);
		if (!$this->isset_module_variable('filters_defaults'))
		$this->set_module_variable('filters_defaults', $this->crits);
		elseif ($this->crits!==$this->get_module_variable('filters_defaults')) $theme->assign('dont_hide', true);
		return $this->get_html_of_module($theme, 'Filter', 'display');
	}
	//////////////////////////////////////////////////////////////////////////////////////////
	public function navigate($func){
		$x = ModuleManager::get_instance('/Base_Box|0');
		if (!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$args = func_get_args();
		array_shift($args);
		$x->push_main('Utils/RecordBrowser',$func,$args,array($this->tab));
		return false;
	}
	public function back(){
		$x = ModuleManager::get_instance('/Base_Box|0');
		if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		return $x->pop_main();
	}
	//////////////////////////////////////////////////////////////////////////////////////////
	public function show_data($crits = array(), $cols = array(), $order = array(), $admin = false, $special = false) {
		if ($this->get_access('browse')===false) {
			print($this->lang->t('You are not authorised to browse this data.'));
			return;
		}
		$this->init();
		$this->action = 'Browse';
		if (!Base_AclCommon::i_am_admin() && $admin) {
			print($this->lang->t('You don\'t have permission to access this data.'));
		}
		$gb = $this->init_module('Utils/GenericBrowser', null, $this->tab);
		$gb->set_module_variable('adv_search', $gb->get_module_variable('adv_search', $this->adv_search));
		$is_searching = $gb->get_module_variable('search','');
		if (!empty($is_searching)) {
			$this->set_module_variable('browse_mode','all');
			$gb->set_module_variable('quickjump_to',null);
		}

		if ($this->is_on_main_page) {
			$this->browse_mode = $this->get_module_variable('browse_mode', 'recent');
			if (($this->browse_mode=='recent' && $this->recent==0) || ($this->browse_mode=='favorites' && !$this->favorites)) $this->set_module_variable('browse_mode', $this->browse_mode='all'); 
			if ($this->browse_mode!=='recent' && $this->recent>0) Base_ActionBarCommon::add('history',$this->lang->t('Recent'), $this->create_callback_href(array($this,'switch_view'),array('recent')));
			if ($this->browse_mode!=='all') Base_ActionBarCommon::add('report',$this->lang->t('All'), $this->create_callback_href(array($this,'switch_view'),array('all')));
			if ($this->browse_mode!=='favorites' && $this->favorites) Base_ActionBarCommon::add('favorites',$this->lang->t('Favorites'), $this->create_callback_href(array($this,'switch_view'),array('favorites')));
		}
		
		if ($special)
			$table_columns = array(array('name'=>'Select', 'width'=>1));
		elseif (!$admin && $this->favorites)
			$table_columns = array(array('name'=>'Fav', 'width'=>1, 'order'=>':Fav'));
		$table_columns_SQL = array();
		$quickjump = DB::GetOne('SELECT quickjump FROM recordbrowser_table_properties WHERE tab=%s', array($this->tab));

		foreach($this->table_rows as $field => $args) {
			if (isset($order[$args['id']])) {
				$order[$field] = $order[$args['id']];
				unset($order[$args['id']]);
			}
			if ($field === 'id') continue;
			if (!$args['visible'] && (!isset($cols[$args['id']]) || $cols[$args['id']] === false)) continue;
			if (isset($cols[$args['id']]) && $cols[$args['id']] === false) continue;
			$arr = array('name'=>$args['name']);
			if ($this->browse_mode!='recent') $arr['order'] = $field;
			if ($quickjump!=='' && $args['name']===$quickjump) $arr['quickjump'] = '"'.$args['name'];
			if ($args['type']=='text' || $args['type']=='currency') $arr['search'] = str_replace(' ','_',$field);
			$str = explode(';', $args['param']);
			$ref = explode('::', $str[0]);
			if ($ref[0]!='' && isset($ref[1])) $arr['search'] = '__Ref__'.str_replace(' ','_',$field);
			if ($args['type']=='commondata') {
				if (!isset($ref[1])) $arr['search'] = '__RefCD__'.str_replace(' ','_',$field);
				else unset($arr['search']);
			}
			$table_columns[] = $arr;
			array_push($table_columns_SQL, 'e.'.$field);
		}

		$table_columns_SQL = join(', ', $table_columns_SQL);
		if ($this->browse_mode == 'recent')
			$table_columns[] = array('name'=>$this->lang->t('Visited on'), 'wrapmode'=>'nowrap');
			 
		$gb->set_table_columns( $table_columns );

		if ($this->browse_mode != 'recent')
			$gb->set_default_order($order, $this->changed_view);

		if (!$special) {
			if ($this->add_button!==null) $label = $this->add_button;
			else $label = $this->create_callback_href(array($this, 'navigate'), array('view_entry', 'add', null, $this->custom_defaults));
			$gb->set_custom_label('<a '.$label.'><img border="0" src="'.Base_ThemeCommon::get_template_file('Base/ActionBar','icons/add.png').'" /></a>');
		}
		$search = $gb->get_search_query(true);
		$search_res = array();
		foreach ($search as $k=>$v)
			$search_res['"'.str_replace(array('__','_'),array(':',' '),$k)] = $v;
		
		$crits = array_merge($crits, $search_res);
		if ($this->browse_mode == 'favorites')
			$crits[':Fav'] = true;
		if ($this->browse_mode == 'recent')
			$crits[':Recent'] = true;
		$order = $gb->get_order();

		$limit = $gb->get_limit(Utils_RecordBrowserCommon::get_records_limit($this->tab, $crits, $admin));
		$records = Utils_RecordBrowserCommon::get_records($this->tab, $crits, array(), $order, $limit, $admin);

		if ($admin) $this->browse_mode = 'all'; 
		if ($this->browse_mode == 'recent') {
			$rec_tmp = array();
			$ret = DB::Execute('SELECT * FROM '.$this->tab.'_recent WHERE user_id=%d ORDER BY visited_on DESC', array(Acl::get_user()));
			while ($row = $ret->FetchRow()) {
				if (!isset($records[$row[$this->tab.'_id']])) continue;
				$rec_tmp[$row[$this->tab.'_id']] = $records[$row[$this->tab.'_id']];
				$rec_tmp[$row[$this->tab.'_id']]['visited_on'] = Base_RegionalSettingsCommon::time2reg(strtotime($row['visited_on']));
			}
			$records = $rec_tmp;
		}
		if ($special) $rpicker_ind = array();

		$favs = array();
		$ret = DB::Execute('SELECT '.$this->tab.'_id FROM '.$this->tab.'_favorite WHERE user_id=%d', array(Acl::get_user()));
		while ($row=$ret->FetchRow()) $favs[$row[$this->tab.'_id']] = true;

		foreach ($records as $row) {
			$gb_row = $gb->get_new_row();
			if (!$admin && $this->favorites) {
				$isfav = isset($favs[$row['id']]);
				$row_data = array('<a '.Utils_TooltipCommon::open_tag_attrs(($isfav?$this->lang->t('This item is on your favourites list<br>Click to remove it from your favorites'):$this->lang->t('Click to add this item to favorites'))).' '.$this->create_callback_href(array($this,($isfav?'remove_from_favs':'add_to_favs')), array($row['id'])).'><img border="0" src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','star_'.($isfav==false?'no':'').'fav.png').'" /></a>');
			} else $row_data = array();
			if ($special) { 
				$func = $this->get_module_variable('format_func');
				$element = $this->get_module_variable('element');
				$row_data = array('<a href="javascript:rpicker_addto(\''.$element.'\','.$row['id'].',\''.Base_ThemeCommon::get_template_file('images/active_on.png').'\',\''.Base_ThemeCommon::get_template_file('images/active_off2.png').'\',\''.call_user_func($func, $row['id']).'\');"><img src="null"  border="0" name="leightbox_rpicker_'.$element.'_'.$row['id'].'" /></a>');
				$rpicker_ind[] = $row['id'];
			}
			
			foreach($this->table_rows as $field => $args)
				if (($args['visible'] && !isset($cols[$args['id']])) || (isset($cols[$args['id']]) && $cols[$args['id']] === true)) {
					$row_data[] = $this->get_val($field, $row, $row['id'], $special, $args);
				}
			if ($this->browse_mode == 'recent')
				$row_data[] = $row['visited_on'];

			$gb_row->add_data_array($row_data);
			if (!isset($cols['Actions']) || $cols['Actions'])
			{
				if (!$special) {
					$gb_row->add_action($this->create_callback_href(array($this,'navigate'),array('view_entry', 'view',$row['id'])),$this->lang->t('View'));
					if ($this->get_access('edit',$row)) $gb_row->add_action($this->create_callback_href(array($this,'navigate'),array('view_entry', 'edit',$row['id'])),$this->lang->t('Edit'));
					if ($admin) {
						if (!$row['active']) $gb_row->add_action($this->create_callback_href(array($this,'set_active'),array($row['id'],true)),$this->lang->t('Activate'), null, 'active-off');
						else $gb_row->add_action($this->create_callback_href(array($this,'set_active'),array($row['id'],false)),$this->lang->t('Deactivate'), null, 'active-on');
						$info = Utils_RecordBrowserCommon::get_record_info($this->tab, $row['id']);
						if ($info['edited_by']===null) $gb_row->add_action('',$this->lang->t('This record was never edited'),null,'history_inactive');
						else $gb_row->add_action($this->create_callback_href(array($this,'navigate'),array('view_edit_history', $row['id'])),$this->lang->t('View edit history'),null,'history');
					} else 
					if ($this->get_access('delete',$row)) $gb_row->add_action($this->create_confirm_callback_href($this->lang->t('Are you sure you want to delete this record?'),array('Utils_RecordBrowserCommon','delete_record'),array($this->tab, $row['id'])),$this->lang->t('Delete'));
				}
				$gb_row->add_info(Utils_RecordBrowserCommon::get_html_record_info($this->tab, isset($info)?$info:$row['id']));
			}
		}
		if (!$special && $this->add_in_table) {
			$form = $this->init_module('Libs/QuickForm',null, 'add_in_table__'.$this->tab);
			$form->setDefaults($this->custom_defaults);
	
			$visible_cols = array();
			foreach($this->table_rows as $field => $args)
				if (($args['visible'] && !isset($cols[$args['id']])) || (isset($cols[$args['id']]) && $cols[$args['id']] === true))
					$visible_cols[$args['id']] = true;
			$this->prepare_view_entry_details(null, 'add', null, $form, $visible_cols);
			
			if ($form->validate()) {
				$values = $form->exportValues();
				$dpm = DB::GetOne('SELECT data_process_method FROM recordbrowser_table_properties WHERE tab=%s', array($this->tab));
				if ($dpm!=='') {
					$method = explode('::',$dpm);
					if (is_callable($method)) $values = call_user_func($method, $values, 'add');
				}
				Utils_RecordBrowserCommon::new_record($this->tab, $values);
				location(array());
			}
							
			$renderer = new HTML_QuickForm_Renderer_TCMSArraySmarty(); 
			$form->accept($renderer);
			$data = $renderer->toArray();

//			print($data['javascript'].'<form '.$data['attributes'].'>'.$data['hidden']."\n");
			$gb->set_prefix($data['javascript'].'<form '.$data['attributes'].'>'.$data['hidden']."\n");
			$gb->set_postfix("</form>\n");
			
			if (!$admin && $this->favorites) {
				$row_data = array('&nbsp;');
			} else $row_data = array();

			foreach($visible_cols as $k => $v)
				$row_data[] = $data[$k]['error'].$data[$k]['html'];

			if ($this->browse_mode == 'recent')
				$row_data[] = '&nbsp;';

			$gb_row = $gb->get_new_row();
			$gb_row->add_action($form->get_submit_form_href(),'Submit');
			$gb_row->add_data_array($row_data);
		}
		if ($special) {
			$this->set_module_variable('rpicker_ind',$rpicker_ind);
			return $this->get_html_of_module($gb);
		} else $this->display_module($gb);
//		if ($this->add_in_table) print("</form>\n");
	}
	//////////////////////////////////////////////////////////////////////////////////////////
	public function delete_record($id) {
		Utils_RecordBrowserCommon::delete_record($this->tab, $id);
		return $this->back();
	}
	public function clone_record($id) {
		if (self::$clone_result!==null) {
			if (is_numeric(self::$clone_result)) $this->navigate('view_entry', 'view', self::$clone_result);
			self::$clone_result = null;
			return false;
		}
		$this->navigate('view_entry', 'add', null, Utils_RecordBrowserCommon::get_record($this->tab, $id));
		return true;
	}
	public function view_entry($mode='view', $id = null, $defaults = array()) {
		$js = ($mode!='view');
		$time = microtime(true);
		if ($this->is_back()) {
			self::$clone_result = 'canceled';
			return $this->back();
		}
		$this->init();
		$this->record = Utils_RecordBrowserCommon::get_record($this->tab, $id);
		switch ($mode) {
			case 'add': $this->action = 'New record'; break;
			case 'edit': $this->action = 'Edit record';
						$this->noneditable_fields = $this->get_access('edit_fields', $this->record); 
						break;
			case 'view': $this->action = 'View record'; break;
		}
		$theme = $this->init_module('Base/Theme');

		if($mode!='add')
			Utils_RecordBrowserCommon::add_recent_entry($this->tab, Acl::get_user(),$id);

		$tb = $this->init_module('Utils/TabbedBrowser');
		$form = $this->init_module('Libs/QuickForm',null, $mode);
		if($mode=='add')
			$form->setDefaults($defaults);

		$this->prepare_view_entry_details($this->record, $mode, $id, $form);

		if ($form->validate()) {
			$values = $form->exportValues();
			$dpm = DB::GetOne('SELECT data_process_method FROM recordbrowser_table_properties WHERE tab=%s', array($this->tab));
			if ($dpm!=='') {
				$method = explode('::',$dpm);
				if (is_callable($method)) $values = call_user_func($method, $values, $mode);
			}
			if ($mode=='add') {
				$id = Utils_RecordBrowserCommon::new_record($this->tab, $values);
				self::$clone_result = $id;
				return $this->back();
			}
			$time_from = date('Y-m-d H:i:s', $this->get_module_variable('edit_start_time'));
			$ret = DB::Execute('SELECT * FROM '.$this->tab.'_edit_history WHERE edited_on>=%T AND '.$this->tab.'_id=%d',array($time_from, $id));
			if ($ret->EOF) {
				$this->update_record($id,$values);
				return $this->back();
			}
			$this->dirty_read_changes($id, $time_from);
		} 
		if ($mode=='edit') $this->set_module_variable('edit_start_time',$time);

		if ($mode=='view') { 
			if ($this->get_access('edit',$this->record)) Base_ActionBarCommon::add('edit', $this->lang->ht('Edit'), $this->create_callback_href(array($this,'navigate'), array('view_entry','edit',$id)));
			if ($this->get_access('delete',$this->record)) Base_ActionBarCommon::add('delete', $this->lang->ht('Delete'), $this->create_confirm_callback_href($this->lang->t('Are you sure you want to delete this record?'),array($this,'delete_record'),array($id)));
			Base_ActionBarCommon::add('settings',$this->lang->t('Clone'), $this->create_confirm_callback_href($this->lang->ht('You are about to create a copy of this record. Do you want to continue?'),array($this,'clone_record'),array($id)));
			Base_ActionBarCommon::add('back', $this->lang->ht('Back'), $this->create_back_href());
		} else {
			Base_ActionBarCommon::add('save', $this->lang->ht('Save'), $form->get_submit_form_href());
			Base_ActionBarCommon::add('delete', $this->lang->ht('Cancel'), $this->create_back_href());
		}

		if ($mode!='add') {
			$isfav = (DB::GetOne('SELECT user_id FROM '.$this->tab.'_favorite WHERE user_id=%d AND '.$this->tab.'_id=%d', array(Acl::get_user(), $id))!==false);
			$theme -> assign('info_tooltip', '<a '.Utils_TooltipCommon::open_tag_attrs(Utils_RecordBrowserCommon::get_html_record_info($this->tab, $id)).'><img border="0" src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','info.png').'" /></a>');
			$row_data = array();
			$fav = DB::GetOne('SELECT user_id FROM '.$this->tab.'_favorite WHERE user_id=%d AND '.$this->tab.'_id=%s', array(Acl::get_user(), $id));
			
			if ($this->favorites)
				$theme -> assign('fav_tooltip', '<a '.Utils_TooltipCommon::open_tag_attrs(($isfav?$this->lang->t('This item is on your favourites list<br>Click to remove it from your favorites'):$this->lang->t('Click to add this item to favorites'))).' '.$this->create_callback_href(array($this,($isfav?'remove_from_favs':'add_to_favs')), array($id)).'><img border="0" src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','star_'.($isfav==false?'no':'').'fav.png').'" /></a>');
			if ($this->full_history) {
				$info = Utils_RecordBrowserCommon::get_record_info($this->tab, $id);
				if ($info['edited_by']===null) $theme -> assign('history_tooltip', '<a '.Utils_TooltipCommon::open_tag_attrs($this->lang->t('This record was never edited')).'><img border="0" src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','history_inactive.png').'" /></a>');
				else $theme -> assign('history_tooltip', '<a '.Utils_TooltipCommon::open_tag_attrs($this->lang->t('Click to view edit history of currently displayed record')).' '.$this->create_callback_href(array($this,'navigate'), array('view_edit_history', $id)).'><img border="0" src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','history.png').'" /></a>');
			}
		}
		if ($mode=='edit') 
			foreach($this->table_rows as $field => $args) 
				if (isset($this->noneditable_fields[$field]) && !$this->noneditable_fields[$field]) {
					$form->freeze($args['id']);
				} 

		if ($mode=='view') $form->freeze();
		$renderer = new HTML_QuickForm_Renderer_TCMSArraySmarty(); 
		$form->accept($renderer);
		$data = $renderer->toArray();
		
		print($data['javascript'].'<form '.$data['attributes'].'>'.$data['hidden']."\n");

		$last_page = DB::GetOne('SELECT MIN(position) FROM '.$this->tab.'_field WHERE type = \'page_split\' AND field != \'General\'');
		$label = DB::GetRow('SELECT field, param FROM '.$this->tab.'_field WHERE position=%s', array($last_page));
		$cols = $label['param'];
		$label = $label['field'];
		$this->mode = $mode;
		$this->view_entry_details(1, $last_page, $data, $theme, true);
		$ret = DB::Execute('SELECT position, field, param FROM '.$this->tab.'_field WHERE type = \'page_split\' AND position > %d', array($last_page));
		$row = true;
		if ($mode=='view')
			print("</form>\n");
		while ($row) {
			$row = $ret->FetchRow();
			if ($row) $pos = $row['position'];
			else $pos = DB::GetOne('SELECT MAX(position) FROM '.$this->tab.'_field')+1;
			if ($pos - $last_page>1) $tb->set_tab($this->lang->t($label),array($this,'view_entry_details'), array($last_page, $pos+1, $data, null, false, $cols), $js);
			$cols = $row['param'];
			$last_page = $pos;
			if ($row) $label = $row['field'];
		}
		if ($mode!='add' && $mode!='edit') {
			$ret = DB::Execute('SELECT * FROM recordbrowser_addon WHERE tab=%s', array($this->tab));
			while ($row = $ret->FetchRow()) {
				$mod = $this->init_module($row['module']);
				if (!is_callable(array($mod,$row['func']))) $tb->set_tab($this->lang->t($row['label']),array($this, 'broken_addon'), $js);
				else $tb->set_tab($this->lang->t($row['label']),array($this, 'display_module'), array($mod, array($this->record), $row['func']), $js);
			}
		}
		$this->display_module($tb);
		if ($mode=='add' || $mode=='edit') print("</form>\n");
		$tb->tag();

		return true;
	} //view_entry
	
	public function broken_addon(){
		print('Addon is broken, please contact system administrator.');
	}
	
	public function view_entry_details($from, $to, $data, $theme=null, $main_page = false, $cols = 2){
		if ($theme==null) $theme = $this->init_module('Base/Theme');
		$fields = array();
		$longfields = array();
		foreach($this->table_rows as $field => $args) { 
			if ($args['type']=='hidden') continue;
			if ($args['position'] >= $from && ($to == -1 || $args['position'] < $to)) 
			{
				if (!isset($data[$args['id']])) $data[$args['id']] = array('label'=>'', 'html'=>'');
					$arr = array(	'label'=>$data[$args['id']]['label'],
									'element'=>$args['id'],
									'html'=>$data[$args['id']]['html'],
									'style'=>$args['style'],
									'error'=>isset($data[$args['id']]['error'])?$data[$args['id']]['error']:null,
									'required'=>isset($args['required'])?$args['required']:null,
									'type'=>$args['type']);
					if ($args['type']<>'long text') $fields[$args['id']] = $arr; else $longfields[$args['id']] = $arr;
			}
		}
		if ($cols==0) $cols=2;
		$theme->assign('fields', $fields);
		$theme->assign('cols', $cols);
		$theme->assign('longfields', $longfields);
		$theme->assign('action', $this->mode);
		$theme->assign('form_data', $data);
		$theme->assign('required_note', $this->lang->t('Indicates required fields.'));
		
		$theme->assign('caption',$this->caption);
		$theme->assign('icon',$this->icon);

		$theme->assign('main_page',$main_page);

		if ($main_page) {
			$tpl = DB::GetOne('SELECT tpl FROM recordbrowser_table_properties WHERE tab=%s', array($this->tab));
			$theme->assign('raw_data',$this->record);
		} else {
			$tpl = '';
			if ($this->mode=='view') print('<form>');
		}
		$theme->display(($tpl!=='')?$tpl:'View_entry', ($tpl!==''));
		if (!$main_page && $this->mode=='view') print('</form>');
	}

	public function prepare_view_entry_details($record, $mode, $id, $form, $visible_cols = null){
		$init_js = '';
		foreach($this->table_rows as $field => $args){
			if ($visible_cols!==null && !isset($visible_cols[$args['id']])) continue;
			if ($args['type']=='hidden') continue;
			if (isset($this->QFfield_callback_table[$field])) {
				call_user_func($this->QFfield_callback_table[$field], $form, $args['id'], $this->lang->t($args['name']), $mode, $mode=='add'?'':$record[$args['id']], $args);
			} else {
				if ($mode!=='add' && $mode!=='edit') {
					if ($args['type']!='checkbox' && $args['type']!='commondata') {
						$def = $this->get_val($field, $record, $id, false, $args);
						$form->addElement('static', $args['id'], '<span id="_'.$args['id'].'__label">'.$this->lang->t($args['name']).'</span>', array('id'=>$args['id']));
						$form->setDefaults(array($args['id']=>$def));
						continue;
					}
				}
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
								if ($record[$k] != $w) {
									$hidden = true;
									break;
								}
							}
							if ($hidden) break;
						}
						if ($hidden) continue;
					}
				switch ($args['type']) {
					case 'calculated':	$form->addElement('static', $args['id'], '<span id="_'.$args['id'].'__label">'.$this->lang->t($args['name']).'</span>', array('id'=>$args['id']));
										$form->setDefaults(array($args['id']=>'['.$this->lang->t('formula').']'));
										break;
					case 'integer':		$form->addElement('text', $args['id'], '<span id="_'.$args['id'].'__label">'.$this->lang->t($args['name']).'</span>', array('id'=>$args['id']));
										$form->addRule($args['id'], $this->lang->t('Only numbers are allowed.'), 'numeric');
										if ($mode!=='add') $form->setDefaults(array($args['id']=>$record[$args['id']]));
										break;
					case 'checkbox':	$form->addElement('checkbox', $args['id'], '<span id="_'.$args['id'].'__label">'.$this->lang->t($args['name']).'</span>', '', array('id'=>$args['id']));
										if ($mode!=='add') $form->setDefaults(array($args['id']=>$record[$args['id']]));
										break;
					case 'currency':	$form->addElement('currency', $args['id'], '<span id="_'.$args['id'].'__label">'.$this->lang->t($args['name']).'</span>', array('id'=>$args['id']));
										if ($mode!=='add') $form->setDefaults(array($args['id']=>$record[$args['id']]));
										break;
					case 'text':		if ($mode!=='view') $form->addElement('text', $args['id'], '<span id="_'.$args['id'].'__label">'.$this->lang->t($args['name']).'</span>', array('id'=>$args['id'], 'maxlength'=>$args['param']));
										else $form->addElement('static', $args['id'], '<span id="_'.$args['id'].'__label">'.$this->lang->t($args['name']).'</span>', array('id'=>$args['id']));
										$form->addRule($args['id'], $this->lang->t('Maximum length for this field is '.$args['param'].'.'), 'maxlength', $args['param']);
										if ($mode!=='add') $form->setDefaults(array($args['id']=>$record[$args['id']]));
										break;
					case 'long text':	$form->addElement('textarea', $args['id'], '<span id="_'.$args['id'].'__label">'.$this->lang->t($args['name']).'</span>', array('id'=>$args['id']));
										$form->addRule($args['id'], $this->lang->t('Maximum length for this field is 255.'), 'maxlength', 255);
										if ($mode!=='add') $form->setDefaults(array($args['id']=>$record[$args['id']]));
										break;
					case 'date':		$form->addElement('datepicker', $args['id'], '<span id="_'.$args['id'].'__label">'.$this->lang->t($args['name']).'</span>', array('id'=>$args['id']));
										if ($mode!=='add') $form->setDefaults(array($args['id']=>$record[$args['id']]));
										break;
					case 'timestamp':	$form->addElement('timestamp', $args['id'], '<span id="_'.$args['id'].'__label">'.$this->lang->t($args['name']).'</span>', array('id'=>$args['id']));
										if ($mode!=='add') $form->setDefaults(array($args['id']=>$record[$args['id']]));
										break;
					case 'commondata':	$param = explode('::',$args['param']);
										foreach ($param as $k=>$v) if ($k!=0) $param[$k] = strtolower(str_replace(' ','_',$v));
										$form->addElement($args['type'], $args['id'], '<span id="_'.$args['id'].'__label">'.$this->lang->t($args['name']).'</span>', $param, array('empty_option'=>$args['required'], 'id'=>$args['id']));
										if ($mode!=='add') $form->setDefaults(array($args['id']=>$record[$args['id']]));
										break;
					case 'select':		
					case 'multiselect':	$comp = array();
										if (!$args['required'] && $args['type']==='select') $comp[''] = '--';
										list($tab, $col) = explode('::',$args['param']);
										if ($tab=='__COMMON__') {
											$data = Utils_CommonDataCommon::get_array($col, true);
											if (!is_array($data)) $data = array();
										}
										if ($mode=='add' || $mode=='edit') {
											if ($tab=='__COMMON__')
												$comp = $comp+$data;
											else {
	/*
	 *											$ret = DB::Execute('SELECT * FROM '.$tab.'_data AS rd LEFT JOIN '.$tab.' AS r ON rd.'.$tab.'_id = r.id WHERE rd.field=%s AND r.active=1 ORDER BY value', array($col));
	 *											while ($row = $ret->FetchRow()) $comp[$row[$tab.'_id']] = $row['value'];
	 **/
												$records = Utils_RecordBrowserCommon::get_records($tab, array(), array($col));
												$col_id = strtolower(str_replace(' ','_',$col));
												if (!is_array($record[$args['id']])) {
													if ($record[$args['id']]!='') $record[$args['id']] = array($record[$args['id']]); else $record[$args['id']] = array();
												} 
												$ext_rec = array_flip($record[$args['id']]);
												foreach ($records as $k=>$v) {
													$comp[$k] = $v[$col_id];
													unset($ext_rec[$v['id']]);
												}
												foreach($ext_rec as $k=>$v) {
													$c = Utils_RecordBrowserCommon::get_record($tab, $k);
													$comp[$k] = $c[$col_id];
												}
											}
											$form->addElement($args['type'], $args['id'], '<span id="_'.$args['id'].'__label">'.$this->lang->t($args['name']).'</span>', $comp, array('id'=>$args['id']));
											if ($mode!=='add') $form->setDefaults(array($args['id']=>$record[$args['id']]));
										} else {
											$form->addElement('static', $args['id'], '<span id="_'.$args['id'].'__label">'.$this->lang->t($args['name']).'</span>', array('id'=>$args['id']));
											$form->setDefaults(array($args['id']=>$record[$args['id']]));
										}
										break;
				}
			}
			if ($args['required'])
				$form->addRule($args['id'], $this->lang->t('Field required'), 'required');
		}
		eval_js($init_js);	
	}
	public function add_to_favs($id) {
		DB::Execute('INSERT INTO '.$this->tab.'_favorite (user_id, '.$this->tab.'_id) VALUES (%d, %d)', array(Acl::get_user(), $id));
	}
	public function remove_from_favs($id) {
		DB::Execute('DELETE FROM '.$this->tab.'_favorite WHERE user_id=%d AND '.$this->tab.'_id=%d', array(Acl::get_user(), $id));
	}
	public function update_record($id,$values) {
		Utils_RecordBrowserCommon::update_record($this->tab, $id, $values, true);
	}
	//////////////////////////////////////////////////////////////////////////////////////////
	public function administrator_panel() {
		$this->init();
		$tb = $this->init_module('Utils/TabbedBrowser');
		
		$tb->set_tab($this->lang->t('Manage Records'),array($this, 'show_data'), array(array(), array(), array(), true) );
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
		if ($this->is_back()) 
			return false;
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
			'currency'=>'currency', 
			'checkbox'=>'checkbox', 
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
	public function dirty_read_changes($id, $time_from) {
		print('<b>'.$this->lang->t('The following changes were applied to this record while you were editing it.<br>Please revise this data and make sure to keep this record most accurate.').'</b><br>');
		$gb_cha = $this->init_module('Utils/GenericBrowser', null, $this->tab.'__changes');
		$table_columns_changes = array(	array('name'=>$this->lang->t('Date'), 'width'=>1, 'wrapmode'=>'nowrap'),
										array('name'=>$this->lang->t('Username'), 'width'=>1, 'wrapmode'=>'nowrap'),
										array('name'=>$this->lang->t('Field'), 'width'=>1, 'wrapmode'=>'nowrap'),
										array('name'=>$this->lang->t('Old value'), 'width'=>1, 'wrapmode'=>'nowrap'),
										array('name'=>$this->lang->t('New value'), 'width'=>1, 'wrapmode'=>'nowrap'));
		$gb_cha->set_table_columns( $table_columns_changes );
		
		$created = Utils_RecordBrowserCommon::get_record($this->tab, $id, true);
		$created['created_by_login'] = Base_UserCommon::get_user_login($created['created_by']);
		$field_hash = array();
		foreach($this->table_rows as $field => $args)
			$field_hash[$args['id']] = $field;
		$ret = DB::Execute('SELECT ul.login, c.id, c.edited_on, c.edited_by FROM '.$this->tab.'_edit_history AS c LEFT JOIN user_login AS ul ON ul.id=c.edited_by WHERE c.edited_on>=%T AND c.'.$this->tab.'_id=%d ORDER BY edited_on DESC',array($time_from,$id));
		while ($row = $ret->FetchRow()) {
			$changed = array();
			$ret2 = DB::Execute('SELECT * FROM '.$this->tab.'_edit_history_data WHERE edit_id=%d',array($row['id']));
			while($row2 = $ret2->FetchRow()) {
				if (isset($changed[$row2['field']])) {
					if (is_array($changed[$row2['field']]))
						array_unshift($changed[$row2['field']], $row2['old_value']);
					else
						$changed[$row2['field']] = array($row2['old_value'], $changed[$row2['field']]);
				} else {
					$changed[$row2['field']] = $row2['old_value'];
				}
				if (is_array($changed[$row2['field']]))
					sort($changed[$row2['field']]);
			}
			foreach($changed as $k=>$v) {
				$new = $this->get_val($field_hash[$k], $created, $created['id'], false, $this->table_rows[$field_hash[$k]]);
				$created[$k] = $v;
				$old = $this->get_val($field_hash[$k], $created, $created['id'], false, $this->table_rows[$field_hash[$k]]);
				$gb_row = $gb_cha->get_new_row();
//				eval_js('apply_changes_to_'.$k.'=function(){element = document.getElementsByName(\''.$k.'\')[0].value=\''.$v.'\';};');
//				$gb_row->add_action('href="javascript:apply_changes_to_'.$k.'()"', 'Apply', null, 'apply');
				$gb_row->add_data($row['edited_on'], Base_UserCommon::get_user_login($row['edited_by']), $field_hash[$k], $old, $new);
			}
		}
		$theme = $this->init_module('Base/Theme');
		$theme->assign('table',$this->get_html_of_module($gb_cha));
		$theme->assign('label',$this->lang->t('Recent Changes'));
		$theme->display('View_history');
	}
	public function view_edit_history($id){
		if ($this->is_back()) 
			return $this->back();
		$this->init();
		$gb_cur = $this->init_module('Utils/GenericBrowser', null, $this->tab.'__current');
		$gb_cha = $this->init_module('Utils/GenericBrowser', null, $this->tab.'__changes');
		$gb_ori = $this->init_module('Utils/GenericBrowser', null, $this->tab.'__original');
		
		$table_columns = array(	array('name'=>$this->lang->t('Field'), 'width'=>1, 'wrapmode'=>'nowrap'),
								array('name'=>$this->lang->t('Value'), 'width'=>1, 'wrapmode'=>'nowrap'));
		$table_columns_changes = array(	array('name'=>$this->lang->t('Date'), 'width'=>1, 'wrapmode'=>'nowrap'),
										array('name'=>$this->lang->t('Username'), 'width'=>1, 'wrapmode'=>'nowrap'),
										array('name'=>$this->lang->t('Field'), 'width'=>1, 'wrapmode'=>'nowrap'),
										array('name'=>$this->lang->t('Old value'), 'width'=>1, 'wrapmode'=>'nowrap'),
										array('name'=>$this->lang->t('New value'), 'width'=>1, 'wrapmode'=>'nowrap'));
		
		$gb_cur->set_table_columns( $table_columns );
		$gb_ori->set_table_columns( $table_columns );
		$gb_cha->set_table_columns( $table_columns_changes );
		
		$created = Utils_RecordBrowserCommon::get_record($this->tab, $id, true);
		$created['created_by_login'] = Base_UserCommon::get_user_login($created['created_by']);
		$field_hash = array();
		$edited = DB::GetRow('SELECT ul.login, c.edited_on FROM '.$this->tab.'_edit_history AS c LEFT JOIN user_login AS ul ON ul.id=c.edited_by WHERE c.'.$this->tab.'_id=%d ORDER BY edited_on DESC',array($id));
		if (!isset($edited['login']))
			return;
		$gb_cur->add_row($this->lang->t('Edited by'), $edited['login']);
		$gb_cur->add_row($this->lang->t('Edited on'), $edited['edited_on']);
		foreach($this->table_rows as $field => $args) {
			$field_hash[$args['id']] = $field;
			$val = $this->get_val($field, $created, $created['id'], false, $args);
			if ($created[$args['id']] !== '') $gb_cur->add_row($field, $val);
		}

		$ret = DB::Execute('SELECT ul.login, c.id, c.edited_on, c.edited_by FROM '.$this->tab.'_edit_history AS c LEFT JOIN user_login AS ul ON ul.id=c.edited_by WHERE c.'.$this->tab.'_id=%d ORDER BY edited_on DESC',array($id));
		while ($row = $ret->FetchRow()) {
			$changed = array();
			$ret2 = DB::Execute('SELECT * FROM '.$this->tab.'_edit_history_data WHERE edit_id=%d',array($row['id']));
			while($row2 = $ret2->FetchRow()) {
				if (isset($changed[$row2['field']])) {
					if (is_array($changed[$row2['field']]))
						array_unshift($changed[$row2['field']], $row2['old_value']);
					else
						$changed[$row2['field']] = array($row2['old_value'], $changed[$row2['field']]);
				} else {
					$changed[$row2['field']] = $row2['old_value'];
				}
				if (is_array($changed[$row2['field']]))
					sort($changed[$row2['field']]);
			}
			foreach($changed as $k=>$v) {
				$new = $this->get_val($field_hash[$k], $created, $created['id'], false, $this->table_rows[$field_hash[$k]]);
				$created[$k] = $v;
				$old = $this->get_val($field_hash[$k], $created, $created['id'], false, $this->table_rows[$field_hash[$k]]);
				$gb_cha->add_row($row['edited_on'], Base_UserCommon::get_user_login($row['edited_by']), $field_hash[$k], $old, $new);
			}
		}
		$gb_ori->add_row($this->lang->t('Created by'), $created['created_by_login']);
		$gb_ori->add_row($this->lang->t('Created on'), $created['created_on']);
		foreach($this->table_rows as $field => $args) {
			$val = $this->get_val($field, $created, $created['id'], false, $args);
			if ($created[$args['id']] !== '') $gb_ori->add_row($field, $val);
		}
		$theme = $this->init_module('Base/Theme');
		$theme->assign('table',$this->get_html_of_module($gb_cur));
		$theme->assign('label',$this->lang->t('Current Record'));
		$theme->display('View_history');
		$theme = $this->init_module('Base/Theme');
		$theme->assign('table',$this->get_html_of_module($gb_cha));
		$theme->assign('label',$this->lang->t('Changes History'));
		$theme->display('View_history');
		$theme = $this->init_module('Base/Theme');
		$theme->assign('table',$this->get_html_of_module($gb_ori));
		$theme->assign('label',$this->lang->t('Original Record'));
		$theme->display('View_history');
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
		$this->update_record($id,$values);
		return false;
	}
	public function set_defaults($arg){
		foreach ($arg as $k=>$v)
			$this->custom_defaults[$k] = $v;
	}
	public function set_filters_defaults($arg){
		if (!$this->isset_module_variable('def_filter')) $this->set_module_variable('def_filter', $arg);
	}	
	public function caption(){
		return $this->caption.': '.$this->action;
	}
	public function recordpicker($element, $format, $crits=array(), $cols=array(), $order=array(), $filters=array()) {
		if (!isset($this->lang)) $this->lang = $this->init_module('Base/Lang');
		$this->init();
		$this->set_module_variable('element',$element);
		$this->set_module_variable('format_func',$format);
		$theme = $this->init_module('Base/Theme');
		$theme->assign('filters', $this->show_filters($filters, $element));
		foreach	($crits as $k=>$v) {
			if (!is_array($v)) $v = array($v);
			if (isset($this->crits[$k]) && !empty($v)) {
				foreach ($v as $w) if (!in_array($w, $this->crits[$k])) $this->crits[$k][] = $w;
			} else $this->crits[$k] = $v;
		}
		$theme->assign('table', $this->show_data($this->crits, $cols, $order, false, true));
		load_js('modules/Utils/RecordBrowser/rpicker.js');
			
		$rpicker_ind = $this->get_module_variable('rpicker_ind');
		$init_func = 'init_all_rpicker_'.$element.' = function(id, cstring){';
		foreach($rpicker_ind as $v)
			$init_func .= 'rpicker_init(\''.$element.'\','.$v.',\''.Base_ThemeCommon::get_template_file('images/active_on.png').'\',\''.Base_ThemeCommon::get_template_file('images/active_off2.png').'\');';
		$init_func .= '}';
		eval_js($init_func.';init_all_rpicker_'.$element.'();');
		$theme->display('Record_picker');
	}
	public function admin() {
		$ret = DB::Execute('SELECT tab FROM recordbrowser_table_properties');
		$tb = $this->init_module('Utils/TabbedBrowser');
		while ($row=$ret->FetchRow()) {
			$tb->set_tab(ucfirst(str_replace('_',' ',$row['tab'])), array($this, 'record_management'), array($row['tab']));
		}
		$this->display_module($tb);
		$tb->tag();
	}
	public function record_management($table){
		$rb = $this->init_module('Utils/RecordBrowser',$table,$table);
		$this->display_module($rb, null, 'administrator_panel');
	}
	
	public function enable_quick_new_records() {
		$this->add_in_table = true;
	}
	public function set_custom_filter($arg, $spec){
		$this->custom_filters[$arg] = $spec;
	}
	public function set_crm_filter($field){
		$this->filter_field = $field;
	}
	public function mini_view($cols, $crits, $order, $info, $limit=null){
		$this->init();
		$gb = $this->init_module('Utils/GenericBrowser',$this->tab,$this->tab);
		$field_hash = array();
		foreach($this->table_rows as $field => $args)
			$field_hash[$args['id']] = $field;
		$header = array();
		$cut = array();
		foreach($cols as $k=>$v) {
			if (isset($v['cut'])) $cut[] = $v['cut'];
			else $cut[] = -1;
			if (is_array($v)) {
				$arr = array('name'=>$field_hash[$v['field']], 'width'=>$v['width']);
				$cols[$k] = $v['field']; 
			} else {
				$arr = array('name'=>$field_hash[$v]);
				$cols[$k] = $v; 
			}
			$arr['wrapmode'] = 'nowrap';
			$header[] = $arr;
		}
		$gb->set_table_columns($header);

		$clean_order = array();
		foreach($order as $k=>$v) {
			$clean_order[] = array('column'=>$field_hash[$k],'order'=>$field_hash[$k],'direction'=>$v);
		}
		if ($limit!=null) $limit = array('offset'=>0, 'numrows'=>$limit);
		$records = Utils_RecordBrowserCommon::get_records($this->tab, $crits, array(), $clean_order, $limit);
		foreach($records as $v) {
			$gb_row = $gb->get_new_row();
			$arr = array();
			foreach($cols as $k=>$w) {
				$s = $this->get_val($field_hash[$w], $v, $v['id'], false, $this->table_rows[$field_hash[$w]]);
				$content = strip_tags($s);
				if ($cut[$k]!=-1 && strlen($content)>$cut[$k]) {
					$label = '<span '.Utils_TooltipCommon::open_tag_attrs($content).'>'.substr($content, 0, $cut[$k]).'...</span>';
					$arr[] = str_replace($content, $label, $s);
				} else $arr[] = $s;
			}
			$gb_row->add_data_array($arr);
			if (is_callable($info)) {
				$additional_info = call_user_func($info, $v).'<hr>';
			} else $additional_info = '';
			$gb_row->add_info($additional_info.Utils_RecordBrowserCommon::get_html_record_info($this->tab, $v['id']));
			$gb_row->add_action($this->create_callback_href(array($this,'navigate'),array('view_entry', 'view',$v['id'])),$this->lang->t('View'));
		}
		$this->display_module($gb);
	}
}
?>
