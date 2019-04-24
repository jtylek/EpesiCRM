<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>, Kuba Slawinski <kslawinski@telaxus.com> and Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-utils
 * @subpackage generic-browser
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_GenericBrowser extends Module {
	private $columns = array();
	private $rows = array();
	private $rows_jses = array();
	private $rows_qty;
	private $actions = array();
	private $row_attrs = array();
	private $en_actions = false;
	private $per_page;
	private $forced_per_page = false;
	private $offset;
	private $custom_label = '';
	private $custom_label_args = '';
	private $table_prefix = '';
	private $table_postfix = '';
	private $absolute_width = false;
	private $no_actions = array();
    private $expandable = false;
	public $form_s = null;
	private $resizable_columns = true;
	private $fixed_columns_selector = '.Utils_GenericBrowser__actions';
	private $columns_width_id = null;

	public function construct() {
		$this->form_s = $this->init_module(Libs_QuickForm::module_name());
		if (is_numeric($this->get_instance_id()))
			trigger_error('GenericBrowser did not receive string name for instance in module '.$this->get_parent_type().'.<br>Use $this->init_module(\'Utils/GenericBrowser\',<construct args>, \'instance name here\');',E_USER_ERROR);
	}

	//region Settings
	public function no_action($num) {
		$this->no_actions[$num] = true;
	}

	public function set_custom_label($arg, $args=''){
		$this->custom_label = $arg;
		$this->custom_label_args = $args;
	}
	
	public function set_resizable_columns($arg = true){
		$this->resizable_columns = $arg;
	}
	
	public function set_fixed_columns_class($classes = array()){
		if (!is_array($classes)) {
			$classes = array($classes);
		}
		
		$classes[] = 'Utils_GenericBrowser__actions';
	
		$classes = array_map(function($c){return (substr($c,0,1)=='.')? $c: '.'.$c;}, $classes);	
		$this->fixed_columns_selector = implode(',', $classes);
	}

	public function absolute_width($arg){
		$this->absolute_width = $arg;
	}

	/**
	 * Sets table columns according to given definition.
	 *
	 * Argument should be an array, each array field represents one column.
	 * A column is defined using an array. The following fields may be used:
	 * name - column label
	 * width - width of the column (percentage of the whole table)
	 * search - sql column by which search should be performed
	 * order - sql column by which order should be deterined
	 * quickjump - sql column by which quickjump should be navigated
	 * wrapmode - what wrap method should be used (nowrap, wrap, cut)
	 *
	 * @param array $arg columns definiton
	 */
	public function set_table_columns(array $arg){
		$col_names = array();
		foreach($arg as $v) {
			if (!is_array($v))
				$v = array('name' => $v);
			
			$this->columns[] = $v;
			
			$col_names[] = isset($v['name'])? $v['name']: null;
		}
		$this->columns_width_id = md5(serialize($col_names));
	}

	/**
	 * Sets default order for the table.
	 * This function can be called multiple times
	 * and only at the first call or if reset argument if set
	 * it will manipulate current order.
	 *
	 * The default order should be provided as an array
	 * containing column names (names given with set_table_columns, not SQL column names).
	 *
	 * @param array array with column names
	 * @param bool true to force order reset
	 */
	public function set_default_order(array $arg,$reset=false){
		if (($this->isset_module_variable('first_display') && !$reset) || empty($arg)) return;
		$order=array();

		if(!$this->columns)
			trigger_error('columns array empty, please call set_table_columns',E_USER_ERROR);

		foreach($arg as $k=>$v){
            if ($k[0] == ':') {
                $order[] = array('column' => $k, 'direction' => $v, 'order' => $k);
                continue;
            }
			$ord = false;
			foreach($this->columns as $val)
				if ($val['name'] == $k && isset($val['order'])) {
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

    public function set_column_display($name_or_numeric, $display)
    {
        $numeric = is_numeric($name_or_numeric);
        foreach ($this->columns as $k => $column) {
            if ($numeric) {
                if ($k == $name_or_numeric) {
                    $this->columns[$k]['display'] = $display;
                }
			} else {
                if ($column['name'] == $name_or_numeric) {
                    $this->columns[$k]['display'] = $display;
                }
            }
        }
    }

	public function set_expandable($b) {
		if (Base_User_SettingsCommon::get($this->get_type(), 'disable_expandable'))
			return;
		$this->set_module_variable('expandable',$this->expandable = ($b ? true : false));
	}

	public function set_per_page($pp) {
		if (!isset(Utils_GenericBrowserCommon::$possible_vals_for_per_page[$pp])) $pp = 5;
		$this->set_module_variable('per_page',$this->per_page = $pp);
	}
	//endregion

	//region Add data
	/**
	 * Creates new row object.
	 * You can then use methods add_data, add_data_array or add_action
	 * to manipulate and extend the row.
	 *
	 * @return object Generic Browser row object
	 */
	public function get_new_row() {
		return new Utils_GenericBrowser_RowObject($this,count($this->rows));
	}

	//region Internal

	/**
	 * For internal use only.
	 */
	public function __add_row_data($num,array $arg) {
		if(!$this->columns)
			trigger_error('columns array empty, please call set_table_columns',E_USER_ERROR);

		if (count($arg) != count($this->columns))
			trigger_error('Invalid size of array for argument 2 while adding data, was '.count($arg).', should be '.count($this->columns).'. Aborting.<br>Given '.print_r($arg, true).' to table '.print_r($this->columns, true),E_USER_ERROR);

		$this->rows[$num] = $arg;
	}

	/**
	 * For internal use only.
	 */
	public function __add_row_action($num,$tag_attrs,$label,$tooltip,$icon,$order=0,$off=false,$size=1) {
		if (!isset($icon)) $icon = strtolower(trim($label));
		switch ($icon) {
			case 'view': $order = $order?: -3; break;
			case 'edit': $order = $order?: -2; break;
			case 'delete': $order = $order?: -1; break;
			case 'info': $order = $order?: 1000; break;
		}
		$this->actions[$num][$icon] = array('tag_attrs'=>$tag_attrs,'label'=>$label,'tooltip'=>$tooltip, 'off'=>$off, 'size'=>$size, 'order'=>$order);
		$this->en_actions = true;
	}

	/**
	 * For internal use only.
	 */
	public function __set_row_attrs($num,$tag_attrs) {
		$this->row_attrs[$num] = $tag_attrs;
	}

	/**
	 * For internal use only.
	 */
	public function __add_row_js($num,$js) {
		if(!isset($this->rows_jses[$num])) $this->rows_jses[$num]='';
		$this->rows_jses[$num] .= rtrim($js,';').';';
	}

	//endregion

	/**
	 * Adds new row with data to Generic Browser.
	 *
	 * Each argument fills one field,
	 * it can be either a string or an array.
	 *
	 * If an array is passed it may consists following fields:
	 * value - text that will be displayed in the field
	 * style - additional css style definition
	 * hint - tooltip for the field
	 * wrapmode - what wrap method should be used (nowrap, wrap, cut)
	 *
	 * If a string is passed it will be displayed in the field.
	 *
	 * It's not recommended to use this function in conjunction with add_new_row().
	 *
	 * @param mixed $args list of arguments
	 */
	public function add_row($args) {
		$args = func_get_args();
		$this->add_row_array($args);
	}

	/**
	 * Adds new row with data to Generic Browser.
	 *
	 * The argument should be an array,
	 * each array entry fills one field,
	 * it can be either a string or an array.
	 *
	 * If an array is passed it may consists following fields:
	 * value - text that will be displayed in the field
	 * style - additional css style definition
	 * hint - tooltip for the field
	 *
	 * If a string is passed it will be displayed in the field.
	 *
	 * It's not recommended to use this function in conjunction with add_new_row().
	 *
	 * @param $arg array array with row data
	 */
	public function add_row_array(array $arg) {
		if(!$this->columns)
			trigger_error('columns array empty, please call set_table_columns',E_USER_ERROR);

		if (count($arg) != count($this->columns))
			trigger_error('Invalid size of array for argument 2 while adding data, was '.count($arg).', should be '.count($this->columns).'. Aborting.<br>',E_USER_ERROR);

		$this->rows[] = $arg;

		if ($this->per_page && count($this->rows) > $this->per_page)
			trigger_error('Added more rows than expected, aborting.',E_USER_ERROR);

	}

	//endregion

	/**
	 * Returns values needed for proper selection of elements.
	 * This is only neccessary if you are using 'paged' version of Genric Browser.
	 * Returned values should be used together with DB::SelectLimit();
	 *
	 * @return array array containing two fields: 'numrows' and 'offset'
	 */
	public function get_limit($max) {
		$offset = $this->get_module_variable('offset',0);
		$per_page = $this->get_module_variable('per_page',Base_User_SettingsCommon::get(Utils_GenericBrowser::module_name(),'per_page'));
		if (!isset(Utils_GenericBrowserCommon::$possible_vals_for_per_page[$per_page])) {
			$per_page = 5;
			$this->get_module_variable('per_page',Base_User_SettingsCommon::save(Utils_GenericBrowser::module_name(),'per_page', 5));
		}
		$this->rows_qty = $max;
		if ($offset>=$max) $offset = 0;
        if($offset % $per_page != 0) $offset = floor($offset/$per_page)*$per_page;

		if($this->get_unique_href_variable('next')=='1')
			$offset += $per_page;
		elseif($this->get_unique_href_variable('prev')=='1') {
			$offset -= $per_page;
			if ($offset<0) $offset=0;
		}
		elseif($this->get_unique_href_variable('first')=='1')
			$offset = 0;
		elseif($this->get_unique_href_variable('last')=='1')
			$offset = floor(($this->rows_qty-1)/$per_page)*$per_page;

		$this->unset_unique_href_variable('next');
		$this->unset_unique_href_variable('prev');
		$this->unset_unique_href_variable('first');
		$this->unset_unique_href_variable('last');
		$this->set_module_variable('offset', $offset);
		$this->set_module_variable('per_page', $per_page);
		$this->per_page = $per_page;
		$this->offset = $offset;
		return array(	'numrows'=>$per_page,
						'offset'=>$offset);
	}

	/**
	 * Returns 'ORDER BY' part of an SQL query
	 * which will sort rows in order chosen by end-user.
	 * Default value returned is determined by arguments passed to set_default_order().
	 * Returned string contains space at the beginning.
	 *
	 * Do not use this method in conjuntion with get_order()
	 *
	 * @param string columns to force order
	 * @return string 'ORDER BY' part of the query
	 */
	public function get_query_order($force_order=null) {
		$ch_order = $this->get_unique_href_variable('change_order');
		if ($ch_order)
			$this->change_order($ch_order);
		$order = $this->get_module_variable('order');
		if(!is_array($order)) return '';
		ksort($order);
		$sql = '';
		$ohd = '';
		$first = true;
		foreach($order as & $v){
			$ohd .= ($first?'':',').' '.$v['column'].' '.$v['direction'];
			$sql .= ($first?'':',').' '.$v['order'].' '.$v['direction'];
			$first = false;
		}
		if ($sql) $sql = ' ORDER BY'.($force_order?' '.trim($force_order,',').',':'').$sql;
		$this->set_module_variable('order_history_display',$ohd);
		$this->set_module_variable('order',$order);
		return $sql;
	}

	/**
	 * Returns an array containing information about row order.
	 * Each field represents a column by which the order is determined.
	 * First field is used as the final order criteria,
	 * while the last field is used for the initial sort.
	 *
	 * Each field contains:
	 * column - Generic Browser column name
	 * order - SQL column name
	 * direction - ASC or DESC
	 *
	 * Default value returned is determined by arguments passed to set_default_order().
	 *
	 * Do not use this method in conjuntion with get_query_order()
	 *
	 * @return array array containing information about row order
	 */
	public function get_order(){
		$this->get_query_order();
		$order = $this->get_module_variable('order');
		return $order;
	}

	/**
	 * For internal use only.
	 */
	public function change_order($ch_order){
		$order = $this->get_module_variable('order', array());

		if(!$this->columns)
			trigger_error('columns array empty, please call set_table_columns',E_USER_ERROR);

		$ord = null;
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

	/**
	 * Returns statement that should be used in 'WHERE' caluse
	 * to select elements that were searched for.
	 *
	 * The statement generated using search criteria is enclosed with parenthesis
	 * and does not include keyword 'WHERE'.
	 *
	 * If no conditions where spcified returns empty string.
	 *
	 * @return string part of sql statement
	 */
	public function get_search_query( $array = false, $separate=false){
		$search = $this->get_module_variable('search');

		$this->get_module_variable_or_unique_href_variable('quickjump_to');
		$quickjump = $this->get_module_variable('quickjump');
		$quickjump_to = $this->get_module_variable('quickjump_to');
		$this->set_module_variable('quickjump_to',$quickjump_to);

		if (!$array) {
			$where = '';
		} else {
			$where = array();
		}
		
		if(!$this->columns)
			trigger_error('columns array empty, please call set_table_columns',E_USER_ERROR);

		if(!$this->is_adv_search_on()) {
			if(isset($search['__keyword__'])) {
				if(!$array) {
					if($separate)
						$search = explode(' ',$search['__keyword__']);
					else
						$search = array($search['__keyword__']);
				}
				foreach($this->columns as $k=>$v){
					if (isset($v['search']))
		 				if (!$array) {
		 					$t_where = '';
		 					foreach($search as $s) {
								$t_where .= ($t_where?' AND':'').' '.$v['search'].' '.DB::like().' '.DB::Concat(DB::qstr('%'),sprintf('%s',DB::qstr($s)),DB::qstr('%'));
							}
							$where .= ($where?' OR':'').' ('.$t_where.')';
						} else
							$where[(empty($where)?'(':'|').$v['search']][] = sprintf('%s',$search['__keyword__']);
				}
			}
		} else {
			foreach($this->columns as $k=>$v)
				if (isset($v['search']) && isset($search[$v['search']])) {
		 			if (!$array)
						$where .= ($where?' AND':'').' '.$v['search'].' '.DB::like().' '.DB::Concat(DB::qstr('%'),sprintf('%s',DB::qstr($search[$v['search']])),DB::qstr('%'));
					else
						$where[$v['search']][] = $search[$v['search']];
				}
		}
 		if (isset($quickjump) && $quickjump_to!='') {
 			if ($quickjump_to=='0') {
	 			if (!$array) {
					$where = ($where?'('.$where.') AND':'').' (false';
					foreach(range(0,9) as $v)
						$where .= 	' OR '
									.$quickjump.' '.DB::like().' '.DB::Concat(sprintf('%s',DB::qstr($v)),'\'%\'');
					$where .= 	')';
					if ($where) $where = ' ('.$where.')';
	 			} else {
					$where[$quickjump] = array();
					foreach(range(0,9) as $v)
						$where[$quickjump][] = DB::qstr($v.'%');
	 			}
 			} else {
	 			if (!$array) {
					$where = ($where?'('.$where.') AND':'').' ('
								.$quickjump.' '.DB::like().' '.DB::Concat(sprintf('%s',DB::qstr($quickjump_to)),'\'%\'')
								.' OR '
								.$quickjump.' '.DB::like().' '.DB::Concat(sprintf('%s',DB::qstr(strtolower($quickjump_to))),'\'%\'').
								')';
					if ($where) $where = ' ('.$where.')';
	 			} else {
					$where[$quickjump] = array(DB::Concat(DB::qstr($quickjump_to),DB::qstr('%')),DB::Concat(DB::qstr(strtolower($quickjump_to)),DB::qstr('%')));
	 			}
 			}
		}
		return $where;
	}

	/**
	 * For internal use only.
	 */
	public function is_adv_search_on(){
		return $this->get_module_variable('adv_search',Base_User_SettingsCommon::get('Utils_GenericBrowser','adv_search'));
	}

	private function check_if_row_fits_array($row,$adv){
		$search = $this->get_module_variable('search');
		$this->get_module_variable_or_unique_href_variable('quickjump_to');
		$quickjump = $this->get_module_variable('quickjump');
		$quickjump_to = $this->get_module_variable('quickjump_to');
		$this->set_module_variable('quickjump_to',$quickjump_to);

		if(!$this->columns)
			trigger_error('columns array empty, please call set_table_columns',E_USER_ERROR);

 		if (isset($quickjump) && $quickjump_to!='') {
			foreach($this->columns as $k=>$v){
				if (isset($v['quickjump'])){
					$r = strip_tags($row[$k]);
	 				if (!isset($r[0]) ||
	 					($quickjump_to != $r[0] &&
	 					strtolower($quickjump_to) != $r[0]))
	 					return false;
				}
			}
 		}
		if (!$adv){
			if (!isset($search['__keyword__']) || $search['__keyword__']=='') return true;
			$ret = true;
			foreach($this->columns as $k=>$v){
				if (isset($v['search']) && isset($search['__keyword__'])) {
					$ret = false;
					if (is_array($row[$k])) $row[$k] = $row[$k]['value'];
					if (stripos(strip_tags($row[$k]),$search['__keyword__'])!==false) return true;
				}
			}
			return $ret;
		} else {
			foreach($this->columns as $k=>$v){
				if (isset($v['search']) && isset($search[$v['search']]) && stripos(strip_tags(is_array($row[$k])?$row[$k]['value']:$row[$k]),$search[$v['search']])===false) return false;
			}
			return true;
		}
	}

	private function sort_data(& $data, & $js=null, & $actions=null, & $row_attrs=null){
		if(!$this->columns) trigger_error('columns array empty, please call set_table_columns',E_USER_ERROR);
		if(($order = $this->get_order()) && $order=$order[0]) {
			$col = array();
			foreach($data as $j=>$d)
				foreach($d as $i=>$c)
					if(isset($this->columns[$i]['order']) && $this->columns[$i]['order']==$order['order']) {
						if(is_array($c)) {
							if(isset($c['order_value']))
								$xxx = $c['order_value'];
							else
								$xxx = $c['value'];
						} else $xxx = $c;
						if(isset($this->columns[$i]['order_preg'])) {
							$ret = array();
							preg_match($this->columns[$i]['order_preg'],$xxx, $ret);
							$xxx = isset($ret[1])?$ret[1]:'';
						}
						$xxx = strip_tags(strtolower($xxx));
						$col[$j] = $xxx;
					}

			asort($col);
			$data2 = array();
			$js2 = array();
			$actions2 = array();
			$row_attrs2 = array();
			foreach($col as $j=>$v) {
				$data2[] = $data[$j];
				if (isset($js)) $js2[] = $js[$j];
				if (isset($actions)) $actions2[] = $actions[$j];
				if (isset($row_attrs)) $row_attrs2[] = $row_attrs[$j];
			}
			if($order['direction']!='ASC') {
				$data2 = array_reverse($data2);
				$js2 = array_reverse($js2);
				$actions2 = array_reverse($actions2);
				$row_attrs2 = array_reverse($row_attrs2);
			}
			$data = $data2;
			$js = $js2;
			$actions = $actions2;
			$row_attrs = $row_attrs2;
		}
	}
	/**
	 * For internal use only.
	 */
	public function simple_table($header, $data, $page_split = true, $template=null, $order=true) {
		$len = count($header);
		foreach($header as $i=>$h) {
			if(is_string($h)) $header[$i]=array('name'=>$h);
			if($order) {
				$header[$i]['order']="$i";
			} else
				unset($header[$i]['order']);
		}
		$this->set_table_columns($header);

		if($order) {
			if(is_array($order)) $this->set_default_order($order);
			$this->sort_data($data);
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

	/**
	 * Displays the table performing paging and searching automatically.
	 *
	 * @param bool enabling paging, true by default
	 */
	public function automatic_display($paging=true){
		if(!$this->columns)
			trigger_error('columns array empty, please call set_table_columns',E_USER_ERROR);

		$rows = array();
		$js = array();
		$actions = array();
		$row_attrs = array();
		foreach($this->columns as $k=>$v)
			if (isset($v['search'])) $this->columns[$k]['search'] = $k;

		foreach($this->rows as $k=>$v){
			if ($this->check_if_row_fits_array($v,$this->is_adv_search_on())) {
				$rows[] = $v;
				$js[] = isset($this->rows_jses[$k])?$this->rows_jses[$k]:'';
				$actions[] = isset($this->actions[$k])?$this->actions[$k]:array();
				$row_attrs[] = isset($this->row_attrs[$k])?$this->row_attrs[$k]:'';
			}
		}
		$this->sort_data($rows, $js, $actions, $row_attrs);

		$this->rows = array();
		$this->rows_jses = array();
		$this->actions = array();
		$this->row_attrs = array();
		if ($paging) $limit = $this->get_limit(count($rows));
		$id = 0;
		foreach($rows as $k=>$v) {
			if (!$paging || ($id>=$limit['offset'] && $id<$limit['offset']+$limit['numrows'])){
				$this->rows[] = $v;
				$this->rows_jses[] = $js[$k];
				$this->actions[] = $actions[$k];
				$this->row_attrs[] = $row_attrs[$k];
			}
			$id++;
		}
		$this->body();
	}

	/**
	 * Executes SQL query that selects elements needed for the current page
	 * and performs sort.
	 *
	 * @param string SQL query that selects all elements for the table
	 * @param string SQL query that will return number of rows in the table
	 */
	public function query_order_limit($query,$query_numrows) {
		$query_order = $this->get_query_order();
		$qty = DB::GetOne($query_numrows);
		$query_limits = $this->get_limit($qty);
		return DB::SelectLimit($query.$query_order,$query_limits['numrows'],$query_limits['offset']);
	}

  	//internal use
  	public function sort_actions($a,$b) {
		return $a['order']-$b['order'];
	}

	public function force_per_page($i) {
		if(!is_numeric($i))
			trigger_error('Invalid argument passed to force_per_page method.',E_USER_ERROR);

		$this->set_module_variable('per_page',$i);
		$this->forced_per_page = true;
	}

	//region Display
	/**
	 * Displays the table.
	 *
	 * @param string template file that should be used to display the table, use Base_ThemeCommon::get_template_filename() for proper filename
	 * @param bool enabling paging, true by default
	 */
	public function body($template=null,$paging=true){
		if(!$this->columns)
			trigger_error('columns array empty, please call set_table_columns',E_USER_ERROR);

		if ($this->isset_unique_href_variable('action')
			&& $this->get_unique_href_variable('action') == 'reset_order') {
			$this->set_module_variable('order',$this->get_module_variable('default_order'));
			location(array());
			return;
		}
		$md5_id = md5($this->get_path());
		$this->set_module_variable('first_display','done');
		$theme = $this->init_module(Base_Theme::module_name());
		$per_page = $this->get_module_variable('per_page');
		$order = $this->get_module_variable('order');
        $this->expandable = $this->get_module_variable('expandable',$this->expandable);
        $expand_action_only = false;
        if ($this->expandable) {
            if(!$this->en_actions) {
                $expand_action_only = true;
                $this->en_actions = true;
            }
        }
		if ($this->en_actions) $actions_position = Base_User_SettingsCommon::get(Utils_GenericBrowser::module_name(),'actions_position');

		$ch_adv_search = $this->get_unique_href_variable('adv_search');
		if (isset($ch_adv_search)) {
			$this->set_module_variable('adv_search',$ch_adv_search);
			$this->set_module_variable('search',array());
			location(array());
		}

		$search = $this->get_module_variable('search');

		$renderer = new HTML_QuickForm_Renderer_TCMSArraySmarty();
		$form_p = $this->init_module(Libs_QuickForm::module_name());
		$pager_on = false;
		if(isset($this->rows_qty) && $paging) {
			if(!$this->forced_per_page) {
				$form_p->addElement('select','per_page',__('Number of rows per page'), Utils_GenericBrowserCommon::$possible_vals_for_per_page, 'onChange="'.$form_p->get_submit_form_js(false).'"');
				$form_p->setDefaults(array('per_page'=>$per_page));
			}
			$qty_pages = ceil($this->rows_qty/$this->per_page);
			if ($qty_pages<=25) {
					$pages = array();
				if($this->rows_qty==0)
					$pages[0] = 1;
				else
					foreach (range(1, $qty_pages) as $v) $pages[$v] = $v;
				$form_p->addElement('select','page',__('Page'), $pages, 'onChange="'.$form_p->get_submit_form_js(false).'"');
				$form_p->setDefaults(array('page'=>(int)(ceil($this->offset/$this->per_page)+1)));
			} else {
				$form_p->addElement('text','page',__('Page (%s to %s)', array(1,$qty_pages)), array('onclick'=>'this.focus();this.select();', 'onChange'=>$form_p->get_submit_form_js(false), 'style'=>'width:'.(7*strlen($qty_pages)).'px;'));
				$form_p->setDefaults(array('page'=>(int)(ceil($this->offset/$this->per_page)+1)));
			}
			$pager_on = true;
		}
		$search_on=false;
		if(!$this->is_adv_search_on()) {
			foreach($this->columns as $k=>$v)
				if (isset($v['search'])) {
					$this->form_s->addElement('text','search',__('Keyword'), array('id'=>'gb_search_field', 'placeholder'=>__('search keyword...'), 'x-webkit-speech'=>'x-webkit-speech', 'lang'=>Base_LangCommon::get_lang_code(), 'onwebkitspeechchange'=>$this->form_s->get_submit_form_js()));
					eval_js('jq("#gb_search_field").focus()');
					$this->form_s->setDefaults(array('search'=>isset($search['__keyword__'])?$search['__keyword__']:''));
					$search_on=true;
					break;
				}
		} else {
			$search_fields = array();
			$search_fields_hidden = '';
			if ($this->en_actions && $actions_position==0) $mov = 1;
			else $mov=0;
			foreach($this->columns as $k=>$v) {
				if(isset($v['display']) && !$v['display']) {
					$mov--;
					continue;
				}
				if (isset($v['search'])) {
					$type = isset($v['search_type']) ? $v['search_type'] : 'text';
					// quickform element to perform proper export
					$this->form_s->addElement($type, 'search__' . $v['search'], '');
					// hidden element to pass data during submit
					$default = isset($search[$v['search']]) ? $search[$v['search']] : '';
					$search_fields_hidden .= '<input type="hidden" name="search__' . $v['search'] . '" value="' . $default . '">';
					$this->form_s->setDefaults(array('search__' . $v['search'] => $default));
					// outside form input element to update input hidden with value
					$in_el = $this->form_s->createElement($type, 'search__' . $v['search'], '', ' style="width:100%" value="'.$default.'" x-webkit-speech="x-webkit-speech" lang="'.Base_LangCommon::get_lang_code().'" placeholder="'.__('search keyword...').'" onchange="document.forms[\''.$this->form_s->getAttribute('name').'\'].search__'.$v['search'].'.value = this.value;" onkeydown="if (event.keyCode==13) {document.forms[\''.$this->form_s->getAttribute('name').'\'].search__'.$v['search'].'.value = this.value;'.$this->form_s->get_submit_form_js().';}"');
					$search_fields[$k+$mov] = $in_el->toHtml();
					$search_on=true;
				}
			}
			$theme->assign('search_fields', $search_fields);
			$theme->assign('search_fields_hidden', $search_fields_hidden);
		}
		if ($search_on) {
			$this->form_s->addElement('submit','submit_search',__('Search'), array('id'=>'gb_search_button'));
			if (Base_User_SettingsCommon::get($this->get_type(), 'show_all_button')) {
				$el = $this->form_s->addElement('hidden','show_all_pressed');
				$this->form_s->addElement('button','show_all',__('Show all'), array('onclick'=>'document.forms["'.$this->form_s->getAttribute('name').'"].show_all_pressed.value="1";'.$this->form_s->get_submit_form_js()));
				$el->setValue('0');
			}
		}
		if ($pager_on) {
			$form_p->accept($renderer);
			$form_array = $renderer->toArray();
			$theme->assign('form_data_paging', $form_array);
			$theme->assign('form_name_paging', $form_p->getAttribute('name'));

			// form processing
			if($form_p->validate()) {
				$values = $form_p->exportValues();
				if(isset($values['per_page'])) {
					$this->set_module_variable('per_page',$values['per_page']);
					Base_User_SettingsCommon::save(Utils_GenericBrowser::module_name(),'per_page',$values['per_page']);
				}
				if(isset($values['page']) && is_numeric($values['page']) && ($values['page']>=1 && $values['page']<=$qty_pages)) {
					$this->set_module_variable('offset',($values['page']-1)*$this->per_page);
				}
				location(array());
				return;
			}
		}
		if ($search_on) {
			$this->form_s->accept($renderer);
			$form_array = $renderer->toArray();
			$theme->assign('form_data_search', $form_array);
			$theme->assign('form_name_search', $this->form_s->getAttribute('name'));

			// form processing
			if($this->form_s->validate()) {
				$values = $this->form_s->exportValues();
				if (isset($values['show_all_pressed']) && $values['show_all_pressed']) {
					$this->set_module_variable('search',array());
					$this->set_module_variable('show_all_triggered',true);
					location(array());
					return;
				}
				$search = array();
				foreach ($values as $k=>$v){
					if ($k=='search') {
						if ($v!=__('search keyword...') && $v!='')
							$search['__keyword__'] = $v;
						break;
					}
					if (substr($k,0,8)=='search__') {
						$val = substr($k,8);
						if ($v!=__('search keyword...') && $v!='') $search[$val] = $v;
					}
				}
				$this->set_module_variable('search',$search);
				location(array());
				return;
			}
		}

		$headers = array();
		if ($this->en_actions) {
			$max_actions = 0; // Possibly improve it to calculate it during adding actions
			foreach($this->actions as $i=>$v) {
				$this_width = 0;
				foreach ($v as $vv) {
					$this_width += $vv['size'];
				}
				if ($this_width>$max_actions) $max_actions = $this_width;
			}
			if ($actions_position==0) $headers[-1] = array('label'=>'<span>'.'&nbsp;'.'</span>','attrs'=>'style="width: '.($max_actions*16+6).'px;" class="Utils_GenericBrowser__actions"');
			else $headers[count($this->columns)] = array('label'=>'<span>'.'&nbsp;'.'</span>','attrs'=>'style="width: '.($max_actions*16+6).'px;" class="Utils_GenericBrowser__actions"');
		}

		$all_width = 0;
		foreach($this->columns as $k=>$v) {
			if (!isset($this->columns[$k]['width'])) $this->columns[$k]['width'] = 100;
			if (!is_numeric($this->columns[$k]['width'])) continue;
			$all_width += $this->columns[$k]['width'];
			if (isset($v['quickjump'])) {
				$quickjump = $this->set_module_variable('quickjump',$v['quickjump']);
				$quickjump_col = $k;
			}
		}
		$i = 0;
		$is_order = false;
		$adv_history = Base_User_SettingsCommon::get(Utils_GenericBrowser::module_name(),'adv_history');
		foreach($this->columns as $v) {
			if (array_key_exists('display', $v) && $v['display']==false) {
				$i++;
				continue;
			}
			if(isset($v['order'])) $is_order = true;
			if(!isset($headers[$i])) $headers[$i] = array('label'=>'');
			if ($v['name'] && $v['name']==$order[0]['column']) $label = '<span style="padding-right: 12px; margin-right: 12px; background-image: url('.Base_ThemeCommon::get_template_file('Utils_GenericBrowser','sort-'.strtolower($order[0]['direction']).'ending.png').'); background-repeat: no-repeat; background-position: right;">'.$v['name'].'</span>';
			else $label = $v['name'];
			$headers[$i]['label'] .= (isset($v['preppend'])?$v['preppend']:'').(isset($v['order'])?'<a '.$this->create_unique_href(array('change_order'=>$v['name'])).'>' . $label . '</a>':$label).(isset($v['append'])?$v['append']:'');
			//if ($v['search']) $headers[$i] .= $form_array['search__'.$v['search']]['label'].$form_array['search__'.$v['search']]['html'];
            if ($this->absolute_width) {
                 $headers[$i]['attrs'] = 'width="'.$v['width'].'" ';
            } elseif (!is_numeric($v['width'])) {
                $headers[$i]['attrs'] = 'style="width:'.$v['width'].'" ';
            } else {
                $headers[$i]['attrs'] = 'width="'.intval(100*$v['width']/$all_width).'%" ';
            }
			$headers[$i]['attrs'] .= 'nowrap="1" ';
			if (isset($v['attrs'])) $headers[$i]['attrs'] .= $v['attrs'].' ';
			$i++;
		}
		ksort($headers);
		$out_headers = array_values($headers);
		unset($headers);

		$out_data = array();

        if($this->expandable) {
            eval_js_once('gb_expandable["'.$md5_id.'"] = {};');
            eval_js_once('gb_expanded["'.$md5_id.'"] = 0;');

            eval_js_once('gb_expand_icon = "'.Base_ThemeCommon::get_template_file(Utils_GenericBrowser::module_name(), 'expand.gif').'";');
            eval_js_once('gb_collapse_icon = "'.Base_ThemeCommon::get_template_file(Utils_GenericBrowser::module_name(), 'collapse.gif').'";');
            eval_js_once('gb_expand_icon_off = "'.Base_ThemeCommon::get_template_file(Utils_GenericBrowser::module_name(), 'expand_gray.gif').'";');
            eval_js_once('gb_collapse_icon_off = "'.Base_ThemeCommon::get_template_file(Utils_GenericBrowser::module_name(), 'collapse_gray.gif').'";');
        }

		foreach($this->rows as $i=>$r) {
			$col = array();

            if($this->expandable) {
                $row_id =  $md5_id.'_'.$i;
                $this->__add_row_action($i,'style="display:none;" href="javascript:void(0)" onClick="gb_expand(\''.$md5_id.'\',\''.$i.'\')" id="gb_more_'.$row_id.'"','Expand', null, Base_ThemeCommon::get_template_file(Utils_GenericBrowser::module_name(), 'plus_gray.png'), 1001);
                $this->__add_row_action($i,'style="display:none;" href="javascript:void(0)" onClick="gb_collapse(\''.$md5_id.'\',\''.$i.'\')" id="gb_less_'.$row_id.'"','Collapse', null, Base_ThemeCommon::get_template_file(Utils_GenericBrowser::module_name(), 'minus_gray.png'), 1001, false, 0);
                $this->__add_row_js($i,'gb_expandable_init("'.Epesi::escapeJS($md5_id,true,false).'","'.Epesi::escapeJS($i,true,false).'")');
                if(!isset($this->row_attrs[$i])) $this->row_attrs[$i]='';
                $this->row_attrs[$i] .= 'id="gb_row_'.$row_id.'"';
            }

            if ($this->en_actions) {
				if ($actions_position==0) $column_no = -1;
				else $column_no = count($this->columns);
				$col[$column_no]['attrs'] = '';
				if (!empty($this->actions[$i])) {
					uasort($this->actions[$i], array($this,'sort_actions'));
					$actions = '';
					foreach($this->actions[$i] as $icon=>$arr) {
						$actions .= '<a '.Utils_TooltipCommon::open_tag_attrs($arr['tooltip']!==null?$arr['tooltip']:$arr['label'], $arr['tooltip']===null).' '.$arr['tag_attrs'].'>';
					    if ($icon=='view' || $icon=='delete' || $icon=='edit' || $icon=='info' || $icon=='restore' || $icon=='append data' || $icon=='active-on' || $icon=='active-off' || $icon=='history' || $icon=='move-down' || $icon=='move-up' || $icon=='history_inactive' || $icon=='print' || $icon == 'move-up-down') {
							$actions .= '<img class="action_button" src="'.Base_ThemeCommon::get_template_file(Utils_GenericBrowser::module_name(),$icon.($arr['off']?'-off':'').'.png').'" border="0">';
					    } elseif(file_exists($icon)) {
							$actions .= '<img class="action_button" src="'.$icon.'" border="0">';
					    } else {
							$actions .= $arr['label'];
					    }
						$actions .= '</a>';
					}
					$col[$column_no]['label'] = $actions;
                    $col[$column_no]['attrs'] .= ' class="Utils_GenericBrowser__actions Utils_GenericBrowser__td"';

					// Add overflow_box to actions
					$settings = Base_User_SettingsCommon::get('Utils_GenericBrowser', 'zoom_actions');
					if ($settings==2 || ($settings==1 && detect_iphone()))
						$col[$column_no]['attrs'] .= ' onmouseover="if(typeof(table_overflow_show)!=\'undefined\')table_overflow_show(this,true);"';
				} else {
					$col[$column_no]['label'] = '&nbsp;';
                    $col[$column_no]['attrs'] .= 'nowrap="nowrap"'.' class="Utils_GenericBrowser__td"';
				}
				if (isset($this->no_actions[$i]))
					$col[$column_no]['attrs'] .= ' style="display:none;"';
			}
			foreach($r as $k=>$v) {
				if (is_array($v) && isset($v['dummy'])) $v['style'] = 'display:none;';
				if (array_key_exists('display',$this->columns[$k]) && $this->columns[$k]['display']==false) continue;
				if (is_array($v) && isset($v['attrs'])) $col[$k]['attrs'] = $v['attrs'];
				else $col[$k]['attrs'] = '';
				if ($this->absolute_width) {
					if (is_array($v) && isset($v['dummy'])) {
						$reverse_col = array_reverse($col, true);
				
						foreach ($reverse_col as $kk=>$vv)
							if (isset($vv['width'])) {
								if (stripos($vv['attrs'], 'colspan')===false) break;
								$col[$kk]['width'] += $this->columns[$k]['width'];
								break;
							}
					}
					else $col[$k]['width'] = $this->columns[$k]['width'];
				}
				if (!is_array($v)) $v = array('value'=>$v);
				$col[$k]['label'] = $v['value'];
				if (!isset($v['overflow_box']) || $v['overflow_box']) {
					$col[$k]['attrs'] .= ' onmouseover="if(typeof(table_overflow_show)!=\'undefined\')table_overflow_show(this);"';
				} else {
					if (!isset($v['style'])) $v['style'] = '';
					$v['style'] .= 'white-space: normal;';
				}
				$col[$k]['attrs'] .= ' class="Utils_GenericBrowser__td '.(isset($v['class'])?$v['class']:'').'"';
				$col[$k]['attrs'] .= isset($v['style'])? ' style="'.$v['style'].'"':'';
				if (isset($quickjump_col) && $k==$quickjump_col) $col[$k]['attrs'] .= ' class="Utils_GenericBrowser__quickjump"';
				if ((!isset($this->columns[$k]['wrapmode']) || $this->columns[$k]['wrapmode']!='cut') && isset($v['hint'])) $col[$k]['attrs'] .= ' title="'.$v['hint'].'"';
				$col[$k]['attrs'] .= (isset($this->columns[$k]['wrapmode']) && $this->columns[$k]['wrapmode']=='nowrap')?' nowrap':'';
				if ($all_width!=0)
					$max_width = 130*(substr($this->columns[$k]['width'],-2)=="px"
							? (int)substr($this->columns[$k]['width'],0,-2)
							: (int)$this->columns[$k]['width'])/$all_width*(7+(isset($this->columns[$k]['fontsize'])
								? $this->columns[$k]['fontsize']
								: 0));
        			else
        			        $max_width = 0;
				if (isset($this->columns[$k]['wrapmode']) && $this->columns[$k]['wrapmode']=='cut'){
					if (strlen($col[$k]['label'])>$max_width){
						if (is_array($v) && isset($v['hint'])) $col[$k]['attrs'] .= ' title="'.$col[$k]['label'].': '.$v['hint'].'"';
						else $col[$k]['attrs'] .= ' title="'.$col[$k]['label'].'"';
						$col[$k]['label'] = substr($col[$k]['label'],0,$max_width-3).'...';
					} elseif (is_array($v) && isset($v['hint'])) $col[$k]['attrs'] .= ' title="'.$v['hint'].'"';
					$col[$k]['attrs'] .= ' nowrap';
				}
			}
			if ($this->absolute_width)
				foreach ($col as $k=>$v) if (isset($v['width'])) $col[$k]['attrs'] .= ' width="'.$v['width'].'"';
			
			ksort($col);
			$expanded = $this->expandable ? ' expanded' : '';
			foreach($col as $v)
				$out_data[] = array('label'=>'<div class="expandable'.$expanded.'">'.$v['label'].'</div>','attrs'=>$v['attrs']);
			if(isset($this->rows_jses[$i]))
				eval_js($this->rows_jses[$i]);
		}
		if (isset($quickjump)) {
			$quickjump_to = $this->get_module_variable('quickjump_to');
			$all = '<span class="all">'.__('All').'</span>';
			if (isset($quickjump_to) && $quickjump_to != '') $all = '<a class="all" '.$this->create_unique_href(array('quickjump_to'=>'')).'>'.__('All').'</a>';
			$letter_links = array(0 => $all);
			if ($quickjump_to != '0')
				$letter_links[] .= '<a class="all" '.$this->create_unique_href(array('quickjump_to'=>'0')).'>'.'123'.'</a>';
			else
				$letter_links[] .= '<span class="all">' . '123' . '</span>';
			$letter = 'A';
			while ($letter<='Z') {
				if ($quickjump_to != $letter)
					$letter_links[] .= '<a class="letter" '.$this->create_unique_href(array('quickjump_to'=>$letter)).'>'.$letter.'</a>';
				else
					$letter_links[] .= '<span class="letter">' . $letter . '</span>';
				$letter = chr(ord($letter)+1);
			}
			$theme->assign('letter_links', $letter_links);
			$theme->assign('quickjump_to', $quickjump_to);
		}

		$theme->assign('data', $out_data);
		$theme->assign('cols', $out_headers);

		$theme->assign('row_attrs', $this->row_attrs);

        $theme->assign('table_id','table_'.$md5_id);
        $theme->assign('cols_width_id',$this->columns_width_id);
        if($expand_action_only) {
            eval_js('gb_expandable_hide_actions("'.$md5_id.'")');
        }
		$theme->assign('table_prefix', $this->table_prefix);
		$theme->assign('table_postfix', $this->table_postfix);

		$theme->assign('summary', $this->summary());
		$theme->assign('first', $this->gb_first());
		$theme->assign('prev', $this->gb_prev());
		$theme->assign('next', $this->gb_next());
		$theme->assign('last', $this->gb_last());
		$theme->assign('custom_label', $this->custom_label);
		$theme->assign('custom_label_args', $this->custom_label_args);

        if($this->expandable) {
            $theme->assign('expand_collapse',array(
                'e_label'=>__('Expand All'),
                'e_href'=>'href="javascript:void(0);" onClick=\'gb_expand_all("'.$md5_id.'")\'',
                'e_id'=>'expand_all_button_'.$md5_id,
                'c_label'=>__('Collapse All'),
                'c_href'=>'href="javascript:void(0);" onClick=\'gb_collapse_all("'.$md5_id.'")\'',
                'c_id'=>'collapse_all_button_'.$md5_id
            ));
            $max_actions = isset($max_actions) ? $max_actions : 0;
            eval_js('gb_expandable_adjust_action_column("'.$md5_id.'", ' . $max_actions . ')');
            eval_js('gb_show_hide_buttons("'.$md5_id.'")');
        }

		if ($search_on) $theme->assign('adv_search','<a id="switch_search_'.($this->is_adv_search_on()?'simple':'advanced').'" class="button" '.$this->create_unique_href(array('adv_search'=>!$this->is_adv_search_on())).'>' . ($this->is_adv_search_on()?__('Simple Search'):__('Advanced Search')) . '&nbsp;&nbsp;&nbsp;<img src="' . Base_ThemeCommon::get_template_file($this -> get_type(), 'advanced.png') . '" width="8px" height="20px" border="0" style="vertical-align: middle;"></a>');
		else $theme->assign('adv_search','');

		if (Base_User_SettingsCommon::get(Utils_GenericBrowser::module_name(),'adv_history') && $is_order){
			$theme->assign('reset', '<a '.$this->create_unique_href(array('action'=>'reset_order')).'>'.__('Reset Order').'</a>');
			$theme->assign('order',$this->get_module_variable('order_history_display'));
		}
		$theme->assign('id',md5($this->get_path()));
		
		if ($this->resizable_columns) {
			load_js($this->get_module_dir().'js/col_resizable.js');
				
			$fixed_col_setting = !empty($this->fixed_columns_selector)? ', skipColumnClass:"'.$this->fixed_columns_selector.'"':'';
			eval_js('jq("#table_'.$md5_id.'").colResizable({liveDrag:true, postbackSafe:true, partialRefresh:true'.$fixed_col_setting.'});');
		}
		
		if(isset($template))
			$theme->display($template,true);
		else
			$theme->display();
		$this->set_module_variable('show_all_triggered',false);
	}
	
	public function show_all() {
		return $this->get_module_variable('show_all_triggered',false);
	}

	private function summary() {
		if($this->rows_qty!=0)
			return __('Records %s to %s of %s',array('<b>'.($this->get_module_variable('offset')+1).'</b>','<b>'.(($this->get_module_variable('offset')+$this->get_module_variable('per_page')>$this->rows_qty)?$this->rows_qty:$this->get_module_variable('offset')+$this->get_module_variable('per_page')).'</b>','<b>'.$this->rows_qty.'</b>'));
		else
		if ((isset($this->rows_qty) || (!isset($this->rows_qty) && empty($this->rows))) && !Base_User_SettingsCommon::get(Utils_GenericBrowser::module_name(),'display_no_records_message'))
			return __('No records found');
		else
			return '';
	}
	//endregion
	//region Pagination
	private function gb_first() {
		if($this->get_module_variable('offset')>0)
			return '<a '.$this->create_unique_href(array('first'=>1)).'>'.__('First').'</a>';
	}

	private function gb_prev() {
		if($this->get_module_variable('offset')>0)
    		return '<a '.$this->create_unique_href(array('prev'=>1)).'>'.__('Prev').'</a>';
	}

	private function gb_next() {
		if($this->get_module_variable('offset')+$this->get_module_variable('per_page')<$this->rows_qty)
      		return '<a '.$this->create_unique_href(array('next'=>1)).'>'.__('Next').'</a>';
	}

	private function gb_last() {
		if($this->get_module_variable('offset')+$this->get_module_variable('per_page')<$this->rows_qty)
      		return '<a '.$this->create_unique_href(array('last'=>1)).'>'.__('Last').'</a>';
	}

	public function set_prefix($arg) {
		$this->table_prefix = $arg;
	}

	public function set_postfix($arg) {
		$this->table_postfix = $arg;
	}
	//endregion

}

?>
