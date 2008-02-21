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
		if (!isset($this->access_callback)) $this->access_callback = explode('::', DB::GetOne('SELECT access_callback FROM recordbrowser_table_properties WHERE tab=%s', array($this->tab)));
		if ($this->access_callback === '' || !is_callable($this->access_callback)) return true;
		return call_user_func($this->access_callback, $action, $param);
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
		if (isset($_REQUEST['tab'])) {
			$this->tab = $_REQUEST['tab'];
			$this->call_callback_href(array($this,'view_entry'),array($_REQUEST['action'], $_REQUEST['id'], isset($_REQUEST['defaults'])?$_REQUEST['defaults']:array()));
		} else {
			if ($this->get_access('browse')===false) {
				print($this->lang->t('You are not authorised to browse this data.'));
				return;
			}
			$this->is_on_main_page = true;
			Base_ActionBarCommon::add('add',$this->lang->t('New'), $this->create_callback_href(array($this,'view_entry'),array('add')));

			$filters = $this->show_filters();
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
				while ($row2 = $ret2->FetchRow()) $arr[$row2['value']] = $this->get_val($filter, array($this->table_rows[$filter]['id']=>$row2['value']), $row2[$this->tab.'_id'], true, $this->table_rows[$filter]);
			}
			if ($this->table_rows[$filter]['type']=='commondata') {
				 $cddata = Utils_CommonDataCommon::get_array(str_replace('::','/',$this->table_rows[$filter]['param']), true);
				 foreach ($arr as $k=>$v) {
//				 	$arr[$k] = $cddata[$v];
				 }
			}
			natcasesort($arr);
			$arr = array('__NULL__'=>'--')+$arr;
			$form->addElement('select', str_replace(' ','_',$filter), $filter, $arr);
			$filters[] = str_replace(' ','_',$filter);
		}
		$form->addElement('submit', 'submit', 'Show');
		$def_filt = $this->get_module_variable('def_filter', array());
		$form->setDefaults($def_filt);
		$this->crits = array();
		if ($form->validate()) {
			$vals = $form->exportValues();
			unset($vals['submit']);
			unset($vals['submited']);
			$def_filt = array();
			foreach($vals as $k=>$v)
				if ($v!=='__NULL__') {
					$this->crits[str_replace('_',' ',$k)] = array($v);
					$def_filt[$k] = $v;
				}
			$this->set_module_variable('def_filter', $def_filt);
		}
		foreach($def_filt as $k=>$v)
			$this->crits[str_replace('_',' ',$k)] = array($v);
		$theme = $this->init_module('Base/Theme');
		$form->assign_theme('form',$theme);
		$theme->assign('filters', $filters);
		$theme->assign('id', $f_id);
		if (!empty($def_filt)) $theme->assign('dont_hide', true);
		return $this->get_html_of_module($theme, 'Filter', 'display');
	}
	//////////////////////////////////////////////////////////////////////////////////////////
	public function show_data($crits = array(), $cols = array(), $order = array(), $fs_links = false, $admin = false, $special = false) {
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
		if ($is_searching!='') {
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
			if ($args['type']!='multiselect' && $args['type']!='select') $arr['search'] = str_replace(' ','_',$field);
			$table_columns[] = $arr;
			array_push($table_columns_SQL, 'e.'.$field);
		}

		$table_columns_SQL = join(', ', $table_columns_SQL);
		if ($this->browse_mode == 'recent')
			$table_columns[] = array('name'=>$this->lang->t('Visited on'));
			 
		$gb->set_table_columns( $table_columns );

		if ($this->browse_mode != 'recent')
			$gb->set_default_order($order, $this->changed_view);

		if ($this->add_button!==null) {
			$gb->set_custom_label($this->add_button);
		}
		$search = $gb->get_search_query(true);
		$search_res = array();
		foreach ($search as $k=>$v) {
			$search_res['"'.str_replace('_',' ',$k)] = $v;
		} 
		$crits = array_merge($crits, $search_res);
		//print_r($crits);
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
				$date_format = Base_RegionalSettingsCommon::date_format();
				$rec_tmp[$row[$this->tab.'_id']]['visited_on'] = strftime($date_format,strtotime($row['visited_on']));
			}
			$records = $rec_tmp;
		}
		if ($special) $rpicker_ind = array();
		foreach ($records as $row) {
			$gb_row = $gb->get_new_row();
			if (!$admin && $this->favorites) {
				$isfav = (DB::GetOne('SELECT user_id FROM '.$this->tab.'_favorite WHERE user_id=%d AND '.$this->tab.'_id=%d', array(Acl::get_user(), $row['id']))!==false);
				$row_data = array('<a '.Utils_TooltipCommon::open_tag_attrs(($isfav?$this->lang->t('This item is on your favourites list<br>Click to remove it from your favorites'):$this->lang->t('Click to add this item to favorites'))).' '.$this->create_callback_href(array($this,($isfav?'remove_from_favs':'add_to_favs')), array($row['id'])).'><img border="0" src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','star_'.($isfav==false?'no':'').'fav.png').'" /></a>');
			} else $row_data = array();
			if ($special) { 
				$func = $this->get_module_variable('format_func');
				$element = $this->get_module_variable('element');
				$row_data = array('<a href="javascript:addto_'.$element.'('.$row['id'].', \''.call_user_func($func, $row['id']).'\');"><img src="null"  border="0" name="leightbox_rpicker_'.$element.'_'.$row['id'].'" /></a>');
				$rpicker_ind[] = $row['id'];
			}
			
			foreach($this->table_rows as $field => $args)
				if (($args['visible'] && !isset($cols[$args['id']])) || (isset($cols[$args['id']]) && $cols[$args['id']] === true)) {
//					$ret = $row[$args['id']];
					$row_data[] = $this->get_val($field, $row, $row['id'], $special, $args);
				}
			if ($this->browse_mode == 'recent')
				$row_data[] = $row['visited_on'];
			$gb_row->add_data_array($row_data);
			if (!isset($cols['Actions']) || $cols['Actions'])
			{
				if (!$special) {
					if ($fs_links===false) {
						$gb_row->add_action($this->create_callback_href(array($this,'view_entry'),array('view',$row['id'])),$this->lang->t('View'));
						if ($this->get_access('edit',$row)) $gb_row->add_action($this->create_callback_href(array($this,'view_entry'),array('edit',$row['id'])),$this->lang->t('Edit'));
						if ($admin) {
							if (!$row['active']) $gb_row->add_action($this->create_callback_href(array($this,'set_active'),array($row['id'],true)),$this->lang->t('Activate'), null, 'active-off');
							else $gb_row->add_action($this->create_callback_href(array($this,'set_active'),array($row['id'],false)),$this->lang->t('Deactivate'), null, 'active-on');
							$info = Utils_RecordBrowserCommon::get_record_info($this->tab, $row['id']);
							if ($info['edited_by']===null) $gb_row->add_action('',$this->lang->t('This record was never edited'),null,'history_inactive');
							else $gb_row->add_action($this->create_callback_href(array($this,'view_edit_history'),array($row['id'])),$this->lang->t('View edit history'),null,'history');
						} else 
						if ($this->get_access('delete',$row)) $gb_row->add_action($this->create_confirm_callback_href($this->lang->t('Are you sure you want to delete this record?'),array('Utils_RecordBrowserCommon','delete_record'),array($this->tab, $row['id'])),$this->lang->t('Delete'));
					} else {
						$gb_row->add_action($this->create_href(array('box_main_module'=>'Utils_RecordBrowser', 'box_main_constructor_arguments'=>array($this->tab), 'tab'=>$this->tab, 'id'=>$row['id'], 'action'=>'view')),$this->lang->t('View'));
						if ($this->get_access('edit',$row)) $gb_row->add_action($this->create_href(array('box_main_module'=>'Utils_RecordBrowser', 'box_main_constructor_arguments'=>array($this->tab), 'tab'=>$this->tab, 'id'=>$row['id'], 'action'=>'edit')),$this->lang->t('Edit'));
						if ($this->get_access('delete',$row)) $gb_row->add_action($this->create_confirm_callback_href($this->lang->t('Are you sure you want to delete this record?'),array('Utils_RecordBrowserCommon','delete_record'),array($this->tab, $row['id'])),$this->lang->t('Delete'));
					}
				}
				$gb_row->add_info(Utils_RecordBrowserCommon::get_html_record_info($this->tab, $row['id']));
			}
		}
		if ($special) {
			$this->set_module_variable('rpicker_ind',$rpicker_ind);
			return $this->get_html_of_module($gb);
		} else $this->display_module($gb);
	}
	//////////////////////////////////////////////////////////////////////////////////////////
	public function view_entry($mode='view', $id = null, $defaults = array()) {
		$js = true;
		if ($this->is_back())
			return false;
		$this->init();
		$record = Utils_RecordBrowserCommon::get_record($this->tab, $id);
		switch ($mode) {
			case 'add': $this->action = 'New record'; break;
			case 'edit': $this->action = 'Edit record';
						$this->noneditable_fields = $this->get_access('edit_fields', $record); 
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

		$this->prepare_view_entry_details($record, $mode, $id, $form);

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
				$this->update_record($id,$values);
			}
			return false;
		}

		if ($mode=='view') { 
			if ($this->get_access('edit',$record)) Base_ActionBarCommon::add('edit', $this->lang->ht('Edit'), $this->create_callback_href(array($this,'view_entry'), array('edit',$id)));
			if ($this->get_access('delete',$record)) Base_ActionBarCommon::add('delete', $this->lang->ht('Delete'), $this->create_confirm_callback_href($this->lang->t('Are you sure you want to delete this record?'),array('Utils_RecordBrowserCommon','delete_record'),array($this->tab, $id)));
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
				else $theme -> assign('history_tooltip', '<a '.Utils_TooltipCommon::open_tag_attrs($this->lang->t('Click to view edit history of currently displayed record')).' '.$this->create_callback_href(array($this,'view_edit_history'), array($id)).'><img border="0" src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','history.png').'" /></a>');
			}
		}
		if ($mode=='edit') 
			foreach($this->table_rows as $field => $args) 
				if (isset($this->noneditable_fields[$field]) && !$this->noneditable_fields[$field]) {
					$form->freeze($args['id']);
				} 

		if ($mode=='view') $form->freeze();
		if(!isset($renderer)) $renderer = new HTML_QuickForm_Renderer_TCMSArraySmarty(); 
		$form->accept($renderer);
		$data = $renderer->toArray();
		
		print($data['javascript'].'<form '.$data['attributes'].'>'.$data['hidden']."\n");

		$last_page = DB::GetOne('SELECT MIN(position) FROM '.$this->tab.'_field WHERE type = \'page_split\' AND field != \'General\'');
		$label = DB::GetOne('SELECT field FROM '.$this->tab.'_field WHERE position=%s', array($last_page));
		$this->mode = $mode;
		$this->view_entry_details(1, $last_page, $data, $theme, true);
		$ret = DB::Execute('SELECT position, field, param FROM '.$this->tab.'_field WHERE type = \'page_split\' AND position > %d', array($last_page));
		$row = true;
		if ($mode=='view')
			print("</form>\n");
		$cols = 2; //TODO: fix!
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
			$record = Utils_RecordBrowserCommon::get_record($this->tab, $id, true);
			$ret = DB::Execute('SELECT * FROM '.$this->tab.'_addon');
			while ($row = $ret->FetchRow()) {
				$mod = $this->init_module($row['module']);
				if (!is_callable(array($mod,$row['func']))) trigger_error('Invalid callback method '.$row['module'].'::'.$row['func'], E_USER_ERROR);
				$tb->set_tab($this->lang->t($row['label']),array($this, 'display_module'), array($mod, array($record), $row['func']), $js);
			}
		}
		$this->display_module($tb);
		if ($mode=='add' || $mode=='edit') print("</form>\n");
		$tb->tag();

		return true;
	} //view_entry
	
	public function view_entry_details($from, $to, $data, $theme=null, $main_page = false, $cols = 2){
		if ($theme==null) $theme = $this->init_module('Base/Theme');
		$fields = array();
		$longfields = array();
		foreach($this->table_rows as $field => $args) 
			if ($args['position'] >= $from && ($to == -1 || $args['position'] < $to)) 
			{	
				if (!isset($data[$args['id']])) $data[$args['id']] = array('label'=>'', 'html'=>'');
					if ($args['type']<>'long text') {
						$fields[$args['id']] = array(	'label'=>$data[$args['id']]['label'],
												'element'=>$args['id'],
												'html'=>$data[$args['id']]['html'],
												'error'=>isset($data[$args['id']]['error'])?$data[$args['id']]['error']:null,
												'required'=>isset($args['required'])?$args['required']:null,
												'type'=>$args['type']);
					} else {
						$longfields[$args['id']] = array(	'label'=>$data[$args['id']]['label'],
												'element'=>$args['id'],
												'html'=>$data[$args['id']]['html'],
												'error'=>isset($data[$args['id']]['error'])?$data[$args['id']]['error']:null,
												'required'=>isset($args['required'])?$args['required']:null,
												'type'=>$args['type']);
					}
			}
		if ($cols==0) $cols=2;
		$theme->assign('fields', $fields);
		$theme->assign('cols', $cols);
		$theme->assign('longfields', $longfields);
		$theme->assign('action', $this->mode);
		$theme->assign('Form_data', $data);
		$theme->assign('required_note', $this->lang->t('Indicates required fields.'));
		
		$theme->assign('caption',$this->caption);
		$theme->assign('icon',$this->icon);

		$theme->assign('main_page',$main_page);

		if ($main_page) $tpl = DB::GetOne('SELECT tpl FROM recordbrowser_table_properties WHERE tab=%s', array($this->tab));
		else $tpl = '';
		$theme->display(($tpl!=='')?$tpl:'View_entry', ($tpl!==''));
	}

	public function prepare_view_entry_details($record, $mode, $id, $form){
		$init_js = '';
		foreach($this->table_rows as $field => $args){
			if (isset($this->QFfield_callback_table[$field])) {
				call_user_func($this->QFfield_callback_table[$field], $form, $args['id'], $this->lang->t($args['name']), $mode, $mode=='add'?array():$record[$args['id']], $args);
				continue;
			}
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
											$ret = DB::Execute('SELECT * FROM '.$tab.'_data WHERE field=%s ORDER BY value', array($col));
											while ($row = $ret->FetchRow()) $comp[$row[$tab.'_id']] = $row['value'];
										}
										$form->addElement($args['type'], $args['id'], '<span id="_'.$args['id'].'__label">'.$this->lang->t($args['name']).'</span>', $comp, array('id'=>$args['id']));
										if ($mode!=='add') $form->setDefaults(array($args['id']=>$record[$args['id']]));
									} else {
										$form->addElement('static', $args['id'], '<span id="_'.$args['id'].'__label">'.$this->lang->t($args['name']).'</span>', array('id'=>$args['id']));
										$form->setDefaults(array($args['id']=>$record[$args['id']]));
									}
									break;
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
	public function view_edit_history($id){
		if ($this->is_back()) return false;
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
		
/*		$table_columns_SQL = array();
		foreach($this->table_rows as $field => $args) {
			if ($field=='id') continue;
			$table_columns[] = array('name'=>$args['name']);
			array_push($table_columns_SQL, 'c.'.$field);
		}
		$table_columns_SQL = join(', ', $table_columns_SQL);
*/
		$gb_cur->set_table_columns( $table_columns );
		$gb_ori->set_table_columns( $table_columns );
		$gb_cha->set_table_columns( $table_columns_changes );
		
		$original = Utils_RecordBrowserCommon::get_record($this->tab, $id, true);
		$created = $original;
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
	public function caption(){
		return $this->caption.': '.$this->action;
	}
	public function recordpicker($element, $format, $crits=array(), $cols=array(), $order=array(), $filters=array()) {
		if (!isset($this->lang)) $this->lang = $this->init_module('Base/Lang');
		$this->init();
		$icon_on = Base_ThemeCommon::get_template_file('Utils_RecordBrowser','active-on.png');
		$icon_off = Base_ThemeCommon::get_template_file('Utils_RecordBrowser','active-off.png');
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
		$theme->assign('table', $this->show_data($this->crits, $cols, $order, false, false, true));

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
