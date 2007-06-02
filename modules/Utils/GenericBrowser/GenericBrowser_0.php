<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_GenericBrowser_Row_Object {
	private $GBobj;
	private $num;
	
	public function __construct($GBobj, $num){
		$this->GBobj = $GBobj;
		$this->num = $num;
	}
	
	public function add_data($args){
		$args = func_get_args();
		$this->GBobj->__add_row_data($this->num,$args);
	}
	
	public function add_data_array($arg){
		if (!is_array($arg)) trigger_error('Invalid argument for add_data_array.',E_USER_ERROR);
		$this->GBobj->__add_row_data($this->num,$arg);
	}
	
	public function add_action($tag_attrs,$label){
		$this->GBobj->__add_row_action($this->num,'<a '.$tag_attrs.'>',$label,'</a>');
	}
	
}


class Utils_GenericBrowser extends Module {
	private $columns;
	private $columns_qty;
	private $rows = array();
	private $lang;
	private $rows_qty;
	private $actions = array();
	private $en_actions = false;
	private $cur_row = -1;
	
	public function construct($name=false) {
		if (!$name) {
			trigger_error('GenericBrowser did not receive name for instance in module '.$this->get_parent_type().'.<br>Use $this->init_module(\'Utils/GenericBrowser\',\'instance name here\');',E_USER_ERROR);
			return;
		}
		$this->set_name($name);
	}
	
	public function set_table_columns($arg){
		if (!is_array($arg)) {
			print('Invalid argument for table_columns, aborting.<br>');
			return;
		}
		$this->columns = $arg;
		$this->columns_qty = count($arg);
	}

	public function set_default_order($arg){
		if ($this->isset_module_variable('first_display')) return;
		if (!is_array($arg)){
			print('Invalid argument for set_default_order, aborting.<br>');
			return;
		}
		$order=array();
		foreach($arg as $k=>$v){
			$ord = false;
			foreach($this->columns as $val) 
				if ($val['name'] == $k) {
					$ord = $val['order'];
					break;
				}
			if ($ord===false) {
				trigger_error('Invalid column name for default order: '.$k,E_USER_ERROR);
			}
			$order[] = array('column'=>$k,'direction'=>$v,'order'=>$ord);
		}
		$this->set_module_variable('order',$order);
		$this->set_module_variable('default_order',$order);
	}
	
	public function get_new_row() {
		$this->cur_row++;
		if ($this->per_page && $this->cur_row>=$this->per_page) trigger_error('Added more rows than expected, aborting.',E_USER_ERROR);
		return new Utils_GenericBrowser_Row_Object($this,$this->cur_row);
	}
	
	public function __add_row_data($num,$arg) {
		if (!is_array($arg)) {
			trigger_error('Invalid argument 2 for add_row_array, aborting.<br>',E_USER_ERROR);
			return;
		}
		if (count($arg)!=$this->columns_qty) {
			trigger_error('Invalid size of array for argument 2 while adding data, was '.count($arg).', should be '.$this->columns_qty.'. Aborting.<br>',E_USER_ERROR);
			return;
		}
		$this->rows[$num] = $arg;
	}

	public function __add_row_action($num,$open,$label,$close) {
		if (!isset($this->lang)) $this->lang = & $this->pack_module('Base/Lang');
		$this->actions[$num][strtolower(trim($label))] = array('open'=>$open,'label'=>$this->lang->t($label),'close'=>$close);
		$this->en_actions = true;
	}

	public function add_row($args) {
		$args = func_get_args();
		$this->add_row_array($args);
	}

	public function add_row_array($arg) {
		if (!is_array($arg)) {
			trigger_error('Invalid argument 2 for add_row_array, aborting.<br>',E_USER_ERROR);
			return;
		}
		if (count($arg)!=$this->columns_qty) {
			trigger_error('Invalid size of array for argument 2 while adding data, was '.count($arg).', should be '.$this->columns_qty.'. Aborting.<br>',E_USER_ERROR);
			return;
		}
		$this->rows[] = $arg;
		$this->cur_row++;
		if ($this->per_page && $this->cur_row>=$this->per_page) trigger_error('Added more rows than expected, aborting.',E_USER_ERROR);
	}

	public function get_limit($max) {
		$offset = $this->get_module_variable('offset',0);
		$per_page = $this->get_module_variable('per_page',Base_User_SettingsCommon::get_user_settings('Utils/GenericBrowser','per_page'));
		$this->rows_qty = $max;
		if ($offset>=$max) $offset = 0;
		
		if($this->get_unique_href_variable('next')=='1')
			$offset += $per_page;
		elseif($this->get_unique_href_variable('prev')=='1') {
			$offset -= $per_page;
			if ($offset<0) $offset=0;
		}
		elseif($this->get_unique_href_variable('first')=='1')
			$offset = 0;
		elseif($this->get_unique_href_variable('last')=='1')
			$offset = floor($this->rows_qty/$per_page)*$per_page;
		
		$this->set_module_variable('offset', $offset);
		$this->set_module_variable('per_page', $per_page);
		return array(	'numrows'=>$per_page,
						'offset'=>$offset);
	}

	public function get_query_order() {
		$ch_order = $this->get_unique_href_variable('change_order');
		if ($ch_order) $this->change_order($ch_order);
		$order = $this->get_module_variable('order');
		ksort($order);
		$sql = '';
		$ohd = '';
		$first = true;
		foreach($order as & $v){
			$ohd .= ($first?'':',').' '.$v['column'].' '.$v['direction'];
			$sql .= ($first?'':',').' '.$v['order'].' '.$v['direction'];
			$first = false;
		}
		if ($sql) $sql = ' ORDER BY'.$sql;
		$this->set_module_variable('order_history_display',$ohd);
		$this->set_module_variable('order',$order);
		return $sql;
	}
	
	public function get_order(){
		$this->get_query_order();	
		$order = $this->get_module_variable('order');
		return $order;
	}
	
	public function change_order($ch_order){
		$order = $this->get_module_variable('order');
		ksort($order);
		foreach($this->columns as $val) 
			if ($val['name'] == $ch_order) {
				$ord = $val['order'];
				break;
			}			
		$pos = -1;
		foreach($order as $k=>$v) {
			if ($v['order']==$ord) {
				$pos = $k;
				break;
			}
		}
		if ($pos == 0) {
			if ($order[$pos]['column']==$ch_order && $order[$pos]['direction']=='ASC') $order[$pos]['direction']='DESC';
			else $order[$pos]['direction']='ASC';
			$order[$pos]['column']=$ch_order;
			$this->set_module_variable('order',$order);
			return;
		}
		if ($pos == -1){
			$new_order = array(array('column'=>$ch_order,'direction'=>'ASC','order'=>$ord));
			foreach($order as $k=>$v) 
				$new_order[] = $v;
			$this->set_module_variable('order',$new_order);
			return;
		}
		$new_order = array();
		unset($order[$pos]);
		foreach($order as $k=>$v){
			$new_order[$k+($k<$pos?1:0)] = $v;
		}
		$new_order[0]=array('column'=>$ch_order,'direction'=>'ASC','order'=>$ord);
		$this->set_module_variable('order',$new_order);
	}
	
	public function get_search_query($arg = array()){
		$search = $this->get_module_variable('search');
		$where = '(';
		if(!$this->is_adv_search_on()) {
			foreach($this->columns as $k=>$v){
				if ($v['search'] && $search['__keyword__']) $where .= ($where?' OR':'').' '.$v['search'].' LIKE '.DB::Concat('\'%\'',sprintf('%s',DB::qstr($search['__keyword__'])),'\'%\'');
			}
		} else {
			foreach($this->columns as $k=>$v){
				if ($v['search'] && $search[$v['search']]) $where .= ($where?' AND':'').' '.$v['search'].' LIKE '.DB::Concat('\'%\'',sprintf('%s',DB::qstr($search[$v['search']])),'\'%\'');
			}
		}
		$where .= ')';
		if ($arg) $where .= ' AND ('.$arg.')';
		return $where;
	}
	
	public function is_adv_search_on(){
		return $this->get_module_variable('adv_search',Base_User_SettingsCommon::get_user_settings('Utils_GenericBrowser','adv_search'));
	}

	public function switch_adv_search(){
		if ($this->is_adv_search_on()) $this->set_module_variable('adv_search',0);
		else $this->set_module_variable('adv_search',0);
		location(array());
	}

	public function check_if_row_fits_array($row,$adv){
		$search = $this->get_module_variable('search');
		if (!$adv){
			$ret = true;
			foreach($this->columns as $k=>$v){
				if ($v['search'] && $search['__keyword__']) {
					$ret = false;
					if (strpos(preg_replace('/<[^<>]*>/','',$row[$k]),$search['__keyword__'])!==false) return true;
				}
			}
			return $ret;
		} else {
			foreach($this->columns as $k=>$v){
				if ($v['search'] && $search[$v['search']]) if (strpos(preg_replace('/<[^<>]*>/','',$row[$k]),$search[$v['search']])===false) return false;
			}
			return true;
		}
	}

	public function simple_table($header, $data, $page_split = true, $template, $order=true) {
		$len = count($header);
		foreach($header as $i=>$h) {
			if(is_string($h)) $header[$i]=array('name'=>$h);
			if($order) {
				$header[$i]['order']="$i";
			} else
				unset($header[$i]['order']);
		}
		$this->set_table_columns($header);
		
		if($order && ($order = $this->get_order()) && $order=$order[0]) {
			$col = array();
			foreach($data as $j=>$d)
				foreach($d as $i=>$c)
					if($i==$order['order']) {
						if(is_array($c)) $xxx = $c['value'];
							else $xxx = $c;
						if(isset($header[$i]['order_eregi'])) {
							eregi($header[$i]['order_eregi'],$xxx, $ret);
							$xxx = $ret[1];
						}
						$xxx = strtolower($xxx);
						$col[$j] = $xxx;
					}

			asort($col);
			$data2 = array();
			foreach($col as $j=>$v) {
				$data2[] = $data[$j];
			}
			if($order['direction']=='ASC')
				$data = $data2;
			else
				$data = array_reverse($data2);
		}
		
		if ($page_split){
			$cd = count($data);
			$limit = $this->get_limit($cd);
			for($i=$limit['offset']; $i<$limit['offset']+$limit['numrows'] && $i<$cd; $i++){
				$this->add_row_array($data[$i]);
			}
			
		} else {
			foreach($data as $row)
				$this->add_row_array($row);
		}
		$this->body($template); 
	}

	public function automatic_display($paging=true){
		$rows = array();
		foreach($this->rows as $k=>$v){
			if ($this->check_if_row_fits_array($v,$this->is_adv_search_on())) $rows[] = $v;
		}
		$this->rows = array();
		$limit = $this->get_limit(count($rows));
		$id = 0;
		foreach($rows as $v) {
			if (!$paging || ($id>=$limit['offset'] && $id<$limit['offset']+$limit['numrows'])){
				$this->add_row_array($v);
			}
			$id++;
		}
		$this->body();
	}

	public function query_order_limit($query,$query_numrows) {
		$query_order = $this->get_query_order();
		$qty = DB::GetOne($query_numrows);
		$query_limits = $this->get_limit($qty);
		return DB::SelectLimit($query.$query_order,$query_limits['numrows'],$query_limits['offset']);
	}

	public function body($template,$automatic=false,$paging=true){
		if ($automatic) {
			$this->automatic_display($paging);
			return;
		}
		$this->set_module_variable('first_display','done');
		if (!$this->lang) $this->lang = $this->pack_module('Base/Lang');
		$theme = & $this->init_module('Base/Theme');
		$per_page = $this->get_module_variable('per_page');
		$order = $this->get_module_variable('order');
		if (!$this->get_name()) return;
		
		$ch_adv_search = $this->get_unique_href_variable('adv_search');
		if (isset($ch_adv_search)) {
			$this->set_module_variable('adv_search',$ch_adv_search);
			$this->set_module_variable('search',array());
			location(array());
		} 

		$search = $this->get_module_variable('search');

		$renderer =& new HTML_QuickForm_Renderer_TCMSArraySmarty();
		$form = $this->init_module('Libs/QuickForm');
		if(isset($this->rows_qty)) {
			$form->addElement('select','per_page',$this->lang->t('Number of rows per page'), array(5=>5,10=>10,25=>25,50=>50,100=>100), 'onChange="'.$form->get_submit_form_js(false).'"');
			$form->setDefaults(array('per_page'=>$per_page));
		}
		$search_on=false;
		if(!$this->is_adv_search_on()) {
			foreach($this->columns as $k=>$v)
				if ($v['search']) {
					$form->addElement('text','search',$this->lang->t('Keyword'), array('onfocus'=>'if (this.value=="'.$this->lang->t('search keyword').'") this.value="";','onblur'=>'if (this.value=="") this.value="'.$this->lang->t('search keyword').'";'));
					$form->setDefaults(array('search'=>$search['__keyword__']?$search['__keyword__']:$this->lang->t('search keyword',true)));
					$search_on=true;
					break;
				}
		} else {
			foreach($this->columns as $k=>$v)
				if ($v['search']) {
					$form->addElement('text','search__'.$v['search'],'',array('onfocus'=>'if (this.value=="'.$this->lang->t('search keyword').'") this.value="";','onblur'=>'if (this.value=="") this.value="'.$this->lang->t('search keyword').'";'));
					$form->setDefaults(array('search__'.$v['search']=>$search[$v['search']]?$search[$v['search']]:$this->lang->ht('search keyword')));
					$search_on=true;
				}
		}
		if ($search_on) $form->addElement('submit','submit_search',$this->lang->t('Search'));
		$form->accept($renderer);
		$form_array = $renderer->toArray();
		$theme->assign('form_data', $form_array);
		$theme->assign('form_name', $form->getAttribute('name'));

		// form processing
		if($form->validate()) {
			$values = $form->exportValues();
			$this->set_module_variable('per_page',$values['per_page']);
			Base_User_SettingsCommon::save_user_settings('Utils/GenericBrowser','per_page',$values['per_page']);
			$search = array();
			foreach ($values as $k=>$v){
				if ($k=='search') {  
					if ($v!=$this->lang->t('search keyword'))
						$search['__keyword__'] = $v;
					break;
				}  
				if (substr($k,0,8)=='search__') {
					$val = substr($k,8);
					if ($v!=$this->lang->t('search keyword') && $v!='') $search[$val] = $v;
				}
			}
			$this->set_module_variable('per_page',$form->exportValue('per_page'));
			$this->set_module_variable('search',$search);
			location(array());
		}
		
		// maintance mode -> action
		$md5_id = md5($this->get_unique_id());
		if($this->isset_unique_href_variable('action'))
			switch($this->get_unique_href_variable('action')) {
				case 'reset_order':
					$this->set_module_variable('order',$this->get_module_variable('default_order'));
					$this->get_order();
					break;
				case 'enable':
					DB::Execute('UPDATE generic_browser SET display=1 WHERE name=%s AND column_id=%d',array($md5_id,$this->get_unique_href_variable('id')));
					break;
				case 'disable':
					DB::Execute('UPDATE generic_browser SET display=0 WHERE name=%s AND column_id=%d',array($md5_id,$this->get_unique_href_variable('id')));
					break;
				case 'move':
					$new_id = $this->get_unique_href_variable('new_id');
					$rid = $this->get_unique_href_variable('id');
					$old_id = DB::GetOne('SELECT column_pos FROM generic_browser WHERE column_id=%d AND name=%s', array($rid, $md5_id));
					if($new_id==$old_id) break;
					if($new_id<$old_id) {
						for($i=$new_id; $i<$old_id; $i++)
							DB::Execute('UPDATE generic_browser SET column_pos=column_pos+1 WHERE name=%s AND column_pos=%d',array($md5_id,$i));
						DB::Execute('UPDATE generic_browser SET column_pos=%d WHERE name=%s AND column_id=%d',array($new_id,$md5_id,$rid));
					} else {
						for($i=$new_id; $i>$old_id; $i--)
							DB::Execute('UPDATE generic_browser SET column_pos=column_pos-1 WHERE name=%s AND column_pos=%d',array($md5_id,$i));
						DB::Execute('UPDATE generic_browser SET column_pos=%d WHERE name=%s AND column_id=%d',array($new_id,$md5_id,$rid));
					}
					break;
			}
		
		$col_pos = array();
		$ret = DB::Execute('SELECT column_id, column_pos, display FROM generic_browser WHERE name=%s',$md5_id);
		if($ret)
			while($row = $ret->FetchRow()) {
				$col_pos[$row['column_id']] = array('pos'=>$row['column_pos'], 'display'=>$row['display']); 
			}

		if (count($col_pos)!=$this->columns_qty) {
			$col_pos = null;
			DB::Execute('DELETE FROM generic_browser WHERE name=%s', $md5_id);
		}
		
		if (!$col_pos) {
			foreach(range(0, $this->columns_qty-1) as $v) {
				$col_pos[] = array('pos'=>$v, 'display'=>1);
				DB::Execute('INSERT INTO generic_browser(name,column_id, column_pos, display) VALUES(%s,%d,%d,1)',array($md5_id, $v, $v));
			}
		}

		$headers = array();
		if ($this->en_actions) {
			$actions_position = Base_User_SettingsCommon::get_user_settings('Utils/GenericBrowser','actions_position');
			if ($actions_position=='Left')	$headers[-1] = array('label'=>$this->lang->t('Actions'),'attrs'=>'style="width: 0%"'); 
			else		$headers[count($this->columns)] = array('label'=>$this->lang->t('Actions'),'attrs'=>'style="width: 0%"');
		}

		if(Base_AclCommon::i_am_sa() && Base_MaintenanceModeCommon::get_mode()) {
			foreach($this->columns as $i=>$v) {
				if($col_pos[$i]['display']) $enabled = '<a '.$this->create_unique_href(array('action'=>'disable', 'id'=>$i)).'>'.$this->lang->t('enabled').'</a>';
				else $enabled = '<a '.$this->create_unique_href(array('action'=>'enable', 'id'=>$i)).'>'.$this->lang->t('disabled').'</a>';
				if($col_pos[$i]['pos']>0) $left = '<a '.$this->create_unique_href(array('action'=>'move', 'id'=>$i, 'new_id'=>$col_pos[$i]['pos']-1)).'><=</a> ';
				else $left = '';
				if($col_pos[$i]['pos']<$this->columns_qty-1) $right = ' <a '.$this->create_unique_href(array('action'=>'move', 'id'=>$i, 'new_id'=>$col_pos[$i]['pos']+1)).'>=></a>';
				else $right = '';

				$headers[$col_pos[$i]['pos']]['label'] = $left.$enabled.$right.'<hr>';
			}
		}

		if($this->is_adv_search_on()) {
			$search_fields = array();
			foreach ($form_array as $k=>$v)
				if (substr($k,0,8)=='search__') {
					$i=0;
					foreach($this->columns as $g=>$u){
						if ('search__'.$u['search']==$k) {
							$search_fields[$col_pos[$i]['pos']] = $v['html'];
							break;
						}
						$i++;
					}
				}
			$theme->assign('search_fields', $search_fields);
		}

		$all_width = 0;
		foreach($this->columns as $k=>$v) {
			if (!$this->columns[$k]['width']) $this->columns[$k]['width'] = 100;
			$all_width += $this->columns[$k]['width'];
		}
		$i = 0;
		foreach($this->columns as $v) {
			if((!Base_AclCommon::i_am_sa() || !Base_MaintenanceModeCommon::get_mode()) && ((array_key_exists('display', $v) && $v['display']==false) || !$col_pos[$i]['display'])) {
				$i++;
				continue;
			}
			$headers[$col_pos[$i]['pos']]['label'] .= isset($v['order'])?'<a '.$this->create_unique_href(array('change_order'=>$v['name'])).'>'.$v['name'].'</a>':$v['name'];
			//if ($v['search']) $headers[$col_pos[$i]['pos']] .= $form_array['search__'.$v['search']]['label'].$form_array['search__'.$v['search']]['html'];
			if (!Base_User_SettingsCommon::get_user_settings('Utils/GenericBrowser','adv_history') && $v['name']==$order[0]['column']) $headers[$col_pos[$i]['pos']]['label'] .= ' '.$order[0]['direction']; 
			$headers[$col_pos[$i]['pos']]['attrs'] = 'style="width: '.intval(100*$v['width']/$all_width).'%"';
			$i++;
		}
		ksort($headers);
		foreach($headers as $v)
			$out_headers[] = array('label'=>$v['label'],'attrs'=>$v['attrs']);

		$out_data = array();
		
		foreach($this->rows as $i=>$r) {
			$col = array();
			if ($this->en_actions) {
				if ($actions_position=='Left') $column_no = -1;
				else $column_no = count($this->columns);
				if (!empty($this->actions[$i])) {
					$ac_theme = &$this->pack_module('Base/Theme');
					$ac_theme->assign('actions',$this->actions[$i]);
					ob_start();
					$ac_theme->display('Actions');
					$col[$column_no]['label'] = ob_get_contents();
					ob_end_clean();
				} else $col[$column_no]['label'] = '&nbsp;';
				$col[$column_no]['attrs'] = 'nowrap="nowrap"';
			}
			foreach($r as $k=>$v) {
				if((!Base_AclCommon::i_am_sa() || !Base_MaintenanceModeCommon::get_mode()) && ((array_key_exists('display',$this->columns[$k]) && $this->columns[$k]['display']==false) || !$col_pos[$k]['display'])) continue;
				$col[$col_pos[$k]['pos']]['attrs'] = '';
				if(!is_array($v)) $v = array('value'=>$v);
				if($v['value']=='')
					$col[$col_pos[$k]['pos']]['label'] = '&nbsp;';
				else
					$col[$col_pos[$k]['pos']]['label'] = $v['value'];
				$col[$col_pos[$k]['pos']]['attrs'] = isset($v['style'])? 'style="'.$v['style'].'"':'';
				if ($this->columns[$k]['wrapmode']!='cut' && isset($v['hint'])) $col[$col_pos[$k]['pos']]['attrs'] .= ' title="'.$v['hint'].'"';
				$col[$col_pos[$k]['pos']]['attrs'] .= ($this->columns[$k]['wrapmode']=='nowrap')?' nowrap':'';
				$max_width = 130*$this->columns[$k]['width']/$all_width*(7+$this->columns[$k]['fontsize']);
				if ($this->columns[$k]['wrapmode']=='cut'){
					if (strlen($col[$col_pos[$k]['pos']]['label'])>$max_width){
						if (is_array($v) && isset($v['hint'])) $col[$col_pos[$k]['pos']]['attrs'] .= ' title="'.$col[$col_pos[$k]['pos']]['label'].': '.$v['hint'].'"';
						else $col[$col_pos[$k]['pos']]['attrs'] .= ' title="'.$col[$col_pos[$k]['pos']]['label'].'"';
						$col[$col_pos[$k]['pos']]['label'] = substr($col[$col_pos[$k]['pos']]['label'],0,$max_width-3).'...';
					} elseif (is_array($v) && isset($v['hint'])) $col[$col_pos[$k]['pos']]['attrs'] .= ' title="'.$v['hint'].'"';
					$col[$col_pos[$k]['pos']]['attrs'] .= ' nowrap';
				}
			}
			ksort($col);
			foreach($col as $v)
				$out_data[] = array('label'=>$v['label'],'attrs'=>$v['attrs']);
		}
		$theme->assign('data', $out_data);
		$theme->assign('cols', $out_headers);
		
		$theme->assign('summary', $this->summary());
		$theme->assign('first', $this->gb_first());
		$theme->assign('prev', $this->gb_prev());
		$theme->assign('next', $this->gb_next());
		$theme->assign('last', $this->gb_last());

		if ($search_on) $theme->assign('adv_search','<a '.$this->create_unique_href(array('adv_search'=>!$this->is_adv_search_on())).'>'.($this->is_adv_search_on()?$this->lang->t('Simple Search'):$this->lang->t('Advanced Search')).'</a>');
		else $theme->assign('adv_search','');
		
		if (Base_User_SettingsCommon::get_user_settings('Utils/GenericBrowser','adv_history')){
			$theme->assign('reset', '<a '.$this->create_unique_href(array('action'=>'reset_order')).'>'.$this->lang->t('Reset Order').'</a>');
			$theme->assign('order',$this->get_module_variable('order_history_display'));
		} else {
			$theme->assign('reset','');
			$theme->assign('order','');
		}
		$theme->display($template);
	}
	
	private function summary() {
		if($this->rows_qty!=0)
			return $this->lang->t('Records %d to %d of %d',$this->get_module_variable('offset')+1,($this->get_module_variable('offset')+$this->get_module_variable('per_page')>$this->rows_qty)?$this->rows_qty:$this->get_module_variable('offset')+$this->get_module_variable('per_page'),$this->rows_qty);
		else 
		if (!isset($this->rows_qty))
			return $this->lang->t('No records found');
		else 
			return '';
	}
	
	private function gb_first() {
		if($this->get_module_variable('offset')>0)
			return '<a '.$this->create_unique_href(array('first'=>1)).'>'.$this->lang->t('First').'</a>';
	} 
	
	private function gb_prev() {
		if($this->get_module_variable('offset')>0)
    		return '</a><a '.$this->create_unique_href(array('prev'=>1)).'>'.$this->lang->t('Prev').'</a>';
	}
	
	private function gb_next() {
		if($this->get_module_variable('offset')+$this->get_module_variable('per_page')<$this->rows_qty) 
      		return '<a '.$this->create_unique_href(array('next'=>1)).'>'.$this->lang->t('Next').'</a>';
	}
	
	private function gb_last() {
		if($this->get_module_variable('offset')+$this->get_module_variable('per_page')<$this->rows_qty) 
      		return '<a '.$this->create_unique_href(array('last'=>1)).'>'.$this->lang->t('Last').'</a>';
	}

}

?>