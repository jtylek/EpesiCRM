<?php
/**
 * RecordBrowserCommon class.
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage RecordBrowser
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowserCommon extends ModuleCommon {
	private static $table_rows = array();
	private static $del_or_a = '';
	private static $hash = array();
	public static $admin_access = false;
	public static $cols_order = array();
	
	private static $clear_get_val_cache = false;

	public static function get_val($tab, $field, $record, $links_not_recommended = false, $args = null) {
		self::init($tab);
		$commondata_sep = '/';
		static $display_callback_table = array();
		if (self::$clear_get_val_cache) {
			self::$clear_get_val_cache = false;
			$display_callback_table = array();
		}
		if (!isset(self::$table_rows[$field])) {
			if (!isset(self::$hash[$field])) trigger_error('Unknown field "'.$field.'" for recordset "'.$tab.'"',E_USER_ERROR);
			$field = self::$hash[$field];
		}
		if ($args===null) $args = self::$table_rows[$field];
		if (!isset($display_callback_table[$tab])) {			
			$ret = DB::Execute('SELECT * FROM '.$tab.'_callback WHERE freezed=1');
			while ($row = $ret->FetchRow())
				$display_callback_table[$tab][$row['field']] = explode('::',$row['callback']);
		}
		if (!isset($record[$args['id']])) trigger_error($args['id'].' - unknown field for record '.serialize($args), E_USER_ERROR);
		$val = $record[$args['id']];
		if (isset($display_callback_table[$tab][$field])) {
			$ret = call_user_func($display_callback_table[$tab][$field], $record, $links_not_recommended, self::$table_rows[$field]);
		} else {
			$ret = $val;
			if ($args['type']=='select' || $args['type']=='multiselect') {
				if ((is_array($val) && empty($val)) || (!is_array($val) && $val=='')) {
					$ret = '---';
					return $ret;
				}
				$param = explode(';',$args['param']);
				$pp = explode('::',$param[0]);
				$tab = $pp[0];
				if (isset($pp[1])) $col = $pp[1]; else return $val;
				if (!is_array($val)) $val = array($val);
//				if ($tab=='__COMMON__') $data = Utils_CommonDataCommon::get_translated_array($col, true);
				$ret = '';
				$first = true;
				foreach ($val as $k=>$v){
//					if ($tab=='__COMMON__' && !isset($data[$v])) continue;
					if ($first) $first = false;
					else $ret .= '<br>';
					if ($tab=='__COMMON__') {
						$path = explode('/',$v);
						$tooltip = '';
						$res = '';
						if (count($path)>1) {
							$res .= Utils_CommonDataCommon::get_value($col.'/'.$path[0],true);
							if (count($path)>2) {
								$res .= $commondata_sep.'...';
								$tooltip = '';
								$full_path = $col;
								foreach ($path as $w) {
									$full_path .= '/'.$w;
									$tooltip .= ($tooltip?$commondata_sep:'').Utils_CommonDataCommon::get_value($full_path,true);
								}
							}
							$res .= $commondata_sep;
						}
						$res .= Utils_CommonDataCommon::get_value($col.'/'.$v,true);
						$res = self::no_wrap($res);
						if ($tooltip) $res = '<span '.Utils_TooltipCommon::open_tag_attrs($tooltip, false).'>'.$res.'</span>';
						$ret .= $res;
					} else $ret .= Utils_RecordBrowserCommon::create_linked_label($tab, $col, $v, $links_not_recommended);
				}
				if ($ret=='') $ret = '---';
			}
			if ($args['type']=='commondata') {
				if (!isset($val) || $val==='') {
					$ret = '';
				} else {
					$arr = explode('::',$args['param']['array_id']);
					$path = array_shift($arr);
					foreach($arr as $v) $path .= '/'.$record[strtolower(str_replace(' ','_',$v))];
					$path .= '/'.$record[$args['id']];
					$ret = Utils_CommonDataCommon::get_value($path,true);
				}
			}
			if ($args['type']=='currency') {
				$val = Utils_CurrencyFieldCommon::get_values($val);
				$ret = Utils_CurrencyFieldCommon::format($val[0], $val[1]);
			}
			if ($args['type']=='checkbox') {
				$ret = Base_LangCommon::ts('Utils_RecordBrowser',$ret?'Yes':'No');
			}
			if ($args['type']=='date') {
				if ($val!='') $ret = Base_RegionalSettingsCommon::time2reg($val, false,true,false);
			}
			if ($args['type']=='timestamp') {
				if ($val!='') $ret = Base_RegionalSettingsCommon::time2reg($val, 'without_seconds');
			}
			if ($args['type']=='time') {
				if ($val!='') $ret = Base_RegionalSettingsCommon::time2reg($val, 'without_seconds',false);
			}
		}
		return $ret;
	}
	public static function multiselect_from_common($arrid) {
		return '__COMMON__::'.$arrid;
	}
	public static function format_long_text_array($tab,$records){
		self::init($tab);
		foreach(self::$table_rows as $field => $args) {
			if ($args['type']!='long text') continue;
			foreach ($records as $k=>$v) {
				$records[$k][$args['id']] = str_replace("\n",'<br>',$v[$args['id']]);
				$records[$k][$args['id']] = Utils_BBCodeCommon::parse($records[$k][$args['id']]);
			}
		}
		return $records;
	}
	public static function encode_multi($v) {
		if ($v==='') return '';
		if (is_array($v)) {
			if (empty($v)) return ''; 
		} else $v = array($v);
		return '__'.implode('__',$v).'__';
	}
	public static function decode_multi($v) {
		if ($v===null) return array();
		$v = explode('__',$v);
		array_shift($v);
		array_pop($v);
		$ret = array();
		foreach ($v as $w)
			$ret[$w] = $w;
		return $ret;
	}
	public static function decode_commondata_param($param) {
		$param = explode('__',$param);
		if (isset($param[1]))
			$param = array('order_by_key'=>$param[0], 'array_id'=>$param[1]);
		else
			$param = array('order_by_key'=>false, 'array_id'=>$param[0]);
		return $param;
	}
	public static function encode_commondata_param($param) {
		if (!is_array($param)) return '0__'.$param;
		if (isset($param[0])) {
			array_unshift($param, 0);
		} else {
			$param = array($param['order_by_key'], $param['array_id']);
		}
		return implode('__', $param);
	}
	public static function format_long_text($tab,$record){
		self::init($tab);
		foreach(self::$table_rows as $field => $args) {
			if ($args['type']!='long text') continue;
			$record[$args['id']] = str_replace("\n",'<br>',htmlspecialchars($record[$args['id']]));
			$record[$args['id']] = Utils_BBCodeCommon::parse($record[$args['id']]);
		}
		return $record;
	}

	public static function user_settings(){
		$ret = DB::Execute('SELECT tab, caption, icon, recent, favorites, full_history FROM recordbrowser_table_properties');
		$settings = array(0=>array(), 1=>array(), 2=>array(), 3=>array());
		while ($row = $ret->FetchRow()) {
			if (!self::get_access($row['tab'],'browse')) continue;
			if ($row['favorites'] || $row['recent']) {
				$options = array('all'=>'All');
				if ($row['favorites']) $options['favorites'] = 'Favorites';
				if ($row['recent']) $options['recent'] = 'Recent';
				$settings[0][] = array('name'=>$row['tab'].'_default_view','label'=>$row['caption'],'type'=>'select','values'=>$options,'default'=>'all');
			}
			if ($row['favorites']) 
				$settings[1][] = array('name'=>$row['tab'].'_auto_fav','label'=>$row['caption'],'type'=>'select','values'=>array('Disabled', 'Enabled'),'default'=>0);
			if (Utils_WatchdogCommon::category_exists($row['tab'])) {
				$settings[2][] = array('name'=>$row['tab'].'_auto_subs','label'=>$row['caption'],'type'=>'select','values'=>array('Disabled', 'Enabled'),'default'=>0);
//				$settings[3][] = array('name'=>$row['tab'].'_subs_category','label'=>$row['caption'],'type'=>'select','values'=>array('Disabled', 'Enabled'),'default'=>0);
			}
		}
		$final_settings = array();
		$final_settings[] = array('name'=>'header_default_view','label'=>'Default data view','type'=>'header');
		$final_settings = array_merge($final_settings,$settings[0]);
		$final_settings[] = array('name'=>'header_auto_fav','label'=>'Automatically add to favorites records created by me','type'=>'header');
		$final_settings = array_merge($final_settings,$settings[1]);
		$final_settings[] = array('name'=>'header_auto_subscriptions','label'=>'Auto-subscribe to records created by me','type'=>'header');
		$final_settings = array_merge($final_settings,$settings[2]);
//		$final_settings[] = array('name'=>'header_category_subscriptions','label'=>'Auto-subscribe to all new records','type'=>'header');
//		$final_settings = array_merge($final_settings,$settings[3]);
		return array('Browsing Records'=>$final_settings);
	}
	public static function check_table_name($tab, $flush=false){
		static $tables = null;
		if ($tables===null || $flush) {
			$r = DB::GetAll('SELECT tab FROM recordbrowser_table_properties');
			$tables = array();
			foreach($r as $v)
				$tables[$v['tab']] = true;
		}
		if (!isset($tables[$tab]) && !$flush) trigger_error('RecordBrowser critical failure, terminating. (Requested '.serialize($tab).', available '.print_r($tables, true).')', E_USER_ERROR);
		return isset($tables[$tab]);
	}
	public static function get_value($tab, $id, $field) {
		self::init($tab);
		if (isset(self::$table_rows[$field])) $field = self::$table_rows[$field]['id'];
		elseif (!isset(self::$hash[$field])) trigger_error('get_value(): Unknown column: '.$field, E_USER_ERROR);
		$ret = DB::GetOne('SELECT f_'.$field.' FROM '.$tab.'_data_1 WHERE id=%d', array($id));
		if ($ret===false || $ret===null) return null;
		return $ret;
	}
	public static function count_possible_values($tab, $field) { //it ignores empty values!
		self::init($tab);
		if (isset(self::$table_rows[$field])) $field = self::$table_rows[$field]['id'];
		elseif (!isset(self::$hash[$field])) trigger_error('count_possible_values(): Unknown column: '.$field, E_USER_ERROR);
		$par = self::build_query($tab, array('!'.$field=>''));
		return DB::GetOne('SELECT COUNT(DISTINCT(f_'.$field.')) FROM '.$par['sql'], $par['vals']);
	}
	public static function get_possible_values($tab, $field) {
		self::init($tab);
		if (isset(self::$table_rows[$field])) $field = self::$table_rows[$field]['id'];
		elseif (!isset(self::$hash[$field])) trigger_error('get_possible_values(): Unknown column: '.$field, E_USER_ERROR);
		$par = self::build_query($tab, array('!'.$field=>''));
		return DB::GetAssoc('SELECT MIN(id), MIN(f_'.$field.') FROM'.$par['sql'].' GROUP BY f_'.$field, $par['vals']);
	}
	public static function get_id($tab, $field, $value) {
		self::init($tab);
		$where = '';
		if (!is_array($field)) $field=array($field);
		if (!is_array($value)) $value=array($value);
		foreach ($field as $k=>$v) {
			if (!$v) continue;
			if (isset(self::$table_rows[$v])) $v = $field[$k] = self::$table_rows[$v]['id'];
			elseif (!isset(self::$hash[$v])) trigger_error('get_id(): Unknown column: '.$v, E_USER_ERROR);
			$f_id = self::$hash[$v];
			if (self::$table_rows[$f_id]['type']=='multiselect') {
				$where .= ' AND f_'.$v.' LIKE '.DB::Concat(DB::qstr('%\_\_'),'%s',DB::qstr('\_\_%'));
			} else $where .= ' AND f_'.$v.'=%s';
			
		}
		$ret = DB::GetOne('SELECT id FROM '.$tab.'_data_1 WHERE active=1'.$where, $value);
		if ($ret===false || $ret===null) return null;
		return $ret;
	}
	public static function is_active($tab, $id) {
		self::init($tab);
		return DB::GetOne('SELECT active FROM '.$tab.'_data_1 WHERE id=%d', array($id));
	}
	public static function admin_caption() {
		return 'Records Sets';
	}
	public static function init($tab, $admin=false, $force=false) {
		static $cache = array();
		if (!isset(self::$cols_order[$tab])) self::$cols_order[$tab] = array();
		if (!$force && isset($cache[$tab.'__'.$admin.'__'.md5(serialize(self::$cols_order[$tab]))])) {
			self::$hash = $cache[$tab.'__'.$admin.'__'.md5(serialize(self::$cols_order[$tab]))]['hash'];
			return self::$table_rows = $cache[$tab.'__'.$admin.'__'.md5(serialize(self::$cols_order[$tab]))]['rows'];
		}
		self::$table_rows = array();
		self::check_table_name($tab);
		$ret = DB::Execute('SELECT * FROM '.$tab.'_field'.($admin?'':' WHERE active=1 AND type!=\'page_split\'').' ORDER BY position');
		self::$hash = array();
		while($row = $ret->FetchRow()) {
			if ($row['field']=='id') continue;
			if ($row['type']=='commondata')
				$row['param'] = self::decode_commondata_param($row['param']);
			self::$table_rows[$row['field']] =
				array(	'name'=>$row['field'],
						'id'=>strtolower(str_replace(' ','_',$row['field'])),
						'type'=>$row['type'],
						'visible'=>$row['visible'],
						'required'=>($row['type']=='calculated'?false:$row['required']),
						'extra'=>$row['extra'],
						'active'=>$row['active'],
						'position'=>$row['position'],
						'filter'=>$row['filter'],
						'style'=>$row['style'],
						'param'=>$row['param']);
			self::$hash[self::$table_rows[$row['field']]['id']] = $row['field'];
		}
		if (!empty(self::$cols_order[$tab])) {
			$rows = array();
			foreach (self::$cols_order[$tab] as $v) {
				$rows[self::$hash[$v]] = self::$table_rows[self::$hash[$v]];
				unset(self::$table_rows[self::$hash[$v]]);
			}
			foreach(self::$table_rows as $k=>$v)
				$rows[$k] = $v;
			self::$table_rows = $rows;
		}
		$cache[$tab.'__'.$admin.'__'.md5(serialize(self::$cols_order[$tab]))] = array('rows'=>self::$table_rows,'hash'=>self::$hash);
		return self::$table_rows;
	}

	public static function install_new_recordset($tab, $fields=array()) {
		if (!preg_match('/^[a-zA-Z_]+$/',$tab)) trigger_error('Invalid table name ('.$tab.') given to install_new_recordset.',E_USER_ERROR);
		if (DB::GetOne('SELECT 1 FROM recordbrowser_table_properties WHERE tab=%s', array($tab))) {
			@DB::DropTable($tab.'_callback');
			@DB::DropTable($tab.'_recent');
			@DB::DropTable($tab.'_favorite');
			@DB::DropTable($tab.'_edit_history_data');
			@DB::DropTable($tab.'_edit_history');
			@DB::DropTable($tab.'_field');
			@DB::DropTable($tab.'_data_1');
		} else {
			DB::Execute('INSERT INTO recordbrowser_table_properties (tab) VALUES (%s)', array($tab));
		}
		self::check_table_name(null, true);
		DB::CreateTable($tab.'_field',
					'field C(32) UNIQUE NOT NULL,'.
					'type C(32),'.
					'extra I1 DEFAULT 1,'.
					'visible I1 DEFAULT 1,'.
					'required I1 DEFAULT 1,'.
					'active I1 DEFAULT 1,'.
					'position I,'.
					'filter I1 DEFAULT 0,'.
					'param C(255),'.
					'style C(64)',
					array('constraints'=>''));
		DB::CreateTable($tab.'_edit_history',
					'id I AUTO KEY,'.
					$tab.'_id I NOT NULL,'.
					'edited_on T NOT NULL,'.
					'edited_by I NOT NULL',
					array('constraints'=>', FOREIGN KEY (edited_by) REFERENCES user_login(id)'));
		DB::CreateTable($tab.'_edit_history_data',
					'edit_id I,'.
					'field C(32),'.
					'old_value C(255)',
					array('constraints'=>', FOREIGN KEY (edit_id) REFERENCES '.$tab.'_edit_history(id)'));
		DB::CreateTable($tab.'_favorite',
					$tab.'_id I,'.
					'user_id I',
					array('constraints'=>', FOREIGN KEY (user_id) REFERENCES user_login(id)'));
		DB::CreateTable($tab.'_recent',
					$tab.'_id I,'.
					'user_id I,'.
					'visited_on T',
					array('constraints'=>', FOREIGN KEY (user_id) REFERENCES user_login(id)'));
		DB::CreateTable($tab.'_callback',
					'field C(32),'.
					'callback C(255),'.
					'freezed I1',
					array('constraints'=>''));
		DB::Execute('INSERT INTO '.$tab.'_field(field, type, extra, visible, position) VALUES(\'id\', \'foreign index\', 0, 0, 1)');
		DB::Execute('INSERT INTO '.$tab.'_field(field, type, extra, position) VALUES(\'General\', \'page_split\', 0, 2)');
		DB::Execute('INSERT INTO '.$tab.'_field(field, type, extra, position) VALUES(\'Details\', \'page_split\', 0, 3)');
		DB::CreateTable($tab.'_data_1',
					'id I AUTO KEY,'.
					'created_on T NOT NULL,'.
					'created_by I NOT NULL,'.
					'private I4 DEFAULT 0,'.
					'active I1 NOT NULL DEFAULT 1',
					array('constraints'=>''));
		foreach ($fields as $v)
			Utils_RecordBrowserCommon::new_record_field($tab, $v);
		return true;
	}
	public static function enable_watchdog($tab,$watchdog_callback) {
		self::check_table_name($tab);
		Utils_WatchdogCommon::register_category($tab, $watchdog_callback);
	}
	public static function set_display_callback($tab, $field, $callback) {
		self::check_table_name($tab);
		self::$clear_get_val_cache = true;
		if (is_array($callback)) $callback = implode('::',$callback);
		DB::Execute('DELETE FROM '.$tab.'_callback WHERE field=%s AND freezed=1', array($field));
		DB::Execute('INSERT INTO '.$tab.'_callback (field, callback, freezed) VALUES(%s, %s, 1)', array($field, $callback));
	}
	public static function set_QFfield_callback($tab, $field, $callback) {
		self::check_table_name($tab);
		self::$clear_get_val_cache = true;
		if (is_array($callback)) $callback = implode('::',$callback);
		DB::Execute('DELETE FROM '.$tab.'_callback WHERE field=%s AND freezed=0', array($field));
		DB::Execute('INSERT INTO '.$tab.'_callback (field, callback, freezed) VALUES(%s, %s, 0)', array($field, $callback));
	}
	public static function unset_display_callback($tab, $field) {
		self::check_table_name($tab);
		self::$clear_get_val_cache = true;
		DB::Execute('DELETE FROM '.$tab.'_callback WHERE field=%s AND freezed=1', array($field));
	}
	public static function unset_QFfield_callback($tab, $field) {
		self::check_table_name($tab);
		self::$clear_get_val_cache = true;
		DB::Execute('DELETE FROM '.$tab.'_callback WHERE field=%s AND freezed=0', array($field));
	}

	public static function uninstall_recordset($tab) {
		if (!self::check_table_name($tab,true)) return;
		self::$clear_get_val_cache = true;
		Utils_WatchdogCommon::unregister_category($tab);
		DB::DropTable($tab.'_callback');
		DB::DropTable($tab.'_recent');
		DB::DropTable($tab.'_favorite');
		DB::DropTable($tab.'_edit_history_data');
		DB::DropTable($tab.'_edit_history');
		DB::DropTable($tab.'_field');
		DB::DropTable($tab.'_data_1');
		DB::Execute('DELETE FROM recordbrowser_table_properties WHERE tab=%s', array($tab));
		return true;
	}

	public static function delete_record_field($tab, $field){
		self::init($tab);
		self::$clear_get_val_cache = true;
		$exists = DB::GetOne('SELECT 1 FROM '.$tab.'_field WHERE field=%s', array($field));
		if(!$exists) return;
		DB::Execute('DELETE FROM '.$tab.'_field WHERE field=%s', array($field));
		@DB::Execute('ALTER TABLE '.$tab.'_data_1 DROP COLUMN f_'.self::$table_rows[$field]['id']);
		self::init($tab, false, true);
	}
	public static function new_record_field($tab, $definition){
		static $datatypes = null;
		if ($datatypes===null) {
			$datatypes = array();
			$ret = DB::Execute('SELECT * FROM recordbrowser_datatype');
			while ($row = $ret->FetchRow())
				$datatypes[$row['type']] = array($row['module'], $row['func']);
		}
		if (!is_array($definition)) {
			// Backward compatibility - got to get rid of this one someday
			$args = func_get_args();
			array_shift($args);
			$definition = array();
			foreach (array(	0=>'name',
					1=>'type',
					2=>'visible',
					3=>'required',
					4=>'param',
					5=>'style',
					6=>'extra',
					7=>'filter',
					8=>'position') as $k=>$w)
				if (isset($args[$k])) $definition[$w] = $args[$k];
		}
		if (!isset($definition['type'])) trigger_error(print_r($definition,true));
		if (!isset($definition['param'])) $definition['param'] = '';
		if (!isset($definition['style'])) {
			if (in_array($definition['type'], array('time','timestamp','currency')))
				$definition['style'] = $definition['type'];
			else {
				if (in_array($definition['type'], array('float','integer')))
					$definition['style'] = 'number';
				else
					$definition['style'] = '';
			}
		}
		if (!isset($definition['extra'])) $definition['extra'] = true;
		if (!isset($definition['visible'])) $definition['visible'] = false;
		if (!isset($definition['required'])) $definition['required'] = false;
		if (!isset($definition['filter'])) $definition['filter'] = false;
		if (!isset($definition['position'])) $definition['position'] = null;
		if (isset($datatypes[$definition['type']])) $definition = call_user_func($datatypes[$definition['type']], $definition);

		if (isset($definition['display_callback'])) self::set_display_callback($tab, $definition['name'], $definition['display_callback']);
		if (isset($definition['QFfield_callback'])) self::set_QFfield_callback($tab, $definition['name'], $definition['QFfield_callback']);
//		$field, $type, $visible, $required, $param='', $style='', $extra = true, $filter = false, $pos = null

		self::check_table_name($tab);
		self::$clear_get_val_cache = true;
		$exists = DB::GetOne('SELECT field FROM '.$tab.'_field WHERE field=%s', array($definition['name']));
		if ($exists) return;
		
		DB::StartTrans();
		if (is_string($definition['position'])) $definition['position'] = DB::GetOne('SELECT position FROM '.$tab.'_field WHERE field=%s', array($definition['position']))+1;
		if ($definition['position']===null || $definition['position']===false) {
			if ($definition['extra'])
				$definition['position'] = DB::GetOne('SELECT MAX(position) FROM '.$tab.'_field')+1;
			else
				$definition['position'] = DB::GetOne('SELECT position FROM '.$tab.'_field WHERE field=%s', array('Details'));
		}
		DB::Execute('UPDATE '.$tab.'_field SET position = position+1 WHERE position>=%d', array($definition['position']));
		DB::CompleteTrans();
		
		$param = $definition['param'];
		if (is_array($param)) {
			if ($definition['type']=='commondata') {
				if (isset($param['order_by_key'])) {
					$obk = $param['order_by_key'];
					unset($param['order_by_key']);
					$param = array('array_id'=>implode('::',$param));
					$param['order_by_key'] = $obk;
					$param = self::encode_commondata_param($param);
				} else $param = implode('::',$param);
			} else {
				$tmp = array();
				foreach ($param as $k=>$v) $tmp[] = $k.'::'.$v;
				$param = implode(';',$tmp);
			}
		}
		$f = self::actual_db_type($definition['type'], $param);
		if ($f!=='') @DB::Execute('ALTER TABLE '.$tab.'_data_1 ADD COLUMN f_'.strtolower(str_replace(' ','_',$definition['name'])).' '.$f);
		DB::Execute('INSERT INTO '.$tab.'_field(field, type, visible, param, style, position, extra, required, filter) VALUES(%s, %s, %d, %s, %s, %d, %d, %d, %d)', array($definition['name'], $definition['type'], $definition['visible']?1:0, $param, $definition['style'], $definition['position'], $definition['extra']?1:0, $definition['required']?1:0, $definition['filter']?1:0));
		self::init($tab, false, true);
	}
	public static function actual_db_type($type, $param=null) {
		$f = '';
		switch ($type) {
			case 'page_split': $f = ''; break;

			case 'text': $f = DB::dict()->ActualType('C').'('.$param.')'; break;
			case 'select': $f = DB::dict()->ActualType('I4'); break;
			case 'multiselect': $f = DB::dict()->ActualType('X'); break;
			case 'commondata': $f = DB::dict()->ActualType('C').'(128)'; break;
			case 'integer': $f = DB::dict()->ActualType('I4'); break;
			case 'float': $f = DB::dict()->ActualType('F'); break;
			case 'date': $f = DB::dict()->ActualType('D'); break;
			case 'timestamp': $f = DB::dict()->ActualType('T'); break;
			case 'time': $f = DB::dict()->ActualType('T'); break;
			case 'long text': $f = DB::dict()->ActualType('X'); break;
			case 'hidden': $f = (isset($param)?$param:''); break;
			case 'calculated': $f = (isset($param)?$param:''); break;
			case 'checkbox': $f = DB::dict()->ActualType('I1'); break;
			case 'currency': $f = DB::dict()->ActualType('C').'(128)'; break;
		}
		if (!isset($f)) trigger_error('Database column for type '.$type.' undefined.',E_USER_ERROR);
		return $f;
	}
	public static function new_addon($tab, $module, $func, $label) {
		if (is_array($label)) $label= implode('::',$label);
		$module = str_replace('/','_',$module);
		self::delete_addon($tab, $module, $func);
		$pos = DB::GetOne('SELECT MAX(pos) FROM recordbrowser_addon WHERE tab=%s', array($tab));
		if (!$pos) $pos=0;
		DB::Execute('INSERT INTO recordbrowser_addon (tab, module, func, label, pos, enabled) VALUES (%s, %s, %s, %s, %d, 1)', array($tab, $module, $func, $label, $pos+1));
	}
	public static function delete_addon($tab, $module, $func) {
		$module = str_replace('/','_',$module);
		$pos = DB::GetOne('SELECT pos FROM recordbrowser_addon WHERE tab=%s AND module=%s AND func=%s', array($tab, $module, $func));
		if ($pos===false || $pos===null) return;
		DB::Execute('DELETE FROM recordbrowser_addon WHERE tab=%s AND module=%s AND func=%s', array($tab, $module, $func));
		while (DB::GetOne('SELECT pos FROM recordbrowser_addon WHERE tab=%s AND pos=%d', array($tab, $pos+1))) {
			DB::Execute('UPDATE recordbrowser_addon SET pos=pos-1 WHERE tab=%s AND pos=%d', array($tab, $pos+1));
			$pos++;
		}
	}
	public static function set_addon_pos($tab, $module, $func, $pos) {
		$module = str_replace('/','_',$module);
		$old_pos = DB::GetOne('SELECT pos FROM recordbrowser_addon WHERE tab=%s AND module=%s AND func=%s', array($tab, $module, $func));
		DB::Execute('UPDATE recordbrowser_addon SET pos=%d WHERE tab=%s AND pos=%d', array($old_pos, $tab, $pos));
		DB::Execute('UPDATE recordbrowser_addon SET pos=%d WHERE tab=%s AND module=%s AND func=%s', array($pos, $tab, $module, $func));
	}
	public static function register_datatype($type, $module, $func) {
		DB::Execute('INSERT INTO recordbrowser_datatype (type, module, func) VALUES (%s, %s, %s)', array($type, $module, $func));
	}
	public static function unregister_datatype($type) {
		DB::Execute('DELETE FROM recordbrowser_datatype WHERE type=%s', array($type));
	}
	public static function new_filter($tab, $col_name) {
		self::check_table_name($tab);
		DB::Execute('UPDATE '.$tab.'_field SET filter=1 WHERE field=%s', array($col_name));
	}
	public static function delete_filter($tab, $col_name) {
		self::check_table_name($tab);
		DB::Execute('UPDATE '.$tab.'_field SET filter=0 WHERE field=%s', array($col_name));
	}
	public static function set_processing_callback($tab, $method) {
		DB::Execute('UPDATE recordbrowser_table_properties SET data_process_method=%s WHERE tab=%s', array($method[0].'::'.$method[1], $tab));
	}
	public static function set_quickjump($tab, $col_name) {
		DB::Execute('UPDATE recordbrowser_table_properties SET quickjump=%s WHERE tab=%s', array($col_name, $tab));
	}
	public static function set_tpl($tab, $filename) {
		DB::Execute('UPDATE recordbrowser_table_properties SET tpl=%s WHERE tab=%s', array($filename, $tab));
	}
	public static function set_favorites($tab, $value) {
		DB::Execute('UPDATE recordbrowser_table_properties SET favorites=%d WHERE tab=%s', array($value?1:0, $tab));
	}
	public static function set_recent($tab, $value) {
		DB::Execute('UPDATE recordbrowser_table_properties SET recent=%d WHERE tab=%s', array($value, $tab));
	}
	public static function set_full_history($tab, $value) {
		DB::Execute('UPDATE recordbrowser_table_properties SET full_history=%d WHERE tab=%s', array($value?1:0, $tab));
	}
	public static function set_caption($tab, $value) {
		DB::Execute('UPDATE recordbrowser_table_properties SET caption=%s WHERE tab=%s', array($value, $tab));
	}
	public static function set_icon($tab, $value) {
		DB::Execute('UPDATE recordbrowser_table_properties SET icon=%s WHERE tab=%s', array($value, $tab));
	}
	public static function set_access_callback($tab, $callback){
		if (is_array($callback)) $callback = implode('::',$callback);
		DB::Execute('UPDATE recordbrowser_table_properties SET access_callback=%s WHERE tab=%s', array($callback, $tab));
	}
	public static function get_sql_type($type) {
		switch ($type) {
			case 'checkbox': return '%d';
			case 'select': return '%d';
			case 'float': return '%f';
			case 'integer': return '%d';
			case 'date': return '%D';
			case 'timestamp': return '%T';
		}
		return '%s';
	}
	public static function set_record_properties( $tab, $id, $info = array()) {
		self::check_table_name($tab);
		foreach ($info as $k=>$v)
			switch ($k) {
				case 'created_on': 	DB::Execute('UPDATE '.$tab.'_data_1 SET created_on=%T WHERE id=%d', array($v, $id));
									break;
				case 'created_by': 	DB::Execute('UPDATE '.$tab.'_data_1 SET created_by=%d WHERE id=%d', array($v, $id));
									break;
			}
	}
	public static function new_record( $tab, $values = array()) {
		self::init($tab);
		$dpm = DB::GetOne('SELECT data_process_method FROM recordbrowser_table_properties WHERE tab=%s', array($tab));
		$method = '';
		if ($dpm!=='') {
			$for_processing = $values;
			foreach(self::$table_rows as $field=>$args)
				if ($args['type']==='multiselect') {
					if (!isset($for_processing[$args['id']]) || !$for_processing[$args['id']])
						$for_processing[$args['id']] = array();
//					else
//						if (!is_array($for_processing[$args['id']])) $for_processing[$args['id']] = self::decode_multi($for_processing[$args['id']]);
				} elseif (!isset($for_processing[$args['id']])) $for_processing[$args['id']] = '';
			$method = explode('::',$dpm);
			if (is_callable($method)) $values = call_user_func($method, $for_processing, 'add');
			else $dpm = '';
		}
		self::init($tab);
		DB::StartTrans();
		$fields = 'created_on,created_by,active';
		$fields_types = '%T,%d,%d';
		$vals = array(date('Y-m-d G:i:s'), Acl::get_user(), 1);
		foreach(self::$table_rows as $field => $args) {
			if (!isset($values[$args['id']]) || $values[$args['id']]==='') continue;
			if ($args['type']=='long text')
				$values[$args['id']] = Utils_BBCodeCommon::optimize($values[$args['id']]);
			if ($args['type']=='multiselect' && empty($values[$args['id']])) continue;
			if ($args['type']=='multiselect')
				$values[$args['id']] = self::encode_multi($values[$args['id']]);
			$fields_types .= ','.self::get_sql_type($args['type']);
			$fields .= ',f_'.$args['id'];
			$vals[] = $values[$args['id']];
		}
		DB::Execute('INSERT INTO '.$tab.'_data_1 ('.$fields.') VALUES ('.$fields_types.')',$vals);
		$id = DB::Insert_ID($tab.'_data_1', 'id');
		self::add_recent_entry($tab, Acl::get_user(), $id);
		DB::CompleteTrans();
		if (Base_User_SettingsCommon::get('Utils_RecordBrowser',$tab.'_auto_fav'))
			DB::Execute('INSERT INTO '.$tab.'_favorite (user_id, '.$tab.'_id) VALUES (%d, %d)', array(Acl::get_user(), $id));
		if (Base_User_SettingsCommon::get('Utils_RecordBrowser',$tab.'_auto_subs'))
			Utils_WatchdogCommon::subscribe($tab,$id);
		Utils_WatchdogCommon::new_event($tab,$id,'C');
		if ($dpm!=='') {
			self::init($tab);
			foreach(self::$table_rows as $field=>$args)
				if ($args['type']==='multiselect') {
					if (!isset($values[$args['id']])) $values[$args['id']] = array();
					elseif (!is_array($values[$args['id']]))
						$values[$args['id']] = self::decode_multi($values[$args['id']]);
				}
			$values['id'] = $id;
			call_user_func($method, $values, 'added');
		}
		return $id;
	}
	public static function update_record($tab,$id,$values,$all_fields = false, $date = null, $dont_notify = false) {
		DB::StartTrans();
		self::init($tab);
		$record = self::get_record($tab, $id, false);
		if (!is_array($record)) return false;
		$dpm = DB::GetOne('SELECT data_process_method FROM recordbrowser_table_properties WHERE tab=%s', array($tab));
		$method = '';
		if ($dpm!=='') {
			$process_method_args = $values;
			$process_method_args['id'] = $id;
			foreach ($record as $k=>$v)
				if (!isset($process_method_args[$k])) $process_method_args[$k] = $v;
			$method = explode('::',$dpm);
			if (is_callable($method)) $values = call_user_func($method, $process_method_args, 'edit');
		}
		$diff = array();
		self::init($tab);
		foreach(self::$table_rows as $field => $args){
			if ($args['id']=='id') continue;
			if (!isset($values[$args['id']])) {
				if ($all_fields) $values[$args['id']] = '';
				else continue;
			}
			if ($args['type']=='long text')
				$values[$args['id']] = Utils_BBCodeCommon::optimize($values[$args['id']]);
			if ($args['type']=='multiselect') {
				$array_diff = array_diff($record[$args['id']], $values[$args['id']]);
				if (empty($array_diff)) {
					$array_diff = array_diff($values[$args['id']], $record[$args['id']]);
					if (empty($array_diff)) continue;
				}
				$v = self::encode_multi($values[$args['id']]);
				$old = self::encode_multi($record[$args['id']]);
			} else {
				if ($record[$args['id']]==$values[$args['id']]) continue;
				$v = $values[$args['id']];
				$old = $record[$args['id']];
			}
			if ($v!=='') DB::Execute('UPDATE '.$tab.'_data_1 SET f_'.$args['id'].'='.self::get_sql_type($args['type']).' WHERE id=%d',array($v, $id));
			else DB::Execute('UPDATE '.$tab.'_data_1 SET f_'.$args['id'].'=NULL WHERE id=%d',array($id));
			$diff[$args['id']] = $old;
		}
		if (!$dont_notify && !empty($diff)) {
			DB::Execute('INSERT INTO '.$tab.'_edit_history(edited_on, edited_by, '.$tab.'_id) VALUES (%T,%d,%d)', array((($date==null)?date('Y-m-d G:i:s'):$date), Acl::get_user(), $id));
			$edit_id = DB::Insert_ID(''.$tab.'_edit_history','id');
			Utils_WatchdogCommon::new_event($tab,$id,'E_'.$edit_id);
			foreach($diff as $k=>$v) {
				if (!is_array($v)) $v = array($v);
				foreach($v as $c)
					DB::Execute('INSERT INTO '.$tab.'_edit_history_data(edit_id, field, old_value) VALUES (%d,%s,%s)', array($edit_id, $k, $c));
			}
		}
		DB::CompleteTrans();
	}
	public static function add_recent_entry($tab, $user_id ,$id){
		self::check_table_name($tab);
		DB::StartTrans();
		static $rec_size;
		if (!isset($rec_size)) $rec_size = DB::GetOne('SELECT recent FROM recordbrowser_table_properties WHERE tab=%s', array($tab));
		DB::Execute('DELETE FROM '.$tab.'_recent WHERE user_id = %d AND '.$tab.'_id = %d',
					array($user_id,
					$id));
		$ret = DB::SelectLimit('SELECT visited_on FROM '.$tab.'_recent WHERE user_id = %d ORDER BY visited_on DESC',
					$rec_size-1,
					-1,
					array($user_id));
		while($row_temp = $ret->FetchRow()) $row = $row_temp;
		if (isset($row)) {
			DB::Execute('DELETE FROM '.$tab.'_recent WHERE user_id = %d AND visited_on < %T',
						array($user_id,
						$row['visited_on']));
		}
		DB::Execute('INSERT INTO '.$tab.'_recent VALUES (%d, %d, %T)',
					array($id,
					$user_id,
					date('Y-m-d H:i:s')));
		DB::CompleteTrans();
	}
	public static function merge_crits($a = array(), $b = array()) {
		foreach ($b as $k=>$v){
			$nk = $k;
			while (isset($a[$nk])) $nk = '_'.$nk;
			$a[$nk] = $v;
		}
		return $a;
	}
	public static function build_query( $tab, $crits = null, $admin = false, $order = array()) {
		$key=$tab.'__'.serialize($crits).'__'.$admin.'__'.serialize($order);
		static $cache = array();
		self::init($tab, $admin);
		if (isset($cache[$key])) return $cache[$key];
		if (!$tab) return false;
		$having = '';
		$fields = '';
		$where = '';
		$final_tab = $tab.'_data_1 AS r';
		$vals = array();
		if (!$crits) $crits = array();
		$access = self::get_access($tab, 'browse_crits');
		if ($access===false) return array();
		elseif ($access!==true && is_array($access)) {
			$crits = self::merge_crits($crits, $access);
		}
		$iter = 0;
		self::init($tab, $admin);
		foreach($order as $k=>$v) {
			if (!is_string($k)) break;
 			if ($k[0]==':') $order[] = array('column'=>$k, 'order'=>$k, 'direction'=>$v);
 			else $order[] = array('column'=>self::$hash[$k], 'order'=>self::$hash[$k], 'direction'=>$v);
			unset($order[$k]);
		}
		$or_started = false;
		$sep = DB::qstr('::');
		foreach($crits as $k=>$v){
			self::init($tab, $admin);
			$negative = $noquotes = $or_start = $or = false;
			$operator = '=';
			while (($k[0]<'a' || $k[0]>'z') && ($k[0]<'A' || $k[0]>'Z') && $k[0]!=':') {
				if ($k[0]=='!') $negative = true;
				if ($k[0]=='"') $noquotes = true;
				if ($k[0]=='(') $or_start = true;
				if ($k[0]=='|') $or = true;
				if ($k[0]=='<') $operator = '<';
				if ($k[0]=='>') $operator = '>';
				if ($k[0]=='~') $operator = 'LIKE';
				if ($k[1]=='=' && $operator!='LIKE') {
					$operator .= '=';
					$k = substr($k, 2);
				} else $k = substr($k, 1);
				if (!isset($k[0])) trigger_error('Invalid criteria in build query: missing word. Crits:'.print_r($crits,true), E_USER_ERROR);
			}
			$or |= $or_start;
//			if ($k[0]!=':' && $k!=='id' && !isset(self::$table_rows[$k]) && !isset(self::$hash[$k])) trigger_error('!'.$k.'!'.$tab.print_r($crits,true).print_r(self::$hash,true));
			if ($k[0]!=':' && $k!=='id' && !isset(self::$table_rows[$k]) && !isset(self::$table_rows[self::$hash[$k]])) continue; //failsafe
			if ($or) {
				if ($or_start && $or_started) {
					$having .= ')';
					$or_started = false;
				}
				if (!$or_started) $having .= ' AND (';
				else $having .= ' OR ';
				$or_started = true;
			} else {
				if ($or_started) $having .= ')';
				$or_started = false;
				$having .= ' AND ';
			}
			if ($k[0]==':') {
				switch ($k) {
					case ':Fav'	: 	$final_tab = '('.$final_tab.') LEFT JOIN '.$tab.'_favorite AS fav ON fav.'.$tab.'_id=r.id AND fav.user_id='.Acl::get_user();
									$having .= ' fav.user_id IS NOT NULL'; 
									break;
					case ':Recent'	: 	$final_tab = '('.$final_tab.') LEFT JOIN '.$tab.'_recent AS rec ON rec.'.$tab.'_id=r.id AND rec.user_id='.Acl::get_user();
										$having .= ' rec.user_id IS NOT NULL'; 
										break;
					case ':Created_on'	:
							$inj = '';
							if(is_array($v))
								$inj = $v[0].DB::qstr($v[1]);
							elseif(is_string($v))
								$inj = DB::qstr($v);
							if($inj)
								$having .= ' created_on '.$inj;
							break;
					case ':Created_by'	:
							$having .= ' created_by = '.$v;
							break;
					case ':Edited_on'	:
							$inj = '';
							if(is_array($v))
								$inj = $v[0].DB::qstr($v[1]);
							elseif(is_string($v))
								$inj = DB::qstr($v);
							if($inj)
								$having .= ' (((SELECT MAX(edited_on) FROM '.$tab.'_edit_history WHERE '.$tab.'_id=r.id) '.$inj.') OR'.
										'((SELECT MAX(edited_on) FROM '.$tab.'_edit_history WHERE '.$tab.'_id=r.id) IS NULL AND created_on '.$inj.'))';
							break;
					default:
						if (substr($k,0,4)==':Ref')	{
							$params = explode(':', $k);
							$ref = $params[2];
							if (is_array(self::$table_rows[self::$hash[$ref]]['param'])) {
								if (isset(self::$table_rows[self::$hash[$ref]]['param']['array_id']))
									self::$table_rows[self::$hash[$ref]]['param'] = self::$table_rows[self::$hash[$ref]]['param']['array_id'];
								else
									self::$table_rows[self::$hash[$ref]]['param'] = self::$table_rows[self::$hash[$ref]]['param'][1];
							}
							$param = explode(';', self::$table_rows[self::$hash[$ref]]['param']);
							$param = explode('::',$param[0]);
							$cols2 = null;
							if (isset($param[1])) {
								$tab2 = $param[0];
								$cols2 = $param[1];
							} else $cols = $param[0];
							if ($params[1]=='RefCD' || $tab2=='__COMMON__') {
								$ret = Utils_CommonDataCommon::get_translated_array($cols2!==null?$cols2:$cols);
								$allowed_cd = array();
								foreach ($ret as $kkk=>$vvv)
									foreach ($v as $w) if ($w!='') {
										if (stripos($vvv,$w)!==false) {
											$allowed_cd[] = DB::qstr($kkk);
											break;
										}
									}
								if (empty($allowed_cd)) {
									$having .= $negative?'true':'false';
									break;
								}
								$having_cd = array();
								foreach ($allowed_cd as $vvv)
									$having_cd[] = 'r.f_'.$ref.' LIKE '.DB::Concat(DB::qstr('%'),$vvv,DB::qstr('%'));
								$having .= '('.implode(' OR ',$having_cd).')';
								break;
							}
							self::init($tab2);
							$det = explode('/', $cols2);
							$cols2 = explode('|', $det[0]);
							foreach($cols2 as $j=>$w) $cols2[$j] = self::$table_rows[$cols2[$j]]['id'];
							self::init($tab);
							if (!is_array($v)) $v = array($v);
							$poss_vals = '';
							foreach ($v as $w) {
								if ($w==='') {
									$poss_vals .= 'OR f_'.implode(' IS NULL OR f_', $cols2);
									break;
								} else {
									if (!$noquotes) $w = DB::qstr($w);
									$poss_vals .= ' OR f_'.implode(' LIKE '.$w.' OR f_', $cols2).' LIKE '.$w;
									// TODO: possible inj
								}
							}
							$allowed_cd = DB::GetAssoc('SELECT id, id FROM '.$tab2.'_data_1 WHERE false '.$poss_vals);

							if (empty($allowed_cd)) {
								$having .= $negative?'true':'false';
								break;
							}

							$having_cd = array();
							if ($negative) $having .= 'NOT (';
							if (isset($det[1])) $ref = self::$table_rows[$det[1]]['id'];
							$is_multiselect = (self::$table_rows[self::$hash[$ref]]['type']=='multiselect');
							foreach ($allowed_cd as $vvv) {
								if ($is_multiselect) $www = DB::Concat(DB::qstr('%'),DB::qstr('\_\_'.$vvv.'\_\_'),DB::qstr('%'));
								else $www = DB::qstr($vvv);
								$having_cd[] = 'r.f_'.$ref.' LIKE '.$www;
							}
							$having .= '('.implode(' OR ',$having_cd).')';
						} else trigger_error('Unknow paramter given to get_records criteria: '.$k, E_USER_ERROR);
				}
			} else {
				if ($k == 'id') {
					if (!is_array($v)) $v = array($v);
					$having .= '('.($negative?'true':'false');
					foreach($v as $w) {
						if (!$noquotes) $w = DB::qstr($w);
						$having .= ' '.($negative?'AND':'OR').($negative?' NOT':'').' id '.$operator.' '.$w;
					}
					$having .= ')';
				} else {
					if (!is_array($v)) $v = array($v);
					if ($negative) $having .= 'NOT ';
					$having .= '(false';
					foreach($v as $w) {
						if ($w==='') {
							$having .= ' OR r.f_'.$k.' IS NULL OR r.f_'.$k.'=\'\'';
						} else {
							if (isset(self::$hash[$k])) {
								$f = self::$hash[$k];
								$key = $k;
							} elseif (isset(self::$table_rows[$k])) {
								$f = $k;
								$key = self::$table_rows[$k]['id'];
							} else trigger_error('In table "'.$tab.'" - unknow column "'.$k.'" in criteria "'.print_r($crits,true).'". Available columns are: "'.print_r(self::$table_rows,true).'"', E_USER_ERROR);
							if (self::$table_rows[$f]['type']=='multiselect') {
								$operator = 'LIKE';
								$param = explode('::',self::$table_rows[$f]['param']);
								if ($param[0]=='__COMMON__')$tail = '';
								else $tail = '\_\_';
								$w = DB::Concat(DB::qstr('%'),DB::qstr('\_\_'.$w.$tail),DB::qstr('%'));
							}
							elseif (!$noquotes) $w = DB::qstr($w);
							$having .= ' OR (r.f_'.$key.' '.$operator.' '.$w.' ';
							if ($operator=='<') $having .= 'OR r.f_'.$key.' IS NULL)';
							else $having .= 'AND r.f_'.$key.' IS NOT NULL)';
						}
					}
					$having .= ')';
				}
			}
		}
		if ($or_started) $having .= ')';
		$orderby = array();
		foreach($order as $v){
			if ($v['order'][0]!=':' && !isset(self::$table_rows[$v['order']])) continue; //failsafe
			if ($v['order'][0]==':') {
				switch ($v['order']) {
					case ':Fav'	:
						$orderby[] = ' (SELECT COUNT(*) FROM '.$tab.'_favorite WHERE '.$tab.'_id=r.id AND user_id=%d) '.$v['direction'];
						$vals[]=Acl::get_user();
						break;
					case ':Visited_on'	:
						$orderby[] = ' (SELECT visited_on FROM '.$tab.'_recent WHERE '.$tab.'_id=r.id AND user_id=%d) '.$v['direction'];
						$vals[]=Acl::get_user();
						break;
					case ':Edited_on'	:
						$orderby[] = ' (CASE WHEN (SELECT MAX(edited_on) FROM '.$tab.'_edit_history WHERE '.$tab.'_id=r.id) IS NOT NULL THEN (SELECT MAX(edited_on) FROM '.$tab.'_edit_history WHERE '.$tab.'_id=r.id) ELSE created_on END) '.$v['direction'];
						break;
					default		: trigger_error('Unknow paramter given to get_records order: '.$k, E_USER_ERROR);
				}
			} else {
				self::init($tab);
				if (is_array(self::$table_rows[$v['order']]['param']))
					$param = explode(';', self::$table_rows[$v['order']]['param']['array_id']);
				else
					$param = explode(';', self::$table_rows[$v['order']]['param']);
				$param = explode('::',$param[0]);
				if (isset($param[1]) && $param[1]!='') {
					if (self::$table_rows[$v['order']]['type']!='commondata') {
						if (!isset($param[1])) $cols = $param[0];
						else if ($param[0]!='__COMMON__') {
							$tab2 = $param[0];
							$cols2 = $param[1];
							$cols2 = explode('|', $cols2);
							$cols2 = $cols2[0];
							$cols2 = explode('/', $cols2);
							if (isset($cols2[1])) $data_col = self::$table_rows[$cols2[1]]['id']; else $data_col = self::$table_rows[$v['order']]['id'];
							$cols2 = $cols2[0];
							$val = '(SELECT rdt.f_'.strtolower(str_replace(' ','_',$cols2)).' FROM '.$tab.'_data_1 AS rd LEFT JOIN '.$tab2.'_data_1 AS rdt ON rdt.id=rd.f_'.$data_col.' WHERE r.id=rd.id)';
							$orderby[] = ' '.$val.' '.$v['direction'];
							$iter++;
							continue;
						}
					}
				}
				$val = 'f_'.self::$table_rows[$v['order']]['id'];
				$orderby[] = ' '.$val.' '.$v['direction'];
				$iter++;
			}
		}
		if (!empty($orderby)) $orderby = ' ORDER BY'.implode(', ',$orderby);
		else $orderby = '';
		$final_tab = str_replace('('.$tab.'_data_1 AS r'.')',$tab.'_data_1 AS r',$final_tab);
		$default_filter = (class_exists('Utils_RecordBrowser') && isset(Utils_RecordBrowser::$admin_filter))?Utils_RecordBrowser::$admin_filter:'';
		$ret = array('sql'=>' '.$final_tab.' WHERE true'.($admin?$default_filter:' AND active=1').$where.$having.$orderby,'vals'=>$vals);
		return $cache[$key] = $ret;
	}
	public static function get_records_count( $tab, $crits = null, $admin = false) {
		$par = self::build_query($tab, $crits, $admin);
		if (empty($par) || !$par) return 0;
		return DB::GetOne('SELECT COUNT(*) FROM'.$par['sql'], $par['vals']);
	}
	public static function get_next_and_prev_record( $tab, $crits, $order, $id, $last = null) {
		$par = self::build_query($tab, $crits, false, $order);
		if (empty($par) || !$par) return null;
		if ($last===null || is_array($last)) {
			/* Just failsafe - should not happen */
			$ret = DB::GetCol('SELECT id FROM'.$par['sql'], $par['vals']);
			if ($ret===false || $ret===null) return null;
			$k = array_search($id,$ret);
			return array(	'next'=>isset($ret[$k+1])?$ret[$k+1]:null,
							'curr'=>$k,
							'prev'=>isset($ret[$k-1])?$ret[$k-1]:null);
		} else {
			$r = DB::SelectLimit('SELECT id FROM'.$par['sql'],3,($last!=0?$last-1:$last), $par['vals']);
			$ret = array();
			while ($row=$r->FetchRow()) {
				$ret[] = $row['id'];
			}
			if ($ret===false || $ret===null) return null;
			if ($last===0) $ret = array(0=>null, 2=>isset($ret[1])?$ret[1]:null);
			return array(	'next'=>isset($ret[2])?$ret[2]:null,
							'curr'=>$last,
							'prev'=>isset($ret[0])?$ret[0]:null);
		}
	}
	public static function get_records( $tab, $crits = array(), $cols = array(), $order = array(), $limit = array(), $admin = false) {
		if (!$tab) return false;
		if (is_numeric($limit)) {
			$limit = array('numrows'=>$limit,'offset'=>0);
		} else {			
			if (!isset($limit['offset'])) $limit['offset'] = 0;
			if (!isset($limit['numrows'])) $limit['numrows'] = -1;
		}
		if (!$order) $order = array();
		$fields = '*';
		self::init($tab);
		if (!empty($cols)) {
			$cleancols = array();
			foreach ($cols as $v) {
				$val = (isset(self::$table_rows[$v])?self::$table_rows[$v]['id']:$v);
				if ($val!='id') $cleancols[] = $val;
			}
			if (!empty($cleancols)) $fields = 'id,active,created_by,created_on,f_'.implode(',f_',$cleancols);
		}
		if (count($crits)==1 && isset($crits['id']) && empty($order)) {
			if (empty($crits['id'])) return array();
			if (!is_array($crits['id'])) $crits['id'] = array($crits['id']);
			$first = true;
			$where = '';
			$vals = array();
			foreach($crits['id'] as $v) {
				if ($first) $first = false;
				else $where .= ', ';
				$where .= '%d';
				$vals[] = $v;
			}
			$ret = DB::SelectLimit('SELECT '.$fields.' FROM '.$tab.'_data_1 WHERE id IN ('.$where.')', $limit['numrows'], $limit['offset'], $vals);
		} else {
			$par = self::build_query($tab, $crits, $admin, $order);
			if (empty($par)) return array();
			$ret = DB::SelectLimit('SELECT '.$fields.' FROM'.$par['sql'], $limit['numrows'], $limit['offset'], $par['vals']);
		}
		$records = array();
		if (!empty($cols)) {
			foreach($cols as $k=>$v) {
				if (isset(self::$hash[$v])) $cols[$k] = self::$table_rows[self::$hash[$v]];
				elseif (isset(self::$table_rows[$v])) $cols[$k] = self::$table_rows[$v];
				else unset($cols[$k]);
			} 
		} else
			$cols = self::$table_rows;
		while ($row = $ret->FetchRow()) {
			$r = array(	'id'=>$row['id'],
						'active'=>$row['active'],
						'created_by'=>$row['created_by'],
						'created_on'=>$row['created_on']);
			foreach($cols as $v){
				if (isset($row['f_'.$v['id']])) {
					if ($v['type']=='multiselect') $r[$v['id']] = self::decode_multi($row['f_'.$v['id']]); 
					elseif ($v['type']!=='long text') $r[$v['id']] = htmlspecialchars($row['f_'.$v['id']]);
					else $r[$v['id']] = $row['f_'.$v['id']];
				} else {
					if ($v['type']=='multiselect') $r[$v['id']] = array(); 
					else $r[$v['id']] = '';
				}
			}
			$records[$row['id']] = $r;
		}
		return $records;
	}
	public static function check_record_against_crits($tab, $id, $crits) {
		if ($crits===true || empty($crits)) return true;
		static $cache = array();
		if (is_numeric($id)) $r = self::get_record($tab, $id);
		else $r = $id;
//		if (isset($cache[$tab.'__'.$id])) return $cache[$tab.'__'.$id];
//		$r = self::get_record($tab, $id);
		$or_started = false;
		$or_result = false;
		foreach ($crits as $k=>$v) {
			$negative = $noquotes = $or_start = $or = false;
			$operator = '==';
			while (($k[0]<'a' || $k[0]>'z') && ($k[0]<'A' || $k[0]>'Z')) {
				if ($k[0]=='!') $negative = true;
				if ($k[0]=='"') $noquotes = true;
				if ($k[0]=='(') $or_start = true;
				if ($k[0]=='|') $or = true;
				if ($k[0]=='<') $operator = '<';
				if ($k[0]=='>') $operator = '>';
				if ($k[1]=='=' && $operator!='==') {
					$operator .= '=';
					$k = substr($k, 2);
				} else $k = substr($k, 1);
				if (!isset($k[0])) trigger_error('Invalid criteria in build query: missing word.', E_USER_ERROR);
			}
			$or |= $or_start;
			if ($or) {
				if ($or_start && $or_started) {
					if (!$or_result) return $cache[$tab.'__'.$id] = false;
					$or_result = false;
				}
				if (!$or_started) $or_result = false;
				$or_started = true;
			} else {
				if ($or_started && !$or_result) return $cache[$tab.'__'.$id] = false;
				$or_started = false;
			}
			$result = false;
			$k = strtolower($k);
			if (!isset($r[$k])) trigger_error($k.'<br><br>'.print_r($r,true), E_USER_ERROR);
			if (is_array($r[$k])) $result = in_array($v, $r[$k]);
			else switch ($operator) {
				case '>': $result = ($r[$k] > $v); break;
				case '>=': $result = ($r[$k] >= $v); break;
				case '<': $result = ($r[$k] < $v); break;
				case '<=': $result = ($r[$k] <= $v); break;
				case '==': $result = stristr((string)$r[$k],(string)$v);
			}
			if ($negative) $result = !$result;
			if ($or_started) $or_result |= $result;
			else if (!$result) return $cache[$tab.'__'.$id] = false;
		}
		if ($or_started && !$or_result) return $cache[$tab.'__'.$id] = false;
		return $cache[$tab.'__'.$id] = true;
	}
	public static function get_access($tab, $action, $record=null){
		if (self::$admin_access && Base_AclCommon::i_am_admin()) {
			$ret = true;
		} else {
			static $cache = array();
			if (!isset($cache[$tab])) $cache[$tab] = $access_callback = explode('::', DB::GetOne('SELECT access_callback FROM recordbrowser_table_properties WHERE tab=%s', array($tab)));
			else $access_callback = $cache[$tab];
			if ($access_callback === '' || !is_callable($access_callback)) {
				$ret = true;
			} else {
				$ret = call_user_func($access_callback, $action, $record);
				if ($action==='delete' && $ret) $ret = call_user_func($access_callback, 'edit', $record);
			}
		}
		if ($action!=='browse_crits' && $action!=='delete') {
			self::init($tab);
			if ($ret===false) return false;
			if ($ret===true) $ret = array();
			foreach (self::$table_rows as $field=>$args)
				if (!isset($ret[$args['id']])) $ret[$args['id']] = true;
		}
		return $ret;
	}
	public static function get_record_info($tab, $id) {
		self::check_table_name($tab);
		$created = DB::GetRow('SELECT created_on, created_by FROM '.$tab.'_data_1 WHERE id=%d', array($id));
		$edited = DB::GetRow('SELECT edited_on, edited_by FROM '.$tab.'_edit_history WHERE '.$tab.'_id=%d ORDER BY edited_on DESC', array($id));
		if (!isset($edited['edited_on'])) $edited['edited_on'] = null;
		if (!isset($edited['edited_by'])) $edited['edited_by'] = null;
		if (!isset($created['created_on'])) trigger_error('There is no such record as '.$id.' in table '.$tab, E_USER_ERROR);
		return array(	'created_on'=>$created['created_on'],'created_by'=>$created['created_by'],
						'edited_on'=>$edited['edited_on'],'edited_by'=>$edited['edited_by']);
	}
	public static function get_html_record_info($tab, $id){
		if (is_numeric($id))$info = Utils_RecordBrowserCommon::get_record_info($tab, $id);
		else $info = $id;
		// If CRM Contacts module is installed get user contact
		if (ModuleManager::is_installed('CRM_Contacts')>=0)
			return CRM_ContactsCommon::get_html_record_info($info['created_by'],$info['created_on'],$info['edited_by'],$info['edited_on']);

		// If CRM Module is not installed get user login only
		$created_by = Base_UserCommon::get_user_login($info['created_by']);
		$edited_by = Base_UserCommon::get_user_login($info['edited_by']);
		$htmlinfo=array(
					'Created by:'=>$created_by,			
					'Created on:'=>Base_RegionalSettingsCommon::time2reg($info['created_on'])
						);
		if ($info['edited_by']!=null) {
			$htmlinfo=$htmlinfo+array(
					'Edited by:'=>$edited_by,		
					'Edited on:'=>Base_RegionalSettingsCommon::time2reg($info['edited_on'])
						);
		}

		return	Utils_TooltipCommon::format_info_tooltip($htmlinfo,'Utils_RecordBrowser');
	}
	public static function get_record($tab, $id, $htmlspecialchars=true) {
		if (!is_numeric($id)) return null;
		self::init($tab);
		if (isset($id)) {
			self::check_table_name($tab);
			$row = DB::GetRow('SELECT * FROM '.$tab.'_data_1 WHERE id=%d', array($id));
			$record = array('id'=>$id);
			if (!isset($row['active'])) return null; 
			foreach(array('active','created_by','created_on') as $v)
				$record[$v] = $row[$v];
			foreach(self::$table_rows as $field=>$args) {
				if ($args['type']==='multiselect') {
					if (!isset($row['f_'.$args['id']])) $r = array();
					else $r = self::decode_multi($row['f_'.$args['id']]);
					$record[$args['id']] = $r;
				} else {
					$record[$args['id']] = (isset($row['f_'.$args['id']])?$row['f_'.$args['id']]:'');
					if ($htmlspecialchars && $args['type']!=='long text') $record[$args['id']] = htmlspecialchars($record[$args['id']]);
				}
			}
			return $record;
		} else {
			return null;
		}
	}
	public static function set_active($tab, $id, $state){
		self::check_table_name($tab);
		DB::StartTrans();
		DB::Execute('UPDATE '.$tab.'_data_1 SET active=%d WHERE id=%d',array($state?1:0,$id));
		DB::Execute('INSERT INTO '.$tab.'_edit_history(edited_on, edited_by, '.$tab.'_id) VALUES (%T,%d,%d)', array(date('Y-m-d G:i:s'), Acl::get_user(), $id));
		$edit_id = DB::Insert_ID($tab.'_edit_history','id');
		DB::Execute('INSERT INTO '.$tab.'_edit_history_data(edit_id, field, old_value) VALUES (%d,%s,%s)', array($edit_id, 'id', ($state?'RESTORED':'DELETED')));
		DB::CompleteTrans();
		$dpm = DB::GetOne('SELECT data_process_method FROM recordbrowser_table_properties WHERE tab=%s', array($tab));
		if ($dpm!=='') {
			$method = explode('::',$dpm);
			if (is_callable($method)) call_user_func($method, self::get_record($tab, $id), $state?'restore':'delete');
		}
	}
	public static function delete_record($tab, $id, $perma=false) {
		if (!$perma) self::set_active($tab, $id, false);
		else {
			self::check_table_name($tab);
			DB::Execute('DELETE FROM '.$tab.'_data_1 WHERE id=%d', array($id));
		}
	}
	public static function restore_record($tab, $id) {
		self::set_active($tab, $id, true);
	}
	public static function no_wrap($s) {
		$content_no_wrap = $s;
		preg_match_all('/>([^\<\>]*)</', $s, $match);
		foreach($match[1] as $v) $content_no_wrap = str_replace($v, str_replace(' ','&nbsp;', $v), $content_no_wrap);
		return $content_no_wrap;
	}
	public static function get_new_record_href($tab, $def, $id='none'){
		self::check_table_name($tab);
		$x = ModuleManager::get_instance('/Base_Box|0');
		if (!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		if (Utils_RecordBrowser::$clone_result!==null) {
			if (is_numeric(Utils_RecordBrowser::$clone_result)) $x->push_main('Utils/RecordBrowser','view_entry',array('view', Utils_RecordBrowser::$clone_result), array(Utils_RecordBrowser::$clone_tab));
			Utils_RecordBrowser::$clone_result = null;
		}
		if (isset($_REQUEST['__add_record_to_RB_table']) &&
			isset($_REQUEST['__add_record_id']) &&
			($tab==$_REQUEST['__add_record_to_RB_table']) &&
			($id==$_REQUEST['__add_record_id'])) {
			unset($_REQUEST['__add_record_to_RB_table']);
			unset($_REQUEST['__add_record_id']);
			$x->push_main('Utils/RecordBrowser','view_entry',array('add', null, $def), array($tab));
			return array();
		}
		return array('__add_record_to_RB_table'=>$tab, '__add_record_id'=>$id);
	}
	public static function create_new_record_href($tab, $def, $id='none'){
		return Module::create_href(self::get_new_record_href($tab,$def, $id));
	}
	public static function get_record_href_array($tab, $id, $action='view'){
		self::check_table_name($tab);
		if (isset($_REQUEST['__jump_to_RB_table']) &&
			($tab==$_REQUEST['__jump_to_RB_table']) &&
			($id==$_REQUEST['__jump_to_RB_record']) &&
			($action==$_REQUEST['__jump_to_RB_action'])) {
			unset($_REQUEST['__jump_to_RB_record']);
			unset($_REQUEST['__jump_to_RB_table']);
			unset($_REQUEST['__jump_to_RB_action']);
			$x = ModuleManager::get_instance('/Base_Box|0');
			if (!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
			$x->push_main('Utils/RecordBrowser','view_entry',array($action, $id),array($tab));
			return array();
		}
		return array('__jump_to_RB_table'=>$tab, '__jump_to_RB_record'=>$id, '__jump_to_RB_action'=>$action);
	}
	public static function create_record_href($tab, $id, $action='view'){
		if(MOBILE_DEVICE) {
			$cap = DB::GetOne('SELECT caption FROM recordbrowser_table_properties WHERE tab=%s',array($tab));
			return mobile_stack_href(array('Utils_RecordBrowserCommon','mobile_rb_view'),array($tab,$id),$cap);
		}
		return Module::create_href(self::get_record_href_array($tab,$id,$action));
	}
	public static function record_link_open_tag($tab, $id, $nolink=false, $action='view'){
		self::check_table_name($tab);
		$ret = '';
		if (!is_numeric($id)) {
			return self::$del_or_a = '';
		}
		if (class_exists('Utils_RecordBrowser') &&
			isset(Utils_RecordBrowser::$access_override) &&
			Utils_RecordBrowser::$access_override['tab']==$tab &&
			Utils_RecordBrowser::$access_override['id']==$id) {
			self::$del_or_a = '</a>';
			if (!$nolink) $ret = '<a '.self::create_record_href($tab, $id, $action).'>';
			else self::$del_or_a = '';
		} else {
			if (!DB::GetOne('SELECT active FROM '.$tab.'_data_1 WHERE id=%d',array($id))) {
				self::$del_or_a = '</del>';
				$ret = '<del '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('Utils_RecordBrowser','This record was deleted from the system, please edit current record or contact system administrator')).'>';
			} elseif (!$nolink && !self::get_access($tab, 'view', self::get_record($tab, $id))) {
				self::$del_or_a = '</span>';
				$ret = '<span '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('Utils_RecordBrowser','You don\'t have permission to view this record.')).'>';
			} else {
				self::$del_or_a = '</a>';
				if (!$nolink) $ret = '<a '.self::create_record_href($tab, $id, $action).'>';
				else self::$del_or_a = '';
			}
		}
		return $ret;
	}
	public static function record_link_close_tag(){
		return self::$del_or_a;
	}
	public static function create_linked_label($tab, $col, $id, $nolink=false){
		if (!is_numeric($id)) return '';
		self::init($tab);
		if (isset(self::$table_rows[$col])) $col = self::$table_rows[$col]['id'];
		elseif (!isset(self::$hash[$col])) trigger_error('Unknown column name: '.$col,E_USER_ERROR);
		$label = DB::GetOne('SELECT f_'.$col.' FROM '.$tab.'_data_1 WHERE id=%d', array($id));
		$ret = self::record_link_open_tag($tab, $id, $nolink).$label.self::record_link_close_tag();
		return $ret;
	}
	public static function create_linked_label_r($tab, $col, $r, $nolink=false){
		$id = $r['id'];
		if (!is_numeric($id)) return '';
		self::init($tab);
		if (isset(self::$table_rows[$col])) $col = self::$table_rows[$col]['id'];
		elseif (!isset(self::$hash[$col])) trigger_error('Unknown column name: '.$col,E_USER_ERROR);
		$label = $r[$col];
		$ret = self::record_link_open_tag($tab, $id, $nolink).$label.self::record_link_close_tag();
		return $ret;
	}
	public static function record_bbcode($tab, $fields, $text, $param, $opt) {
		if (!is_numeric($param)) {
			$parts = explode(' ', $text);
			$crits = array();
			foreach ($parts as $k=>$v) {
				$v = DB::Concat(DB::qstr('%'),DB::qstr($v),DB::qstr('%'));;
				$chr = '(';
				foreach ($fields as $f) {
					$crits[$chr.str_repeat('_', $k).'"'.$f] = $v;
					$chr='|';
				}
			}
			$rec = Utils_RecordBrowserCommon::get_records($tab, $crits, array(), array(), 1);
			if (is_array($rec) && !empty($rec)) $rec = array_shift($rec);
			else {
				$crits = array();
				foreach ($parts as $k=>$v) {
					$v = DB::Concat(DB::qstr('%'),DB::qstr($v),DB::qstr('%'));;
					$chr = '(';
					foreach ($fields as $f) {
						$crits[$chr.str_repeat('_', $k).'~"'.$f] = $v;
						$chr='|';
					}
				}
				$rec = Utils_RecordBrowserCommon::get_records($tab, $crits, array(), array(), 1);
				if (is_array($rec)) $rec = array_shift($rec);
				else $rec = null;
			}
		} else {
			$rec = Utils_RecordBrowserCommon::get_record($tab, $param);
		}
		if ($opt) {
			if (!$rec) return null;
			return Utils_BBCodeCommon::create_bbcode(null, $rec['id'], $text);
		}
		if ($rec) {
			return Utils_RecordBrowserCommon::record_link_open_tag($tab, $rec['id']).$text.Utils_RecordBrowserCommon::record_link_close_tag();
		}
		return Utils_BBCodeCommon::create_bbcode(null, $param, $text, Base_LangCommon::ts('Utils_RecordBrowser','Record not found'));
	}
	public static function applet_settings($some_more = array()) {
		$some_more = array_merge($some_more,array(
			array('label'=>'Actions','name'=>'actions_header','type'=>'header'),
			array('label'=>'Info','name'=>'actions_info','type'=>'checkbox','default'=>true),
			array('label'=>'View','name'=>'actions_view','type'=>'checkbox','default'=>false),
			array('label'=>'Edit','name'=>'actions_edit','type'=>'checkbox','default'=>true),
			array('label'=>'Delete','name'=>'actions_delete','type'=>'checkbox','default'=>false),
			array('label'=>'View edit history','name'=>'actions_history','type'=>'checkbox','default'=>false),
//			array('label'=>'Subscription status','name'=>'actions_subscription','type'=>'checkbox','default'=>false),
//			array('label'=>'Favs','name'=>'actions_fav','type'=>'checkbox','default'=>false)
		));
		return $some_more;
	}
	public static function watchdog_label($tab, $cat, $rid, $events = array(), $label = null, $details = true) {
		$ret = array('category'=>$cat);
		if ($rid!==null) {
			$r = self::get_record($tab, $rid);
			if ($r===null) return null;
			if (is_array($label)) $label = call_user_func($label, $r, true);
			else $label = $r[$label];
			$ret['title'] = Utils_RecordBrowserCommon::record_link_open_tag($tab, $rid).$label;
			$close = Utils_RecordBrowserCommon::record_link_close_tag();
			if ($close!='</a>') return null;
			$ret['title'] .= $close;
			$ret['view_href'] = Utils_RecordBrowserCommon::create_record_href($tab, $rid);
			$events_display = array();
			$events = array_reverse($events);
			foreach ($events as $v) {
				$param = explode('_', $v);
				switch ($param[0]) {
					case 'C': 	$event_display = Base_LangCommon::ts('Utils_RecordBrowser','<b>Record created by</b> %s<b>, on</b> %s', array(Base_UserCommon::get_user_login($r['created_by']), Base_RegionalSettingsCommon::time2reg($r['created_on'])));
								break;
					case 'E': 	$edit_info = DB::GetRow('SELECT * FROM '.$tab.'_edit_history WHERE id=%d',array($param[1]));
								$event_display = 'Error, Invalid event: '.$param;
								if (!$edit_info) continue;

								$event_display = Base_LangCommon::ts('Utils_RecordBrowser','<b>Record edited by</b> %s<b>, on</b> %s', array(Base_UserCommon::get_user_login($edit_info['edited_by']), Base_RegionalSettingsCommon::time2reg($edit_info['edited_on'])));
								if (!$details) break;
								$edit_details = DB::GetAssoc('SELECT field, old_value FROM '.$tab.'_edit_history_data WHERE edit_id=%d',array($param[1]));
								$event_display .= '<table border="0"><tr><td><b>'.Base_LangCommon::ts('Utils_RecordBrowser','Field').'</b></td><td><b>'.Base_LangCommon::ts('Utils_RecordBrowser','Old value').'</b></td><td><b>'.Base_LangCommon::ts('Utils_RecordBrowser','New value').'</b></td></tr>';
								$r2 = $r;
								self::init($tab);
								foreach ($edit_details as $k=>$v) {
									$k = strtolower(str_replace(' ','_',$k)); // failsafe
									if (self::$table_rows[self::$hash[$k]]['type']=='multiselect') $v = $edit_details[$k] = self::decode_multi($v);
									$r2[$k] = $v;
								}
								foreach ($edit_details as $k=>$v) {
									$access = self::get_access($tab,'view',$r);
									$k = strtolower(str_replace(' ','_',$k)); // failsafe
									if (!$access[$k]) continue;
									self::init($tab);
									$field = self::$hash[$k];
									$event_display .= '<tr valign="top"><td><b>'.$field.'</b></td>';
									$params = self::$table_rows[$field];
									$event_display .= 	'<td>'.self::get_val($tab, $field, $r2, true, $params).'</td>'.
														'<td>'.self::get_val($tab, $field, $r, true, $params).'</td></tr>';
								}
								$r = $r2;
								$event_display .= '</table>';
								break;
								
					case 'N': 	$event_display = false;
								switch($param[1]) {
									case '+':
										$action = 'added';
										break;
									case '~':
										$action = 'edited';
										break;
									case '-':
										$action = 'deleted';
										break;
									default:
										$event_display = Base_LangCommon::ts('Utils_RecordBrowser',$param[1]);
								}
								if($event_display===false)
									$event_display = Base_LangCommon::ts('Utils_RecordBrowser','<b>Note '.$action.'<b>');
								break;								
					default: 	$event_display = '<b>'.Base_LangCommon::ts('Utils_RecordBrowser',$v).'</b>';	
				}
				$events_display[] = $event_display;
			}
			$ret['events'] = implode($details?'<hr>':'<br>',array_reverse($events_display));
		}
		return $ret;
	}
	public static function get_tables($tab){
		return array(	$tab.'_callback',
						$tab.'_recent',
						$tab.'_favorite',
						$tab.'_edit_history_data',
						$tab.'_edit_history',
						$tab.'_field',
						$tab.'_data_1');
	}
	
	public static function applet_new_record_button($tab, $defaults = array()) {
		return '<a '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('Utils_RecordBrowser', 'New record')).' '.Utils_RecordBrowserCommon::create_new_record_href($tab,$defaults).'><img src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','add.png').'" border="0"></a>';
	}
	
	public static function get_calculated_id($tab, $field, $id) {
		return $tab.'__'.$field.'___'.$id;
	}

	public static function check_for_jump() {
		if (isset($_REQUEST['__jump_to_RB_table']) &&
			isset($_REQUEST['__jump_to_RB_record'])) {
			$tab = $_REQUEST['__jump_to_RB_table'];
			$id = $_REQUEST['__jump_to_RB_record'];
			$action = $_REQUEST['__jump_to_RB_action'];
			if (!is_numeric($id)) trigger_error('Critical failure - invalid id, requested record with id "'.serialize($id).'" from table "'.serialize($tab).'".',E_USER_ERROR);
			Utils_RecordBrowserCommon::check_table_name($tab);
			unset($_REQUEST['__jump_to_RB_record']);
			unset($_REQUEST['__jump_to_RB_table']);
			$x = ModuleManager::get_instance('/Base_Box|0');
			if (!self::get_access($tab,'browse')) return false;
			if (!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
			$x->push_main('Utils/RecordBrowser','view_entry',array($action, $id),array($tab));
			return true;
		}
		return false;
	}

	public function cut_string($str, $len, $tooltip=true, &$cut=null) {
		if ($len==-1) return $str;
		$ret = '';
		$strings = explode('<br>',$str);
		foreach ($strings as $str) {
			if ($ret) $ret .= '<br>';
			$label = '';
			$i = 0;
			$curr_len = 0;
			$tags = array();
			$inside = false;
			$strlen = strlen($str); 
			while ($curr_len<=$len && $i<$strlen) {
				if ($str{$i} == '&' && !$inside) {
					$e = -1;
					if (isset($str{$i+3}) && $str{$i+3}==';') $e = 3;
					elseif (isset($str{$i+4}) && $str{$i+4}==';') $e = 4;
					elseif (isset($str{$i+5}) && $str{$i+5}==';') $e = 5;
					if ($e!=-1) {
						$hsc = substr($str, $i, $e+1);
						if ($hsc=='&nbsp;' || strlen(htmlspecialchars_decode($hsc))==1) {
							$label .= substr($str, $i, $e);
							$i += $e;
							$curr_len++;
						}
					}
				} elseif ($str{$i} == '<') {
					$inside = true;
					if (isset($str{$i+1}) && $str{$i+1} == '/') { 
						if (!empty($tags)) array_pop($tags);
					} else {
						$j = 1;
						$next_tag = '';
						while ($i+$j<=$strlen && $str{$i+$j}!=' ' && $str{$i+$j}!='>' && $str{$i+$j}!='/') {
							$next_tag .= $str{$i+$j};
							$j++;
						}
						$tags[] = $next_tag;
					}
				} elseif ($str{$i} == '>') {
					if ($i>0 && $str{$i-1} == '/') array_pop($tags);
					$inside = false;
				} elseif (!$inside) $curr_len++;
				$label .= $str{$i}; 
				$i++;
			}
			if ($i<$strlen) {
				$cut = true;
				$label .= '...';
				if ($tooltip) {
					if (!strpos($str, 'Utils_Toltip__showTip(')) $label = '<span '.Utils_TooltipCommon::open_tag_attrs(strip_tags($str)).'>'.$label.'</span>';
					else $label = preg_replace('/Utils_Toltip__showTip\(\'(.*?)\'/', 'Utils_Toltip__showTip(\''.escapeJS(htmlspecialchars($str)).'<hr>$1\'', $label);
				}
			}
			while (!empty($tags)) $label .= '</'.array_pop($tags).'>';
			$ret .= $label;
		}
		return $ret;
	}
	
	public static function build_cols_array($tab, $arg) {
		self::init($tab);
		$arg = array_flip($arg);
		$ret = array();
		foreach (self::$table_rows as $k=>$v) {
			if ($v['visible'] && !isset($arg[$v['id']])) $ret[$v['id']] = false;
			elseif (!$v['visible'] && isset($arg[$v['id']])) $ret[$v['id']] = true;
		}
		return $ret;
	}
	///////////////////////////////////////////
	// mobile devices
	
/*	public static function mobile_menu() {
		if(!Acl::is_user()) return array();
		$rbs = DB::GetAssoc('SELECT tab,caption FROM recordbrowser_table_properties');
		$ret = array();
		foreach($rbs as $table=>$cap)
			$ret[$cap]=array('func'=>'mobile_rb','args'=>array($table));
		return $ret;
	}
*/	
	public static function mobile_rb($table,array $crits=array(),array $sort=array(),$info=array()) {
		require_once('modules/Utils/RecordBrowser/mobile.php');
	}
	
	public static function mobile_rb_view($tab,$id) {
		self::add_recent_entry($tab, Acl::get_user() ,$id);
		$rec = self::get_record($tab,$id);
		$cols = Utils_RecordBrowserCommon::init($tab);
		if(IPHONE) {
			print('<ul class="field">');
			foreach($cols as $k=>$col) {
				$val = Utils_RecordBrowserCommon::get_val($tab,$col['name'],$rec,false,$col);
				if($val==='') continue;
				print('<li>'.$col['name'].': '.$val.'</li>');
			}
			print('</ul>');
		} else {
			foreach($cols as $k=>$col) {
				$val = Utils_RecordBrowserCommon::get_val($tab,$col['name'],$rec,false,$col);
				if($val==='') continue;
				print($col['name'].': '.$val.'<br>');
			}
		}
	}
}
?>
