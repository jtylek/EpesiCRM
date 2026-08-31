<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>, Kuba Slawinski <kslawinski@telaxus.com> and Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Janusz Tylek
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
    private $expand_collapse_external = false;
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

		$classes = array_map(fn($c) => (str_starts_with($c, '.'))? $c: '.'.$c, $classes);	
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

			$col_names[] = $v['name'] ?? null;
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

	// Lets a caller (e.g. Utils_RecordBrowser's browse() screen) render the
	// Expand All/Collapse All controls itself, elsewhere in its own layout,
	// instead of inside this module's own card-header toolbar - see
	// get_expand_collapse_controls(). Only suppresses this module's own
	// rendering; the buttons' behavior is unchanged since gb_expand_all()/
	// gb_collapse_all() target rows purely by the md5(path) id, not by DOM
	// position.
	public function use_external_expand_collapse_controls($b = true) {
		$this->expand_collapse_external = $b ? true : false;
	}

	public function get_expand_collapse_controls() {
		return $this->expandable ? $this->build_expand_collapse_controls() : null;
	}

	private function build_expand_collapse_controls() {
		$md5_id = md5($this->get_path());
		return array(
			'e_label'=>__('Expand All'),
			'e_href'=>'href="javascript:void(0);" onClick=\'gb_expand_all("'.$md5_id.'")\'',
			'e_id'=>'expand_all_button_'.$md5_id,
			'c_label'=>__('Collapse All'),
			'c_href'=>'href="javascript:void(0);" onClick=\'gb_collapse_all("'.$md5_id.'")\'',
			'c_id'=>'collapse_all_button_'.$md5_id
		);
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
	/**
	 * Bootstrap Icons glyph for each row action this module owns.
	 *
	 * These used to be raster PNGs emitted as <img>, which the adminltedark theme then
	 * hid with display:none and painted over with a ::before glyph selected by
	 * [src*="..."] - so every grid page shipped ~240 <img> elements that were downloaded
	 * and never shown (measured 2026-08-31). Emitting the glyph directly removes both the
	 * elements and the requests, and matches what Base_ActionBar's own adminltedark
	 * template has always done (its $icon_map).
	 *
	 * Keyed by the same action keyword __add_row_action() already uses, so there is no
	 * cross-module filename collision to worry about (Premium/Import ships its own
	 * edit.png/view.png, and reaches the path branch below, not this map).
	 */
	private static $bi_action_icons = array(
		'view'             => 'bi-eye',
		'edit'             => 'bi-pencil-square',
		'delete'           => 'bi-trash',
		'info'             => 'bi-info-circle-fill',
		'print'            => 'bi-printer',
		'restore'          => 'bi-arrow-counterclockwise',
		'active-on'        => 'bi-toggle-on',
		'active-off'       => 'bi-toggle-off',
		'move-up'          => 'bi-arrow-up',
		'move-down'        => 'bi-arrow-down',
		'move-up-down'     => 'bi-arrow-down-up',
		'history'          => 'bi-clock-history',
		'history_inactive' => 'bi-clock-history',
		// Reached through the path branch (callers pass a resolved template file), so
		// keyed by filename stem as well - see action_icon_tag().
		'expand'           => 'bi-chevron-down',
		'collapse'         => 'bi-chevron-up',
		'plus_gray'        => 'bi-chevron-down',
		'minus_gray'       => 'bi-chevron-up',
	);

	/**
	 * Render one row-action icon: a Bootstrap Icons glyph where we know which glyph the
	 * icon means, the original <img> otherwise.
	 *
	 * The <img> fallback is deliberate and load-bearing, not a leftover. Third-party and
	 * legacy modules (Premium/Import ships folder/manual/copy/checkbox icons, and the
	 * default theme exists only for old modules now) pass their own artwork through the
	 * path branch, and there is no way to know what glyph an arbitrary PNG means. Those
	 * keep working exactly as before; converting a module is then just a matter of it
	 * declaring bootstrap_icon(), with nothing here to change.
	 *
	 * @param string|null $keyword action keyword for the keyword branch, null for a path
	 * @param bool        $off     disabled variant - drawn dimmed rather than as a
	 *                             separate "-off" image
	 * @param string      $path    resolved image path, used for the fallback and for
	 *                             module-identity lookup
	 */
	private static function action_icon_tag($keyword, $off, $path) {
		$bi = null;
		// Whether the glyph came out of this module's OWN action map, as opposed to a
		// module's identity icon used as a row shortcut. The adminltedark theme's
		// isCoreAction() (Base_Box/theme_adminltedark/default.tpl) needs that distinction
		// to decide which actions render inline and which group behind the "More actions"
		// toggle, and it used to read it off the <img src> filename - which this method
		// had just stopped emitting for exactly these icons, so every action fell through
		// to "extra" and whole rows collapsed behind the toggle. Marked here rather than
		// re-derived from the bi-* name over there: this is the side that actually knows,
		// and a module identity glyph is free to coincide with a core one.
		$core = false;
		if ($keyword !== null && isset(self::$bi_action_icons[$keyword])) {
			$bi = self::$bi_action_icons[$keyword];
			$core = true;
		} elseif ($path) {
			$stem = strtolower(pathinfo($path, PATHINFO_FILENAME));
			// Only this module's own artwork is matched by stem; another module's
			// edit.png must not silently borrow our glyph.
			if (isset(self::$bi_action_icons[$stem]) && str_contains(str_replace('\\', '/', $path), '/GenericBrowser/')) {
				$bi = self::$bi_action_icons[$stem];
				// Same map, same module's own artwork - the expand/collapse pair reaches this
				// branch rather than the keyword one, and counted as core before too.
				$core = true;
			} elseif (preg_match('/^icon[-_]small$/', $stem)) {
				// Plain require_once at the point of use, same as Base_Menu and the
				// ActionBar template - it is not a Module subclass, so the autoloader
				// never sees it.
				require_once('modules/Base/Theme/bootstrap_icons.php');
				// A module's own identity icon used as a row shortcut (CRM_Contacts'
				// "New Meeting/Task/Phonecall", Utils_Attachment's notes icon). Let the
				// module declare its own glyph rather than mapping each file here - the
				// same resolve() the sidebar and launcher already use, so the icon reads
				// identically everywhere. Returns null when the module declares nothing,
				// which keeps its <img>.
				//
				// Matched on the exact "icon-small"/"icon_small" stem, not any "*_small"
				// (2026-08-31 fix): the module-identity convention IS that filename, and
				// every such shortcut in the app - Premium included - uses it. The looser
				// pattern also swallowed action artwork that merely ends the same way, and
				// resolve() then fell through to the OWNING module's identity glyph: CRM_Mail's
				// "copy" row action rendered as an envelope, Utils_Attachment's "Copy link"
				// and "Cut" both as a journal. Those cannot be repaired by adding filename
				// entries to resolve()'s map either - it is keyed by basename alone, and the
				// very same copy_small.png deliberately means bi-copy for Mail but bi-link
				// for Attachment (see the [src*=".."] rules in Utils/GenericBrowser/
				// theme_adminltedark/default.css). Left as an <img>, each keeps the correct
				// per-module glyph those rules already paint, and isCoreAction() gets back
				// the src it reads CRM_Mail's "keep this one inline" carve-out from.
				$bi = Base_BootstrapIcons::resolve($path, null, null);
			}
		}
		if ($bi) {
			return '<i class="bi '.$bi.' action_button'.($core ? ' action_button_core' : '').($off ? ' action_button_off' : '').'"></i>';
		}
		return '<img class="action_button'.($off ? ' action_button_off' : '').'" src="'.$path.'" border="0">';
	}

	public function __add_row_action($num,$tag_attrs,$label,$tooltip,$icon,$order=0,$off=false,$size=1,$keep_table=false) {
		if (!isset($icon)) $icon = strtolower(trim($label));
		switch ($icon) {
			case 'view': $order = $order?: -3; break;
			case 'edit': $order = $order?: -2; break;
			case 'delete': $order = $order?: -1; break;
			case 'info': $order = $order?: 1000; break;
		}
		$this->actions[$num][$icon] = array('tag_attrs'=>$tag_attrs,'label'=>$label,'tooltip'=>$tooltip, 'off'=>$off, 'size'=>$size, 'order'=>$order, 'keep_table'=>$keep_table);
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

		if(!$this->columns)
			trigger_error('columns array empty, please call set_table_columns',E_USER_ERROR);

		if (!$adv){
			if (!isset($search['__keyword__']) || $search['__keyword__']=='') return true;
			$ret = true;
			foreach($this->columns as $k=>$v){
				if (isset($v['search']) && isset($search['__keyword__'])) {
					$ret = false;
					if (is_array($row[$k])) $row[$k] = $row[$k]['value'];
					if (stripos(strip_tags($row[$k]),(string) $search['__keyword__'])!==false) return true;
				}
			}
			return $ret;
		} else {
			foreach($this->columns as $k=>$v){
				if (isset($v['search']) && isset($search[$v['search']]) && stripos(strip_tags(is_array($row[$k])?$row[$k]['value']:$row[$k]),(string) $search[$v['search']])===false) return false;
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
							$xxx = $ret[1] ?? '';
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
				$js[] = $this->rows_jses[$k] ?? '';
				$actions[] = $this->actions[$k] ?? array();
				$row_attrs[] = $this->row_attrs[$k] ?? '';
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

		$renderer = new EpesiSmartyRenderer();
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
					$this->form_s->setDefaults(array('search'=>$search['__keyword__'] ?? ''));
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
					$type = $v['search_type'] ?? 'text';
					// quickform element to perform proper export
					$this->form_s->addElement($type, 'search__' . $v['search'], '');
					// hidden element to pass data during submit
					$default = $search[$v['search']] ?? '';
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
					if (str_starts_with($k, 'search__')) {
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
			if ($v['name'] && isset($order[0]['column']) && $v['name']==$order[0]['column']) {
				if (Base_ThemeCommon::is_adminlte_family()) {
					// Bootstrap Icons glyph instead of the legacy theme's own
					// sort-ascending.png/sort-descending.png (no adminltedark
					// copy of those ever existed, so get_template_file() was
					// silently falling back to the legacy theme's raster
					// icon - looked stock/unstyled against this theme).
					$icon = strtolower($order[0]['direction'])=='desc' ? 'bi-caret-down-fill' : 'bi-caret-up-fill';
					$label = '<span class="Utils_GenericBrowser__sort">'.$v['name'].' <i class="bi '.$icon.'"></i></span>';
				} else {
					$label = '<span style="padding-right: 12px; margin-right: 12px; background-image: url('.Base_ThemeCommon::get_template_file('Utils_GenericBrowser','sort-'.strtolower($order[0]['direction']).'ending.png').'); background-repeat: no-repeat; background-position: right;">'.$v['name'].'</span>';
				}
			}
			else $label = $v['name'];
			$headers[$i]['label'] .= ($v['preppend'] ?? '').(isset($v['order'])?'<a '.$this->create_unique_href(array('change_order'=>$v['name'])).'>' . $label . '</a>':$label).($v['append'] ?? '');
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

            // Four gb_*_icon variables were emitted here (expand/collapse and an
            // _off pair). All four are gone as of 2026-08-31. The expand/collapse
            // pair had exactly one reader, js/table_overflow.js's two .src writes,
            // which stopped doing anything once these actions became <i class="bi ...">
            // and are removed too. The _off pair was read by nothing at all, and had
            // not been for some time - it predates the icon conversion.
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
					uasort($this->actions[$i], $this->sort_actions(...));
					$actions = '';
					foreach($this->actions[$i] as $icon=>$arr) {
						$actions .= '<a '.Utils_TooltipCommon::open_tag_attrs($arr['tooltip'] ?? $arr['label'], $arr['tooltip']===null, 500, $arr['keep_table'] ?? false).' '.$arr['tag_attrs'].'>';
					    if ($icon=='view' || $icon=='delete' || $icon=='edit' || $icon=='info' || $icon=='restore' || $icon=='append data' || $icon=='active-on' || $icon=='active-off' || $icon=='history' || $icon=='move-down' || $icon=='move-up' || $icon=='history_inactive' || $icon=='print' || $icon == 'move-up-down') {
							$actions .= self::action_icon_tag($icon, (bool)$arr['off'], Base_ThemeCommon::get_template_file(Utils_GenericBrowser::module_name(),$icon.($arr['off']?'-off':'').'.png'));
					    } elseif(file_exists($icon)) {
							$actions .= self::action_icon_tag(null, false, $icon);
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
						$col[$column_no]['attrs'] .= ' onmouseover="if(typeof(table_overflow_show)!=\'undefined\')table_overflow_show(this,true,event);" onmouseout="if(typeof(table_overflow_hide)!=\'undefined\')table_overflow_hide();"';
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
				// A cell whose value already carries its own tooltip (e.g.
				// CRM_ContactsCommon::company_get_tooltip() via
				// Utils_RecordBrowserCommon::create_linked_text() - a
				// data-epesi-tooltip/'Utils_Tooltip.load_ajax(' attribute
				// on the <a> itself) doesn't get the overflow preview too:
				// onmouseover bubbles from that <a> up to this <td> regardless,
				// so both used to fire on the same hover - the plain "full
				// truncated text" preview stacked on top of the richer
				// explicit tooltip, which already opens with that same text
				// (see company_get_tooltip()'s bolded first row) so nothing is
				// lost by letting the explicit tooltip win alone. Skipped only
				// for this, not folded into the overflow_box=>false path
				// below - that path also switches the cell to
				// white-space:normal (wrapping instead of truncating), which
				// isn't needed here since the tooltip already covers full-text
				// disclosure and the cell should keep its normal ellipsis.
				$has_own_tooltip = Utils_TooltipCommon::is_tooltip_code_in_str($col[$k]['label']);
				if (!$has_own_tooltip && (!isset($v['overflow_box']) || $v['overflow_box'])) {
					$col[$k]['attrs'] .= ' onmouseover="if(typeof(table_overflow_show)!=\'undefined\')table_overflow_show(this,false,event);" onmouseout="if(typeof(table_overflow_hide)!=\'undefined\')table_overflow_hide();"';
				} elseif (!$has_own_tooltip) {
					if (!isset($v['style'])) $v['style'] = '';
					$v['style'] .= 'white-space: normal;';
				}
				$col[$k]['attrs'] .= ' class="Utils_GenericBrowser__td '.($v['class'] ?? '').'"';
				$col[$k]['attrs'] .= isset($v['style'])? ' style="'.$v['style'].'"':'';
				if ((!isset($this->columns[$k]['wrapmode']) || $this->columns[$k]['wrapmode']!='cut') && isset($v['hint'])) $col[$k]['attrs'] .= ' title="'.$v['hint'].'"';
				$col[$k]['attrs'] .= (isset($this->columns[$k]['wrapmode']) && $this->columns[$k]['wrapmode']=='nowrap')?' nowrap':'';
				if ($all_width!=0)
					$max_width = 130*(str_ends_with($this->columns[$k]['width'], "px")
							? (int)substr($this->columns[$k]['width'],0,-2)
							: (int)$this->columns[$k]['width'])/$all_width*(7+($this->columns[$k]['fontsize'] ?? 0));
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
            if (!$this->expand_collapse_external) {
                $theme->assign('expand_collapse', $this->build_expand_collapse_controls());
            }
            $max_actions ??= 0;
            eval_js('gb_expandable_adjust_action_column("'.$md5_id.'", ' . $max_actions . ')');
            eval_js('gb_show_hide_buttons("'.$md5_id.'")');
        }

		if ($search_on) $theme->assign('adv_search','<a id="switch_search_'.($this->is_adv_search_on()?'simple':'advanced').'" class="button" '.$this->create_unique_href(array('adv_search'=>!$this->is_adv_search_on())).'><i class="bi bi-search"></i> ' . ($this->is_adv_search_on()?__('Simple Search'):__('Advanced Search')) . '</a>');
		else $theme->assign('adv_search','');

		if (Base_User_SettingsCommon::get(Utils_GenericBrowser::module_name(),'adv_history') && $is_order){
			$theme->assign('reset', '<a '.$this->create_unique_href(array('action'=>'reset_order')).'>'.__('Reset Order').'</a>');
			$theme->assign('order',$this->get_module_variable('order_history_display'));
		}
		$theme->assign('id',md5($this->get_path()));

		// Column drag-to-resize (js/col_resizable.js, jQuery colResizable) was
		// dropped when this grid's markup moved from a real <table> to CSS
		// table-display divs (see AI-shared/adminlte-theme.md) - the vendored
		// plugin hard-requires an actual <table> element and has no
		// div-compatible equivalent. $resizable_columns/set_resizable_columns()
		// are kept as inert API surface (RecordBrowser_0.php still calls
		// set_resizable_columns(false) for PDF export, where it's moot anyway
		// since pdf.tpl still renders a real <table> via {html_table_epesi}).

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
