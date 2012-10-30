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
    private static $del_or_a = '';
    public static $table_rows = array();
    public static $hash = array();
    public static $admin_access = false;
    public static $cols_order = array();
    public static $options_limit = 50;
	
    public static $display_callback_table = array();
    private static $clear_get_val_cache = false;
    public static function display_callback_cache($tab) {
        if (self::$clear_get_val_cache) {
            self::$clear_get_val_cache = false;
            self::$display_callback_table = array();
        }
        if (!isset(self::$display_callback_table[$tab])) {
            $ret = DB::Execute('SELECT * FROM '.$tab.'_callback WHERE freezed=1');
            while ($row = $ret->FetchRow())
                self::$display_callback_table[$tab][$row['field']] = explode('::',$row['callback']);
        }
	}
	
    public static function get_val($tab, $field, $record, $links_not_recommended = false, $args = null) {
        self::init($tab);
        $commondata_sep = '/';
        if (!isset(self::$table_rows[$field])) {
            if (!isset(self::$hash[$field])) trigger_error('Unknown field "'.$field.'" for recordset "'.$tab.'"',E_USER_ERROR);
            $field = self::$hash[$field];
        }
        if ($args===null) $args = self::$table_rows[$field];
        if (!isset($record[$args['id']])) trigger_error($args['id'].' - unknown field for record '.serialize($record), E_USER_ERROR);
        $val = $record[$args['id']];
		self::display_callback_cache($tab);
        if (isset(self::$display_callback_table[$tab][$field])) {
            $ret = call_user_func(self::$display_callback_table[$tab][$field], $record, $links_not_recommended, self::$table_rows[$field]);
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
                if (isset($pp[1])) $col = $pp[1];
                else return;//trigger_error("\"param\" attribute of field \"$field\" is not valid. Please set <recordset>::<field>");
                if (!is_array($val)) $val = array($val);
//              if ($tab=='__COMMON__') $data = Utils_CommonDataCommon::get_translated_array($col, true);
                $ret = '';
                $first = true;
                foreach ($val as $k=>$v){
//                  if ($tab=='__COMMON__' && !isset($data[$v])) continue;
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
                        $val = Utils_CommonDataCommon::get_value($col.'/'.$v,true);
						if (!$val) continue;
                        $res .= $val;
						if ($first) $first = false;
						else $ret .= '<br>';
                        $res = self::no_wrap($res);
                        if ($tooltip) $res = '<span '.Utils_TooltipCommon::open_tag_attrs($tooltip, false).'>'.$res.'</span>';
                        $ret .= $res;
                    } else {
                        $columns = explode('|', $col);
						if ($first) $first = false;
						else $ret .= '<br>';
						$ret .= Utils_RecordBrowserCommon::create_linked_label($tab, $columns, $v, $links_not_recommended);
					}
                }
                if ($ret=='') $ret = '---';
            }
            if ($args['type']=='commondata') {
                if (!isset($val) || $val==='') {
                    $ret = '';
                } else {
                    $arr = explode('::',$args['param']['array_id']);
                    $path = array_shift($arr);
                    foreach($arr as $v) $path .= '/'.$record[self::get_field_id($v)];
                    $path .= '/'.$record[$args['id']];
                    $ret = Utils_CommonDataCommon::get_value($path,true);
                }
            }
            if ($args['type']=='currency') {
                $val = Utils_CurrencyFieldCommon::get_values($val);
                $ret = Utils_CurrencyFieldCommon::format($val[0], $val[1]);
            }
            if ($args['type']=='checkbox') {
                $ret = $ret?__('Yes'):__('No');
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
			if ($args['type']=='long text') {
				$ret = htmlspecialchars($val);
                $ret = str_replace("\n",'<br>',$ret);
                $ret = Utils_BBCodeCommon::parse($ret);
			}
        }
        return $ret;
    }
    public static function multiselect_from_common($arrid) {
        return '__COMMON__::'.$arrid;
    }
    public static function format_long_text($text){
		$ret = htmlspecialchars($text);
		$ret = str_replace("\n",'<br>',$ret);
		$ret = Utils_BBCodeCommon::parse($ret);
        return $ret;
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
        if(is_array($v)) return $v;
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
    public static function user_settings(){
        $ret = DB::Execute('SELECT tab, caption, icon, recent, favorites, full_history FROM recordbrowser_table_properties');
        $settings = array(0=>array(), 1=>array(), 2=>array(), 3=>array());
        while ($row = $ret->FetchRow()) {
			$caption = _V($row['caption']); // ****** RecordBrowser - recordset caption
            if (!self::get_access($row['tab'],'browse')) continue;
            if ($row['favorites'] || $row['recent']) {
                $options = array('all'=>__('All'));
                if ($row['favorites']) $options['favorites'] = __('Favorites');
                if ($row['recent']) $options['recent'] = __('Recent');
                if (Utils_WatchdogCommon::category_exists($row['tab'])) $options['watchdog'] = __('Watched');
                $settings[0][] = array('name'=>$row['tab'].'_default_view','label'=>$caption,'type'=>'select','values'=>$options,'default'=>'all');
            }
            if ($row['favorites'])
                $settings[1][] = array('name'=>$row['tab'].'_auto_fav','label'=>$caption,'type'=>'select','values'=>array(__('Disabled'), __('Enabled')),'default'=>0);
            if (Utils_WatchdogCommon::category_exists($row['tab'])) {
                $settings[2][] = array('name'=>$row['tab'].'_auto_subs','label'=>$caption,'type'=>'select','values'=>array(__('Disabled'), __('Enabled')),'default'=>0);
//              $settings[3][] = array('name'=>$row['tab'].'_subs_category','label'=>$caption,'type'=>'select','values'=>array(__('Disabled'), __('Enabled')),'default'=>0);
            }
			$settings[0][] = array('name'=>$row['tab'].'_show_filters','label'=>'','type'=>'hidden','default'=>0);
        }
        $final_settings = array();
        $subscribe_settings = array();
        $final_settings[] = array('name'=>'add_in_table_shown','label'=>__('Quick new record - show by default'),'type'=>'checkbox','default'=>0);
        $final_settings[] = array('name'=>'hide_empty','label'=>__('Hide empty fields'),'type'=>'checkbox','default'=>0);
        $final_settings[] = array('name'=>'enable_autocomplete','label'=>__('Enable autocomplete in select/multiselect at'),'type'=>'select','default'=>50, 'values'=>array(0=>__('Always'), 20=>__('%s records', array(20)), 50=>__('%s records', array(50)), 100=>__('%s records', array(100))));
        $final_settings[] = array('name'=>'grid','label'=>__('Grid edit (experimental)'),'type'=>'checkbox','default'=>0);
        $final_settings[] = array('name'=>'header_default_view','label'=>__('Default data view'),'type'=>'header');
        $final_settings = array_merge($final_settings,$settings[0]);
        $final_settings[] = array('name'=>'header_auto_fav','label'=>__('Automatically add to favorites records created by me'),'type'=>'header');
        $final_settings = array_merge($final_settings,$settings[1]);
        $subscribe_settings[] = array('name'=>'header_auto_subscriptions','label'=>__('Automatically watch records created by me'),'type'=>'header');
        $subscribe_settings = array_merge($subscribe_settings,$settings[2]);
//      $final_settings[] = array('name'=>'header_category_subscriptions','label'=>__('Auto-subscribe to all new records'),'type'=>'header');
//      $final_settings = array_merge($final_settings,$settings[3]);
        return array(__('Browsing records')=>$final_settings, __('Watchdog')=>$subscribe_settings);
    }
    public static function check_table_name($tab, $flush=false, $failure_on_missing=true){
        static $tables = null;
        if ($tables===null || $flush) {
            $r = DB::GetAll('SELECT tab FROM recordbrowser_table_properties');
            $tables = array();
            foreach($r as $v)
                $tables[$v['tab']] = true;
        }
        if (!isset($tables[$tab]) && !$flush && $failure_on_missing) trigger_error('RecordBrowser critical failure, terminating. (Requested '.serialize($tab).', available '.print_r($tables, true).')', E_USER_ERROR);
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
                $where .= ' AND f_'.$v.' '.DB::like().' '.DB::Concat(DB::qstr('%\_\_'),'%s',DB::qstr('\_\_%'));
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
		return array('label'=>__('Record Browser'), 'section'=>__('Data'));
    }
	public static function admin_access_levels() {
		return array(
			'records'=>array('label'=>__('Manage Records'), 'values'=>array(0=>__('No access'), 1=>__('View'), 2=>__('Full')), 'default'=>1),
			'fields'=>array('label'=>__('Manage Fields'), 'values'=>array(0=>__('No access'), 1=>__('View'), 2=>__('Full')), 'default'=>1),
			'addons'=>array('label'=>__('Manage Addons'), 'values'=>array(0=>__('No access'), 1=>__('View'), 2=>__('Full')), 'default'=>2),
			'permissions'=>array('label'=>__('Permissions'), 'values'=>array(0=>__('No access'), 1=>__('View'), 2=>__('Full')), 'default'=>1),
			'pattern'=>array('label'=>__('Clipboard Pattern'), 'values'=>array(0=>__('No access'), 1=>__('View'), 2=>__('Full')), 'default'=>2)
		);
	}
	public static function get_field_id($f) {
		return preg_replace('/[^|a-z0-9]/','_',strtolower($f));
	}
    public static function init($tab, $admin=false, $force=false) {
        static $cache = array();
        if (!isset(self::$cols_order[$tab])) self::$cols_order[$tab] = array();
        $cache_str = $tab.'__'.$admin.'__'.md5(serialize(self::$cols_order[$tab]));
        if (!$force && isset($cache[$cache_str])) {
            self::$hash = $cache[$cache_str]['hash'];
            return self::$table_rows = $cache[$cache_str]['rows'];
        }
        self::$table_rows = array();
        self::check_table_name($tab);
		self::display_callback_cache($tab);
        $ret = DB::Execute('SELECT * FROM '.$tab.'_field'.($admin?'':' WHERE active=1 AND type!=\'page_split\'').' ORDER BY position');
        self::$hash = array();
        while($row = $ret->FetchRow()) {
            if ($row['field']=='id') continue;
			$commondata = false;
            if ($row['type']=='commondata') {
                $row['param'] = self::decode_commondata_param($row['param']);
				$commondata = true;
			}
            self::$table_rows[$row['field']] =
                array(  'name'=>str_replace('%','%%',$row['field']),
                        'id'=>self::get_field_id($row['field']),
                        'type'=>$row['type'],
                        'visible'=>$row['visible'],
                        'required'=>($row['type']=='calculated'?false:$row['required']),
                        'extra'=>$row['extra'],
                        'active'=>$row['active'],
                        'position'=>$row['position'],
                        'filter'=>$row['filter'],
                        'style'=>$row['style'],
                        'param'=>$row['param']);
			if (isset(self::$display_callback_table[$tab][$row['field']]))
				self::$table_rows[$row['field']]['display_callback'] = self::$display_callback_table[$tab][$row['field']];
			if (($row['type']=='select' || $row['type']=='multiselect') && $row['param']) {
				$pos = strpos($row['param'], ':');
				self::$table_rows[$row['field']]['ref_table'] = substr($row['param'], 0, $pos);
				if (self::$table_rows[$row['field']]['ref_table']=='__COMMON__') {
					self::$table_rows[$row['field']]['ref_field'] = '__COMMON__';
					self::$table_rows[$row['field']]['ref_table'] = substr($row['param'], $pos+2);
					$commondata = true;
				} else {
				    $end = strpos($row['param'], ';', $pos+2);
				    if ($end==0) $end = strlen($row['param']);
					self::$table_rows[$row['field']]['ref_field'] = substr($row['param'], $pos+2, $end-$pos-2);
				    if (!self::$table_rows[$row['field']]['ref_field'] && $tab=='company') trigger_error($pos.$tab.print_r($row,true));
				}
			}
			self::$table_rows[$row['field']]['commondata'] = $commondata;

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
        if (!preg_match('/^[a-zA-Z_0-9]+$/',$tab)) trigger_error('Invalid table name ('.$tab.') given to install_new_recordset.',E_USER_ERROR);
        if (strlen($tab)>39) trigger_error('Invalid table name ('.$tab.') given to install_new_recordset, max length is 39 characters.',E_USER_ERROR);
        if (!DB::GetOne('SELECT 1 FROM recordbrowser_table_properties WHERE tab=%s', array($tab))) {
            DB::Execute('INSERT INTO recordbrowser_table_properties (tab) VALUES (%s)', array($tab));
        }

        @DB::DropTable($tab.'_callback');
        @DB::DropTable($tab.'_recent');
        @DB::DropTable($tab.'_favorite');
        @DB::DropTable($tab.'_edit_history_data');
        @DB::DropTable($tab.'_edit_history');
        @DB::DropTable($tab.'_field');
        @DB::DropTable($tab.'_data_1');
		@DB::DropTable($tab.'_access_clearance');
		@DB::DropTable($tab.'_access_fields');
		@DB::DropTable($tab.'_access');

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
        DB::CreateTable($tab.'_callback',
                    'field C(32),'.
                    'callback C(255),'.
                    'freezed I1',
                    array('constraints'=>''));

        DB::Execute('INSERT INTO '.$tab.'_field(field, type, extra, visible, position) VALUES(\'id\', \'foreign index\', 0, 0, 1)');
        DB::Execute('INSERT INTO '.$tab.'_field(field, type, extra, position) VALUES(\'General\', \'page_split\', 0, 2)');

		$fields_sql = '';
        foreach ($fields as $v)
            $fields_sql .= Utils_RecordBrowserCommon::new_record_field($tab, $v, false);
        DB::CreateTable($tab.'_data_1',
                    'id I AUTO KEY,'.
                    'created_on T NOT NULL,'.
                    'created_by I NOT NULL,'.
                    'private I4 DEFAULT 0,'.
                    'active I1 NOT NULL DEFAULT 1'.
					$fields_sql,
                    array('constraints'=>''));

        DB::CreateTable($tab.'_edit_history',
                    'id I AUTO KEY,'.
                    $tab.'_id I NOT NULL,'.
                    'edited_on T NOT NULL,'.
                    'edited_by I NOT NULL',
                    array('constraints'=>', FOREIGN KEY (edited_by) REFERENCES user_login(id), FOREIGN KEY ('.$tab.'_id) REFERENCES '.$tab.'_data_1(id)'));
        DB::CreateTable($tab.'_edit_history_data',
                    'edit_id I,'.
                    'field C(32),'.
                    'old_value X',
                    array('constraints'=>', FOREIGN KEY (edit_id) REFERENCES '.$tab.'_edit_history(id)'));
        DB::CreateTable($tab.'_favorite',
                    'fav_id I AUTO KEY,'.
                    $tab.'_id I,'.
                    'user_id I',
                    array('constraints'=>', FOREIGN KEY (user_id) REFERENCES user_login(id), FOREIGN KEY ('.$tab.'_id) REFERENCES '.$tab.'_data_1(id)'));
        DB::CreateTable($tab.'_recent',
                    'recent_id I AUTO KEY,'.
                    $tab.'_id I,'.
                    'user_id I,'.
                    'visited_on T',
                    array('constraints'=>', FOREIGN KEY (user_id) REFERENCES user_login(id), FOREIGN KEY ('.$tab.'_id) REFERENCES '.$tab.'_data_1(id)'));
		DB::CreateTable($tab.'_access',
					'id I AUTO KEY,'.
					'action C(16),'.
					'crits C(255)',
					array('constraints'=>''));
		DB::CreateTable($tab.'_access_fields',
					'rule_id I,'.
					'block_field C(32)',
					array('constraints'=>', FOREIGN KEY (rule_id) REFERENCES '.$tab.'_access(id)'));
		DB::CreateTable($tab.'_access_clearance',
					'rule_id I,'.
					'clearance C(32)',
					array('constraints'=>', FOREIGN KEY (rule_id) REFERENCES '.$tab.'_access(id)'));
		self::check_table_name($tab, true);
		self::add_access($tab, 'print', 'SUPERADMIN');
		self::add_access($tab, 'export', 'SUPERADMIN');
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
        DB::DropTable($tab.'_access_clearance');
		DB::DropTable($tab.'_access_fields');
		DB::DropTable($tab.'_access');
        DB::Execute('DELETE FROM recordbrowser_table_properties WHERE tab=%s', array($tab));
        return true;
    }

    public static function delete_record_field($tab, $field){
        self::init($tab);
        self::$clear_get_val_cache = true;
        $exists = DB::GetOne('SELECT 1 FROM '.$tab.'_field WHERE field=%s', array($field));
        if(!$exists) return;
        DB::Execute('DELETE FROM '.$tab.'_field WHERE field=%s', array($field));
		$f_id = self::$table_rows[$field]['id'];
        @DB::Execute('ALTER TABLE '.$tab.'_data_1 DROP COLUMN f_'.$f_id);
		@DB::Execute('DELETE FROM '.$tab.'_access_fields WHERE block_field=%s', array($f_id));
        self::init($tab, false, true);
    }

    public static function new_record_field($tab, $definition, $alter=true){
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
            foreach (array( 0=>'name',
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
//      $field, $type, $visible, $required, $param='', $style='', $extra = true, $filter = false, $pos = null

		if (strpos($definition['name'],'|')!==false) trigger_error('Invalid field name (character | is not allowed):'.$definition['name'], E_USER_ERROR);
        self::check_table_name($tab);
        self::$clear_get_val_cache = true;
		if ($alter) {
			$exists = DB::GetOne('SELECT field FROM '.$tab.'_field WHERE field=%s', array($definition['name']));
			if ($exists) return;
		}

        DB::StartTrans();
        if (is_string($definition['position'])) $definition['position'] = DB::GetOne('SELECT position FROM '.$tab.'_field WHERE field=%s', array($definition['position']))+1;
        if ($definition['position']===null || $definition['position']===false) {
            $definition['position'] = DB::GetOne('SELECT MAX(position) FROM '.$tab.'_field')+1;
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
        DB::Execute('INSERT INTO '.$tab.'_field(field, type, visible, param, style, position, extra, required, filter) VALUES(%s, %s, %d, %s, %s, %d, %d, %d, %d)', array($definition['name'], $definition['type'], $definition['visible']?1:0, $param, $definition['style'], $definition['position'], $definition['extra']?1:0, $definition['required']?1:0, $definition['filter']?1:0));
		$column = 'f_'.self::get_field_id($definition['name']);
		if ($alter) {
			self::init($tab, false, true);
			if ($f!=='') @DB::Execute('ALTER TABLE '.$tab.'_data_1 ADD COLUMN '.$column.' '.$f);
		} else {
			if ($f!=='') return ','.$column.' '.$f;
			else return '';
		}
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
        return $f;
    }
    public static function new_browse_mode_details_callback($tab, $mod, $func) {
        self::check_table_name($tab);
        if(!DB::GetOne('SELECT 1 FROM recordbrowser_browse_mode_definitions WHERE tab=%s AND module=%s AND func=%s', array($tab, $mod, $func)))
            DB::Execute('INSERT INTO recordbrowser_browse_mode_definitions (tab, module, func) VALUES (%s, %s, %s)', array($tab, $mod, $func));
    }
    
    public static function delete_browse_mode_details_callback($tab, $mod, $func) {
        self::check_table_name($tab);
        DB::Execute('DELETE FROM recordbrowser_browse_mode_definitions WHERE tab=%s AND module=%s AND func=%s', array($tab, $mod, $func));
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
        if ($pos===false || $pos===null) return false;
        DB::Execute('DELETE FROM recordbrowser_addon WHERE tab=%s AND module=%s AND func=%s', array($tab, $module, $func));
        while (DB::GetOne('SELECT pos FROM recordbrowser_addon WHERE tab=%s AND pos=%d', array($tab, $pos+1))) {
            DB::Execute('UPDATE recordbrowser_addon SET pos=pos-1 WHERE tab=%s AND pos=%d', array($tab, $pos+1));
            $pos++;
        }
		return true;
    }
    public static function set_addon_pos($tab, $module, $func, $pos) {
        $module = str_replace('/','_',$module);
        $old_pos = DB::GetOne('SELECT pos FROM recordbrowser_addon WHERE tab=%s AND module=%s AND func=%s', array($tab, $module, $func));
		if ($old_pos>$pos)
			DB::Execute('UPDATE recordbrowser_addon SET pos=pos+1 WHERE tab=%s AND pos>=%d AND pos<%d', array($tab, $pos, $old_pos));
		else
			DB::Execute('UPDATE recordbrowser_addon SET pos=pos-1 WHERE tab=%s AND pos<=%d AND pos>%d', array($tab, $pos, $old_pos));
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
    public static function register_processing_callback($tab, $callback) {
        $tables_db = DB::MetaTables();
        if(!in_array('recordbrowser_processing_methods',$tables_db)) return; // Delete this on major patch
        if (is_array($callback)) $callback = implode('::',$callback);
        if(!DB::GetOne('SELECT 1 FROM recordbrowser_processing_methods WHERE tab=%s AND func=%s', array($tab, $callback)))
            DB::Execute('INSERT INTO recordbrowser_processing_methods (tab, func) VALUES (%s, %s)', array($tab, $callback));
    }
    public static function unregister_processing_callback($tab, $callback) {
        $tables_db = DB::MetaTables();
        if(!in_array('recordbrowser_processing_methods',$tables_db)) return; // Delete this on major patch
        if (is_array($callback)) $callback = implode('::',$callback);
        DB::Execute('DELETE FROM recordbrowser_processing_methods WHERE tab=%s AND func=%s', array($tab, $callback));
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
    public static function set_description_callback($tab, $callback){
        if (is_array($callback)) $callback = implode('::',$callback);
        DB::Execute('UPDATE recordbrowser_table_properties SET description_callback=%s WHERE tab=%s', array($callback, $tab));
    }
    public static function get_caption($tab) {
		static $cache = null;
        if ($cache===null) $cache = DB::GetAssoc('SELECT tab, caption FROM recordbrowser_table_properties');
		if (is_string($tab) && isset($cache[$tab])) return _V($cache[$tab]);
		return '---';
    }
    public static function get_sql_type($type) {
        switch ($type) {
            case 'checkbox': return '%d';
            case 'select': return '%s';
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
                case 'created_on':  DB::Execute('UPDATE '.$tab.'_data_1 SET created_on=%T WHERE id=%d', array($v, $id));
                                    break;
                case 'created_by':  DB::Execute('UPDATE '.$tab.'_data_1 SET created_by=%d WHERE id=%d', array($v, $id));
                                    break;
            }
    }

	public static function record_processing($tab, $base, $mode, $clone=null) {
        self::check_table_name($tab);
        $tables_db = DB::MetaTables();
        if(!in_array('recordbrowser_processing_methods',$tables_db)) return $base;
		static $cache = array();
		if (!isset($cache[$tab])) {
			$ret = DB::Execute('SELECT * FROM recordbrowser_processing_methods WHERE tab=%s', array($tab));
			$cache[$tab] = array();
			while ($row = $ret->FetchRow()) {
				$callback = explode('::',$row['func']);
				if (is_callable($callback))
					$cache[$tab][] = $callback;
			}
		}
		$current = $base;
		if ($mode=='display') $result = array();
		else $result = $base;
		foreach ($cache[$tab] as $callback) {
			if ($mode=='cloned') $current = array('original'=>$clone, 'clone'=>$current);
			$return = call_user_func($callback, $current, $mode);
			if ($return===false) return false;
			if ($return) {
				if ($mode!='display') $current = $return;
				else $result = array_merge_recursive($result, $return);
			}
		}
		if ($mode!='display') $result = $current;
		return $result;
	}

    public static function new_record( $tab, $values = array()) {
        self::init($tab);
        $user = Acl::get_user();

		$for_processing = $values;
		foreach(self::$table_rows as $field=>$args)
			if ($args['type']==='multiselect') {
				if (!isset($for_processing[$args['id']]) || !$for_processing[$args['id']])
					$for_processing[$args['id']] = array();
			} elseif (!isset($for_processing[$args['id']])) $for_processing[$args['id']] = '';

		$values = self::record_processing($tab, $for_processing, 'add');
		if ($values===false) return;

        self::init($tab);
        $fields = 'created_on,created_by,active';
        $fields_types = '%T,%d,%d';
        $vals = array(date('Y-m-d H:i:s'), $user, 1);
        foreach(self::$table_rows as $field => $args) {
            if (!isset($values[$args['id']]) || $values[$args['id']]==='') continue;
			if (!is_array($values[$args['id']])) $values[$args['id']] = trim($values[$args['id']]);
            if ($args['type']=='long text')
                $values[$args['id']] = Utils_BBCodeCommon::optimize($values[$args['id']]);
            if ($args['type']=='multiselect' && empty($values[$args['id']])) continue;
            if ($args['type']=='multiselect')
                $values[$args['id']] = self::encode_multi($values[$args['id']]);
            $fields_types .= ','.self::get_sql_type($args['type']);
            $fields .= ',f_'.$args['id'];
            if (is_bool($values[$args['id']])) $values[$args['id']] = $values[$args['id']]?1:0;
            $vals[] = $values[$args['id']];
        }
        DB::Execute('INSERT INTO '.$tab.'_data_1 ('.$fields.') VALUES ('.$fields_types.')',$vals);
        $id = DB::Insert_ID($tab.'_data_1', 'id');
        if ($user) self::add_recent_entry($tab, $user, $id);
        if (Base_User_SettingsCommon::get('Utils_RecordBrowser',$tab.'_auto_fav'))
            DB::Execute('INSERT INTO '.$tab.'_favorite (user_id, '.$tab.'_id) VALUES (%d, %d)', array($user, $id));
		self::init($tab);
		foreach(self::$table_rows as $field=>$args)
			if ($args['type']==='multiselect') {
				if (!isset($values[$args['id']])) $values[$args['id']] = array();
				elseif (!is_array($values[$args['id']]))
					$values[$args['id']] = self::decode_multi($values[$args['id']]);
			}
		$values['id'] = $id;
		self::record_processing($tab, $values, 'added');

        if (Base_User_SettingsCommon::get('Utils_RecordBrowser',$tab.'_auto_subs')==1)
            Utils_WatchdogCommon::subscribe($tab,$id);
        Utils_WatchdogCommon::new_event($tab,$id,'C');

        return $id;
    }
    public static function update_record($tab,$id,$values,$all_fields = false, $date = null, $dont_notify = false) {
        DB::StartTrans();
        self::init($tab);
        $record = self::get_record($tab, $id, false);
        if (!is_array($record)) return false;

		$process_method_args = $values;
		$process_method_args['id'] = $id;
		foreach ($record as $k=>$v)
			if (!isset($process_method_args[$k])) $process_method_args[$k] = $v;

		$values = self::record_processing($tab, $process_method_args, 'edit');

        $diff = array();
        self::init($tab);
        foreach(self::$table_rows as $field => $args){
			if ($args['type']=='calculated' && preg_match('/^[a-z]+(\([0-9]+\))?$/i',$args['param'])===0) continue; // FIXME move DB definiton to *_field table
            if ($args['id']=='id') continue;
            if (!isset($values[$args['id']])) {
                if ($all_fields) $values[$args['id']] = '';
                else continue;
            }
			if (is_bool($values[$args['id']])) {
				if ($values[$args['id']]===true) $values[$args['id']] = 1;
				else $values[$args['id']] = 0;
			}
            if ($args['type']=='long text')
                $values[$args['id']] = Utils_BBCodeCommon::optimize($values[$args['id']]);
            if ($args['type']=='multiselect') {
                if (!is_array($values[$args['id']])) $values[$args['id']] = array($values[$args['id']]);
                $array_diff = array_diff($record[$args['id']], $values[$args['id']]);
                if (empty($array_diff)) {
                    $array_diff = array_diff($values[$args['id']], $record[$args['id']]);
                    if (empty($array_diff)) continue;
                }
                $v = self::encode_multi($values[$args['id']]);
                $old = self::encode_multi($record[$args['id']]);
            } else {
                if ($record[$args['id']]===$values[$args['id']]) continue;
                $v = $values[$args['id']];
                $old = $record[$args['id']];
            }
            if ($v!=='') DB::Execute('UPDATE '.$tab.'_data_1 SET f_'.$args['id'].'='.self::get_sql_type($args['type']).' WHERE id=%d',array($v, $id));
            else DB::Execute('UPDATE '.$tab.'_data_1 SET f_'.$args['id'].'=NULL WHERE id=%d',array($id));
            $diff[$args['id']] = $old;
        }
        if (!$dont_notify && !empty($diff)) {
			$diff = self::record_processing($tab, $diff, 'edit_changes');
            DB::Execute('INSERT INTO '.$tab.'_edit_history(edited_on, edited_by, '.$tab.'_id) VALUES (%T,%d,%d)', array((($date==null)?date('Y-m-d G:i:s'):$date), Acl::get_user(), $id));
            $edit_id = DB::Insert_ID(''.$tab.'_edit_history','id');
            foreach($diff as $k=>$v) {
                if (!is_array($v)) $v = array($v);
                foreach($v as $c)
                    DB::Execute('INSERT INTO '.$tab.'_edit_history_data(edit_id, field, old_value) VALUES (%d,%s,%s)', array($edit_id, $k, $c));
            }
            Utils_WatchdogCommon::new_event($tab,$id,'E_'.$edit_id);
        }
        return DB::CompleteTrans();
    }
    public static function add_recent_entry($tab, $user_id ,$id){
        self::check_table_name($tab);
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
        DB::Execute('INSERT INTO '.$tab.'_recent ('.$tab.'_id, user_id, visited_on) VALUES (%d, %d, %T)',
                    array($id,
                    $user_id,
                    date('Y-m-d H:i:s')));
    }
    public static function merge_crits($a = array(), $b = array(), $or=false) {
		if ($or) {
			if ($a===null) return $b;
			if ($b===null) return $a;
			if (empty($a) || empty($b)) return array();
			reset($a);
			$key = key($a);
			if ($key[0]!='^') {
				$el = array_shift($a);
				$a = array('^'.$key=>$el)+$a;
			}

			reset($b);
			$key = key($b);
			if ($key[0]!='^') {
				$el = array_shift($b);
				$b = array('^'.$key=>$el)+$b;
			}
			
			$nb = array();
			
			foreach ($b as $k=>$v){
				$nk = $k;
				while (isset($a[$nk])) $nk = '_'.$nk;
				$nb[$nk] = $v;
			}
			$b = $nb;
			
			return $a+$b;
		} else {
			foreach ($b as $k=>$v){
				$nk = $k;
				while (isset($a[$nk])) $nk = '_'.$nk;
				$a[$nk] = $v;
			}
		}
        return $a;
    }
    public static function build_query( $tab, $crits = null, $admin = false, $order = array()) {
		$PG = (DATABASE_DRIVER == "postgres");
		if (!is_array($order)) $order = array();
        $cache_key=$tab.'__'.serialize($crits).'__'.$admin.'__'.serialize($order);
        static $cache = array();
        self::init($tab, $admin);
		if (isset($cache[$cache_key])) return $cache[$cache_key];
        if (!$tab) return false;
		$postgre = (strcasecmp(DATABASE_DRIVER,"postgres")===0);
        $having = '';
        $fields = '';
        $final_tab = $tab.'_data_1 AS r';
        $vals = array();
        if (!$crits) $crits = array();
		$access = self::get_access($tab, 'browse');
		if ($access===false) return array();
		elseif ($access!==true && is_array($access))
			$crits = self::merge_crits($crits, $access);
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
		$group_or_start = $group_or = false;
		$special_chars = str_split('!"(|<>=~]^');
        foreach($crits as $k=>$v){
            self::init($tab, $admin);
			$f = explode('[',$k);
			$f = str_replace($special_chars,'',$f[0]);
			while ($f[0]=='_') $f = substr($f, 1);
            if (!isset(self::$table_rows[$f]) && $f[0]!=':' && $f!=='id' && (!isset(self::$hash[$f]) || !isset(self::$table_rows[self::$hash[$f]]))) continue; //failsafe
            $negative = $noquotes = $or_start = $or = false;
            $operator = '=';
            while (($k[0]<'a' || $k[0]>'z') && ($k[0]<'A' || $k[0]>'Z') && $k[0]!=':') {
                if ($k[0]=='!') $negative = true;
                if ($k[0]=='"') $noquotes = true;
                if ($k[0]=='(') $or_start = true;
                if ($k[0]=='|') $or = true;
                if ($k[0]=='<') $operator = '<';
                if ($k[0]=='>') $operator = '>';
                if ($k[0]=='~') $operator = DB::like();
                if ($k[0]=='^') $group_or_start = true;
                if ($k[1]=='=' && $operator!=DB::like()) {
                    $operator .= '=';
                    $k = substr($k, 2);
                } else $k = substr($k, 1);
                if (!isset($k[0])) trigger_error('Invalid criteria in build query: missing word. Crits:'.print_r($crits,true), E_USER_ERROR);
            }
            $or |= $or_start;
			if ($group_or && $group_or_start)
				$having .= ')';
			if ($or_start && $or_started || ($or_started && !$or)) {
				$having .= ')';
				$or_started = false;
			}
            if ($or) {
				if ($having!='') {
					if ($group_or && $group_or_start || $or_started) $having .= ' OR ';
					else $having .= ' AND ';
				}
				if ($group_or_start) $having .= '(';
				if (!$or_started) $having .= '(';
                $or_started = true;
            } else {
				if ($having!='' && $group_or && $group_or_start) $having .= ' OR ';
				if ($having!='' && (!$group_or || !$group_or_start)) $having .= ' AND ';
				if ($group_or_start) $having .= '(';
            }
			if ($group_or_start) {
				if (!$group_or) $having .= '(';
				$group_or = true;
				$group_or_start = false;
			}
			if ($k[strlen($k)-1]==']') {
				list($ref, $sub_field) = explode('[', trim($k, ']'));
				$args = self::$table_rows[self::$hash[$ref]];
				$commondata = $args['commondata'];
				if (is_array($args['param'])) {
					if (isset($args['param']['array_id']))
						$args['ref_table'] = $args['param']['array_id'];
					else
						$args['ref_table'] = $args['param'][1];
				}
				if (!isset($args['ref_table'])) trigger_error('Invalid crits, field '.$ref.' is not a reference; crits: '.print_r($crits,true),E_USER_ERROR);
				$is_multiselect = ($args['type']=='multiselect');
				$tab2 = $args['ref_table'];
				$col2 = $sub_field;
				if ($commondata) {
					$ret = Utils_CommonDataCommon::get_translated_array($tab2);
					$allowed_cd = array();
					if (!is_array($v)) $v = array($v);
					foreach ($ret as $kkk=>$vvv)
						foreach ($v as $w) if ($w!='') {
							if ($operator==DB::like())
								$w = '/'.preg_quote($w, '/').'/i';
							else
								$w = '/^'.preg_quote($w, '/').'$/i';
							if (preg_match($w,$vvv)!==0) {
								$allowed_cd[] = $kkk;
								break;
							}
						}
					if (empty($allowed_cd)) {
						$having .= $negative?'true':'false';
						continue;
					}
				} else {
					self::init($tab2);
					$det = explode('/', $col2);
					$col2 = explode('|', $det[0]);
					//self::init($tab);
					if (!is_array($v)) $v = array($v);
					$poss_vals = '';
					$col2s = array();
					$col2m = array();
					
					$conv = '';
					if ($PG) $conv = '::varchar';
					foreach ($col2 as $c) {
						if (self::$table_rows[self::$hash[$c]]['type']=='multiselect')
							$col2m[] = $c.$conv;
						else
							$col2s[] = $c.$conv;
					}

					foreach ($v as $w) {
						if ($w==='') {
							$poss_vals .= 'OR f_'.implode(' IS NULL OR f_', $col2);
							break;
						} else {
							if (!$noquotes) $w = DB::qstr($w);
							if (!empty($col2s)) $poss_vals .= ' OR f_'.implode(' '.DB::like().' '.$w.' OR f_', $col2s).' '.DB::like().' '.$w;
							if (!empty($col2m)) {
								$w = DB::Concat(DB::qstr('%'),DB::qstr('\_\_'),$w,DB::qstr('\_\_'),DB::qstr('%'));
								$poss_vals .= ' OR f_'.implode(' '.DB::like().' '.$w.' OR f_', $col2m).' '.DB::like().' '.$w;
							}
						}
					}
					$allowed_cd = DB::GetAssoc('SELECT id, id FROM '.$tab2.'_data_1 WHERE false '.$poss_vals);

					if (empty($allowed_cd)) {
						$having .= $negative?'true':'false';
						continue;
					}
				}
				if ($operator==DB::like())
					$operator = '=';
				$v = $allowed_cd;
				$k = $ref;
			}
			self::init($tab);
            if ($k[0]==':') {
                switch ($k) {
                    case ':Fav' :   $final_tab = '('.$final_tab.') LEFT JOIN '.$tab.'_favorite AS fav ON fav.'.$tab.'_id=r.id';
                                    $having .= ' (fav.user_id='.Acl::get_user().' AND fav.user_id IS NOT NULL)';
                                    break;
                    case ':Sub' :   $final_tab = '('.$final_tab.') LEFT JOIN utils_watchdog_subscription AS sub ON sub.internal_id=r.id AND sub.category_id='.Utils_WatchdogCommon::get_category_id($tab);
                                    $having .= ' (sub.user_id='.Acl::get_user().' AND sub.user_id IS NOT NULL)';
                                    break;
                    case ':Recent'  :   $final_tab = '('.$final_tab.') LEFT JOIN '.$tab.'_recent AS rec ON rec.'.$tab.'_id=r.id';
                                        $having .= ' (rec.user_id='.Acl::get_user().' AND rec.user_id IS NOT NULL)';
                                        break;
                    case ':Created_on'  :
                            $inj = $operator.DB::qstr($v);
							$having .= ' created_on '.$inj;
                            break;
                    case ':Created_by'  :
                            $having .= ' created_by = '.$v;
                            break;
                    case ':Edited_on'   :
                            $inj = $operator.DB::qstr($v);
							$having .= ' (((SELECT MAX(edited_on) FROM '.$tab.'_edit_history WHERE '.$tab.'_id=r.id) '.$inj.') OR'.
									'((SELECT MAX(edited_on) FROM '.$tab.'_edit_history WHERE '.$tab.'_id=r.id) IS NULL AND created_on '.$inj.'))';
                            break;
                    default:
                        trigger_error('Unknow paramter given to get_records criteria: '.$k, E_USER_ERROR);
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
					// Postgres compatibility fix
                    if (!is_array($v)) $v = array($v);
                    if ($negative) $having .= 'NOT ';
                    $having .= '(false';
                    foreach($v as $w) {
                        if (isset(self::$hash[$k])) {
                            $f = self::$hash[$k];
                            $key = $k;
                        } elseif (isset(self::$table_rows[$k])) {
                            $f = $k;
                            $key = self::$table_rows[$k]['id'];
                        } else trigger_error('In table "'.$tab.'" - unknow column "'.$k.'" in criteria "'.print_r($crits,true).'". Available columns are: "'.print_r(self::$table_rows,true).'"', E_USER_ERROR);

						if (self::$table_rows[self::$hash[$key]]['type']=='timestamp' && is_numeric($w))
							$w = date('Y-m-d H:i:s', $w);
						elseif (self::$table_rows[self::$hash[$key]]['type']=='date' && is_numeric($w))
							$w = date('Y-m-d', $w);

						if ($postgre && $operator==DB::like()) $key .= '::varchar';
                        if (self::$table_rows[$f]['type']!='text' && self::$table_rows[$f]['type']!='long text' && ($w==='' || $w===null || $w===false)) {
                            if($operator=='=')
                                $having .= ' OR r.f_'.$key.' IS NULL';
                            else
                                $having .= ' OR r.f_'.$key.' IS NOT NULL';
                        } elseif ($w==='') {
                            $having .= ' OR r.f_'.$k.' IS NULL OR r.f_'.$k.'=\'\'';
                        } else {
                            if (self::$table_rows[$f]['type']=='multiselect') {
                                $operator = DB::like();
                                $param = explode('::',self::$table_rows[$f]['param']);
                                $w = DB::Concat(DB::qstr('%'),DB::qstr('\_\_'.$w.'\_\_'),DB::qstr('%'));
                            }
                            elseif (!$noquotes) $w = DB::qstr($w);

                            if (false || $postgre && ($operator=='<' || $operator=='<=' || $operator=='>' || $operator=='>=')) {
								switch (self::$table_rows[$f]['type']) {
									case 'timestamp': $cast_type = 'timestamp'; break;
									case 'date': $cast_type = 'date'; break;
									default: $cast_type = 'integer';
								}
								$c_field = 'CAST(r.f_'.$key.' AS '.$cast_type.')';
							} else $c_field = 'r.f_'.$key;
                            $having .= ' OR ('.$c_field.' '.$operator.' '.$w.' ';
                            if ($operator=='<' || $operator=='<=') {
								$having .= 'OR r.f_'.$key.' IS NULL)';
							} else {
								$having .= 'AND r.f_'.$key.' IS NOT NULL)';
							}
                        }
                    }
                    $having .= ')';
                }
            }
        }
        if ($or_started) $having .= ')';
		if ($group_or) $having  .= '))';
        $orderby = array();
        self::init($tab);
        foreach($order as $v){
            if ($v['order'][0]!=':' && !isset(self::$table_rows[$v['order']])) continue; //failsafe
            if ($v['order'][0]==':') {
				if (!is_numeric(Acl::get_user())) trigger_error('Invalid user id.');
                switch ($v['order']) {
                    case ':id':
                        $orderby[] = ' id ' . $v['direction'];
                    case ':Fav' :
                        $orderby[] = ' (SELECT COUNT(*) FROM '.$tab.'_favorite WHERE '.$tab.'_id=r.id AND user_id='.Acl::get_user().') '.$v['direction'];
                        break;
                    case ':Visited_on'  :
                        $orderby[] = ' (SELECT visited_on FROM '.$tab.'_recent WHERE '.$tab.'_id=r.id AND user_id='.Acl::get_user().') '.$v['direction'];
                        break;
                    case ':Edited_on'   :
                        $orderby[] = ' (CASE WHEN (SELECT MAX(edited_on) FROM '.$tab.'_edit_history WHERE '.$tab.'_id=r.id) IS NOT NULL THEN (SELECT MAX(edited_on) FROM '.$tab.'_edit_history WHERE '.$tab.'_id=r.id) ELSE created_on END) '.$v['direction'];
                        break;
                    default     : trigger_error('Unknow paramter given to get_records order: '.$v, E_USER_ERROR);
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
                            $val = '(SELECT rdt.f_'.self::get_field_id($cols2).' FROM '.$tab.'_data_1 AS rd LEFT JOIN '.$tab2.'_data_1 AS rdt ON rdt.id=rd.f_'.$data_col.' WHERE r.id=rd.id)';
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
		if (!$having) $having = 'true';
        $final_tab = str_replace('('.$tab.'_data_1 AS r'.')',$tab.'_data_1 AS r',$final_tab);
        $default_filter = (class_exists('Utils_RecordBrowser') && isset(Utils_RecordBrowser::$admin_filter))?Utils_RecordBrowser::$admin_filter:'';
        $ret = array('sql'=>' '.$final_tab.' WHERE '.($admin?$default_filter:'active=1 AND ').$having,'order'=>$orderby,'vals'=>$vals);
        return $cache[$cache_key] = $ret;
    }
    public static function get_records_count( $tab, $crits = null, $admin = false) {
        $par = self::build_query($tab, $crits, $admin, array());
        if (empty($par) || !$par) return 0;
        return DB::GetOne('SELECT COUNT(*) FROM'.$par['sql'], $par['vals']);
    }
    public static function get_next_and_prev_record( $tab, $crits, $order, $id, $last = null) {
        $par = self::build_query($tab, $crits, false, $order);
        if (empty($par) || !$par) return null;
        if ($last===null || is_array($last)) {
            /* Just failsafe - should not happen */
            $ret = DB::GetCol('SELECT id FROM'.$par['sql'].$par['order'], $par['vals']);
            if ($ret===false || $ret===null) return null;
            $k = array_search($id,$ret);
            return array(   'next'=>isset($ret[$k+1])?$ret[$k+1]:null,
                            'curr'=>$k,
                            'prev'=>isset($ret[$k-1])?$ret[$k-1]:null);
        } else {
            $r = DB::SelectLimit('SELECT id FROM'.$par['sql'].$par['order'],3,($last!=0?$last-1:$last), $par['vals']);
            $ret = array();
            while ($row=$r->FetchRow()) {
                $ret[] = $row['id'];
            }
            if ($ret===false || $ret===null) return null;
            if ($last===0) $ret = array(0=>null, 2=>isset($ret[1])?$ret[1]:null);
            return array(   'next'=>isset($ret[2])?$ret[2]:null,
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
                $val = (isset(self::$table_rows[$v])?self::$table_rows[$v]['id']:$v); // FIX it
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
            $ret = DB::SelectLimit('SELECT '.$fields.' FROM'.$par['sql'].$par['order'], $limit['numrows'], $limit['offset'], $par['vals']);
        }
        $records = array();
        self::init($tab);
        if (!empty($cols)) {
            foreach($cols as $k=>$v) {
                if (isset(self::$hash[$v])) $cols[$k] = self::$table_rows[self::$hash[$v]];
                elseif (isset(self::$table_rows[$v])) $cols[$k] = self::$table_rows[$v];
                else unset($cols[$k]);
            }
        } else
            $cols = self::$table_rows;
        while ($row = $ret->FetchRow()) {
            $r = array( 'id'=>$row['id'],
                        ':active'=>$row['active'],
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
        if(is_array($r) && isset($r['id']))
		$id = $r['id'];
	else $id = '';
//      if (isset($cache[$tab.'__'.$id])) return $cache[$tab.'__'.$id];
//      $r = self::get_record($tab, $id);
        $or_started = false;
        $or_result = false;
		self::init($tab);
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
			if (!isset($r[$k]) && $k[strlen($k)-1]==']') {
				list($field, $sub_field) = explode('[', trim($k, ']'));
				self::init($tab);
				$sub_tab = self::$table_rows[self::$hash[$field]]['ref_table'];
				if (!isset($r[$field])) $r[$field] = 0;
				if (is_array($r[$field])) {
					$r[$k] = array();
					foreach ($r[$field] as $f_v)
						$r[$k][] = self::get_value($sub_tab, $f_v, $sub_field);
				} else {
					if ($r[$field]) $r[$k] = self::get_value($sub_tab, $r[$field], $sub_field);
					else $r[$k] = '';
					if (substr($r[$k], 0, 2)=='__') $r[$k] = self::decode_multi($r[$k]); // FIXME need better check
				}
			}
            $result = false;
            $k = strtolower($k);
            if (!isset($r[$k])) $r[$k] = '';
            if (is_array($r[$k])) $result = in_array($v, $r[$k]);
            else switch ($operator) {
                case '>': $result = ($r[$k] > $v); break;
                case '>=': $result = ($r[$k] >= $v); break;
                case '<': $result = ($r[$k] < $v); break;
                case '<=': $result = ($r[$k] <= $v); break;
                case '==': $result = ($r[$k] == $v);
            }
            if ($negative) $result = !$result;
            if ($or_started) $or_result |= $result;
            else if (!$result) return $cache[$tab.'__'.$id] = false;
        }
        if ($or_started && !$or_result) return $cache[$tab.'__'.$id] = false;
        return $cache[$tab.'__'.$id] = true;
    }
	public static function decode_access($str, $manage_permissions=false) {
		if (is_numeric($str)) return $str;
		// FIXME should be moved to CRM_Contacts, but only after editor is ready and there's synatx to retrieve all needed info
		if ($manage_permissions) {
			if ($str=='USER_ID') return __('User Login');
			if (class_exists('CRM_ContactsCommon')) { 
				$me = CRM_ContactsCommon::get_my_record();
				if ($str=='USER') return __('User Contact');
				if ($str=='USER_COMPANY') return __('User Company');
			}
		} else {
			if ($str=='USER_ID') return Acl::get_user();
			if (class_exists('CRM_ContactsCommon')) {
				$me = CRM_ContactsCommon::get_my_record();
				if ($str=='USER') return $me['id']?$me['id']:-1;
				if ($str=='USER_COMPANY') return (isset($me['company_name']) && $me['company_name'])?$me['company_name']:-1;
			}
		}
		return $str;
	}
	public static function parse_access_crits($str, $manage_permissions=false) {
		$ret = unserialize($str);
		foreach ($ret as $k=>$v) {
			if (!is_array($v)) {
				$ret[$k] = self::decode_access($v, $manage_permissions);
			} else {
				foreach ($v as $kw=>$w) {
					$ret[$k][$kw] = self::decode_access($w, $manage_permissions);
				}
			}
		}
		return $ret;
	}
	public static function add_default_access($tab) {
		Utils_RecordBrowserCommon::add_access($tab, 'view', 'ACCESS:employee');
		Utils_RecordBrowserCommon::add_access($tab, 'add', 'ACCESS:employee');
		Utils_RecordBrowserCommon::add_access($tab, 'edit', 'ACCESS:employee');
		Utils_RecordBrowserCommon::add_access($tab, 'delete', 'ACCESS:employee');
	}
	
	public static function field_deny_access($tab, $fields, $action='', $clearance=null) {
		if (!self::check_table_name($tab, false, false)) return;
		if (!is_array($fields)) $fields = array($fields);
		$sql = '';
		$vals = array();
		if ($clearance!=null) {
			$sql .= ' WHERE NOT EXISTS (SELECT * FROM '.$tab.'_access_clearance WHERE rule_id=acs.id AND '.implode(' AND ',array_fill(0, count($clearance), 'clearance!=%s')).')';
			$vals = array_values($clearance);
		}
		if ($action!='') {
			if ($sql) $sql .= ' AND ';
			else $sql .= ' WHERE ';
			$sql .= 'action=%s';
			$vals[] = $action;
		}
		$sql = 'SELECT id, id FROM '.$tab.'_access AS acs'.$sql;
		$ids = DB::GetAssoc($sql, $vals);
		foreach ($fields as $f) {
			$f = self::get_field_id($f);
			foreach ($ids as $id)
				DB::Execute('INSERT INTO '.$tab.'_access_fields (rule_id, block_field) VALUES (%d, %s)', array($id, $f));
		}
	}
	public static function wipe_access($tab) {
		if (!self::check_table_name($tab, false, false)) return;
		DB::Execute('DELETE FROM '.$tab.'_access_clearance');
		DB::Execute('DELETE FROM '.$tab.'_access_fields');
		DB::Execute('DELETE FROM '.$tab.'_access');
	}
	public static function delete_access($tab, $id) {
		if (!self::check_table_name($tab, false, false)) return;
		DB::Execute('DELETE FROM '.$tab.'_access_clearance WHERE rule_id=%d', array($id));
		DB::Execute('DELETE FROM '.$tab.'_access_fields WHERE rule_id=%d', array($id));
		DB::Execute('DELETE FROM '.$tab.'_access WHERE id=%d', array($id));
	}
	public static function add_access($tab, $action, $clearance, $crits=array(), $blocked_fields=array()) {
		if (!self::check_table_name($tab, false, false)) return;
		DB::Execute('INSERT INTO '.$tab.'_access (crits, action) VALUES (%s, %s)', array(serialize($crits), $action));
        $rule_id = DB::Insert_ID($tab.'_access','id');
		if (!is_array($clearance)) $clearance = array($clearance);
		foreach ($clearance as $c)
			DB::Execute('INSERT INTO '.$tab.'_access_clearance (rule_id, clearance) VALUES (%d, %s)', array($rule_id, $c));
		foreach ($blocked_fields as $f)
			DB::Execute('INSERT INTO '.$tab.'_access_fields (rule_id, block_field) VALUES (%d, %s)', array($rule_id, $f));
	}
	public static function update_access($tab, $id, $action, $clearance, $crits=array(), $blocked_fields=array()) {
		DB::Execute('UPDATE '.$tab.'_access SET crits=%s, action=%s WHERE id=%d', array(serialize($crits), $action, $id));
		if (!is_array($clearance)) $clearance = array($clearance);
		DB::Execute('DELETE FROM '.$tab.'_access_clearance WHERE rule_id=%d', array($id));
		DB::Execute('DELETE FROM '.$tab.'_access_fields WHERE rule_id=%d', array($id));
		foreach ($clearance as $c)
			DB::Execute('INSERT INTO '.$tab.'_access_clearance (rule_id, clearance) VALUES (%d, %s)', array($id, $c));
		foreach ($blocked_fields as $f)
			DB::Execute('INSERT INTO '.$tab.'_access_fields (rule_id, block_field) VALUES (%d, %s)', array($id, $f));
	}
    public static function get_access($tab, $action, $record=null){
        if (self::$admin_access && Base_AclCommon::i_am_admin()) {
            $ret = true;
        } elseif (isset($record[':active']) && !$record[':active'] && ($action=='edit' || $action=='delete' || $action=='clone')) {
			return false;
		} else {
			static $cache = array();
			if (!isset($cache[$tab])) {
				self::check_table_name($tab);
				$user_clearance = Base_AclCommon::get_clearance();
				
				$r = DB::Execute('SELECT * FROM '.$tab.'_access AS acs WHERE NOT EXISTS (SELECT * FROM '.$tab.'_access_clearance WHERE rule_id=acs.id AND '.implode(' AND ',array_fill(0, count($user_clearance), 'clearance!=%s')).')', array_values($user_clearance));
				$crits = array('view'=>null, 'edit'=>null, 'delete'=>null, 'add'=>null, 'print'=>null, 'export'=>null);
				$crits_raw = array('view'=>array(), 'edit'=>array(), 'delete'=>array(), 'add'=>array(), 'print'=>array(), 'export'=>array());
				$fields = array();
				while ($row = $r->FetchRow()) {
					$fields[$row['id']] = array();
					$new = self::parse_access_crits($row['crits']);
					$crits_raw[$row['action']][$row['id']] = $new;
					$crits[$row['action']] = self::merge_crits($crits[$row['action']], $new, true);
				}
				$r = DB::Execute('SELECT * FROM '.$tab.'_access_fields');
				while ($row = $r->FetchRow()) {
					$fields[$row['rule_id']][$row['block_field']] = $row['block_field'];
				}
				$cache[$tab]['crits'] = $crits;
				$cache[$tab]['crits_raw'] = $crits_raw;
				$cache[$tab]['fields'] = $fields;
			} else {
				$crits = $cache[$tab]['crits'];
				$crits_raw = $cache[$tab]['crits_raw'];
				$fields = $cache[$tab]['fields'];
			}
			if ($action=='browse') {
				return $crits['view']!==null?(empty($crits['view'])?true:$crits['view']):false;
			}
			$ret = false;
			$blocked_fields = array();
			if ($action!='browse' && $action!='clone') {
				foreach ($crits_raw[$action] as $rule_id=>$c) {
					if (!self::check_record_against_crits($tab, $record, $c))
						continue;
					if (!$ret) {
						$ret = true;
						$blocked_fields = $fields[$rule_id];
					} else {
						foreach ($blocked_fields as $f=>$v)
							if (!isset($fields[$rule_id][$f])) unset($blocked_fields[$f]);
					}
				}
			}
        }
        if ($action!=='browse' && $action!=='delete') {
            self::init($tab);
            if ($ret===false) return false;
            if ($ret===true) $ret = array();
            foreach (self::$table_rows as $field=>$args)
                if (!isset($ret[$args['id']])) {
					if (isset($blocked_fields[$args['id']]))
						$ret[$args['id']] = false;
					else
						$ret[$args['id']] = true;
				}
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
        return array(   'created_on'=>$created['created_on'],'created_by'=>$created['created_by'],
                        'edited_on'=>$edited['edited_on'],'edited_by'=>$edited['edited_by'],
                        'id'=>$id);
    }
	public static function get_fav_button($tab, $id, $isfav = null) {
		$tag_id = 'rb_fav_button_'.$tab.'_'.$id;
		return '<span id="'.$tag_id.'">'.self::get_fav_button_tags($tab, $id, $isfav).'</span>';
	}
	public static function get_fav_button_tags($tab, $id, $isfav = null) {
        self::check_table_name($tab);
		$star_on = Base_ThemeCommon::get_template_file('Utils_RecordBrowser','star_fav.png');
		$star_off = Base_ThemeCommon::get_template_file('Utils_RecordBrowser','star_nofav.png');
		load_js('modules/Utils/RecordBrowser/favorites.js');
		if ($isfav===null) $isfav = DB::GetOne('SELECT '.$tab.'_id FROM '.$tab.'_favorite WHERE user_id=%d AND '.$tab.'_id=%d', array(Acl::get_user(), $id));
		$tag_id = 'rb_fav_button_'.$tab.'_'.$id;
		return '<a '.Utils_TooltipCommon::open_tag_attrs(($isfav?__('This item is on your favorites list<br>Click to remove it from your favorites'):__('Click to add this item to favorites'))).' onclick="utils_recordbrowser_set_favorite('.($isfav?0:1).',\''.$tab.'\','.$id.',\''.$tag_id.'\')" href="javascript:void(0);"><img style="width: 14px; height: 14px;" border="0" src="'.($isfav==false?$star_off:$star_on).'" /></a>';
	}
    public static function set_favs($tab, $id, $state) {
        self::check_table_name($tab);
		if ($state) {
			if (DB::GetOne('SELECT * FROM '.$tab.'_favorite WHERE user_id=%d AND '.$tab.'_id=%d', array(Acl::get_user(), $id))) return;
			DB::Execute('INSERT INTO '.$tab.'_favorite (user_id, '.$tab.'_id) VALUES (%d, %d)', array(Acl::get_user(), $id));
		} else {
			DB::Execute('DELETE FROM '.$tab.'_favorite WHERE user_id=%d AND '.$tab.'_id=%d', array(Acl::get_user(), $id));
		}
    }
    public static function get_html_record_info($tab, $id){
        if (is_numeric($id)) $info = Utils_RecordBrowserCommon::get_record_info($tab, $id);
        else $info = $id;
        if (isset($info['id'])) $id = $info['id'];

        // If CRM Module is not installed get user login only
        $created_by = Base_UserCommon::get_user_label($info['created_by']);
        $htmlinfo=array(
                    __('Record ID').':'=>$id,
                    __('Created by').':'=>$created_by,
                    __('Created on').':'=>Base_RegionalSettingsCommon::time2reg($info['created_on'])
                        );
        if ($info['edited_on']!==null) {
            $htmlinfo=$htmlinfo+array(
                    __('Edited by').':'=>$info['edited_by']!==null?Base_UserCommon::get_user_label($info['edited_by']):'',
                    __('Edited on').':'=>Base_RegionalSettingsCommon::time2reg($info['edited_on'])
                        );
        }

        return  Utils_TooltipCommon::format_info_tooltip($htmlinfo);
    }
    public static function get_record($tab, $id, $htmlspecialchars=true) {
        if (!is_numeric($id)) return null;
        self::init($tab);
        if (isset($id)) {
            self::check_table_name($tab);
            $row = DB::GetRow('SELECT * FROM '.$tab.'_data_1 WHERE id=%d', array($id));
            $record = array('id'=>$id);
            if (!isset($row['active'])) return null;
            foreach(array('created_by','created_on') as $v)
                $record[$v] = $row[$v];
            $record[':active'] = $row['active'];
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
        $current = DB::GetOne('SELECT active FROM '.$tab.'_data_1 WHERE id=%d',array($id));
		if ($current==($state?1:0)) return;
	$values = self::record_processing($tab, self::get_record($tab, $id), $state?'restore':'delete');
	if($values===false) return;
        Utils_WatchdogCommon::new_event($tab,$id,$state?'R':'D');
        DB::Execute('UPDATE '.$tab.'_data_1 SET active=%d WHERE id=%d',array($state?1:0,$id));
        DB::Execute('INSERT INTO '.$tab.'_edit_history(edited_on, edited_by, '.$tab.'_id) VALUES (%T,%d,%d)', array(date('Y-m-d G:i:s'), Acl::get_user(), $id));
        $edit_id = DB::Insert_ID($tab.'_edit_history','id');
        DB::Execute('INSERT INTO '.$tab.'_edit_history_data(edit_id, field, old_value) VALUES (%d,%s,%s)', array($edit_id, 'id', ($state?'RESTORED':'DELETED')));

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
		if (empty($match[1])) return str_replace(' ','&nbsp;', $s);
        foreach($match[1] as $v) $content_no_wrap = str_replace($v, str_replace(' ','&nbsp;', $v), $content_no_wrap);
        return $content_no_wrap;
    }
    public static function get_new_record_href($tab, $def, $id='none', $check_defaults=true){
        self::check_table_name($tab);
        $x = ModuleManager::get_instance('/Base_Box|0');
        if (!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
        if (class_exists('Utils_RecordBrowser') && Utils_RecordBrowser::$clone_result!==null) {
            if (is_numeric(Utils_RecordBrowser::$clone_result)) $x->push_main('Utils/RecordBrowser','view_entry',array('view', Utils_RecordBrowser::$clone_result), array(Utils_RecordBrowser::$clone_tab));
            Utils_RecordBrowser::$clone_result = null;
        }
 		$def_key = $def;
    	if (is_array($check_defaults)) foreach ($check_defaults as $c) unset($def_key[$c]);
        $def_md5 = md5(serialize($def_key));
//      print_r($_REQUEST);
//      print('<br>'.$tab.' - '.$def_md5.' - '.$id.' - '.$check_defaults);
//      print('<hr>');
        if (isset($_REQUEST['__add_record_to_RB_table']) &&
                isset($_REQUEST['__add_record_id']) &&
                isset($_REQUEST['__add_record_def']) &&
                ($tab==$_REQUEST['__add_record_to_RB_table']) &&
                (!$check_defaults || $def_md5==$_REQUEST['__add_record_def']) &&
                ($id==$_REQUEST['__add_record_id'])) {
                unset($_REQUEST['__add_record_to_RB_table']);
                unset($_REQUEST['__add_record_id']);
                unset($_REQUEST['__add_record_def']);
                $x->push_main('Utils/RecordBrowser','view_entry',array('add', null, $def), array($tab));
                return array();
        }
        return array('__add_record_to_RB_table'=>$tab, '__add_record_id'=>$id, '__add_record_def'=>$def_md5);
    }
    public static function create_new_record_href($tab, $def, $id='none', $check_defaults=true, $multiple_defaults=false){
        if($multiple_defaults) {
            static $done = false;
            if($done) return Libs_LeightboxCommon::get_open_href('actionbar_rb_new_record');
            eval_js_once('actionbar_rb_new_record_deactivate = function(){leightbox_deactivate(\'actionbar_rb_new_record\');}');
            $th = Base_ThemeCommon::init_smarty();
            $cds = array();
            foreach ($def as $k=>$v) {
                    $cds[] = array( 'label'=>$k,
                                    'open'=>'<a OnClick="actionbar_rb_new_record_deactivate();'.Module::create_href_js(self::get_new_record_href($tab,$v['defaults'], $id, $check_defaults)).'">',
                                    'icon'=>$v['icon'],
                                    'close'=>'</a>'
                                    );
            }
            $th->assign('custom_defaults', $cds);
            ob_start();
            Base_ThemeCommon::display_smarty($th,'Utils_RecordBrowser','new_record_leightbox');
            $panel = ob_get_clean();
            Libs_LeightboxCommon::display('actionbar_rb_new_record',$panel,__('New record'));
            $done = true;
            return Libs_LeightboxCommon::get_open_href('actionbar_rb_new_record');
        } else 
            return Module::create_href(self::get_new_record_href($tab,$def, $id, $check_defaults));
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
            $x->push_main('Utils/RecordBrowser','view_entry_with_REQUEST',array($action, $id, array(), true, $_REQUEST),array($tab));
            return array();
        }
        return array('__jump_to_RB_table'=>$tab, '__jump_to_RB_record'=>$id, '__jump_to_RB_action'=>$action);
    }
    public static function create_record_href($tab, $id, $action='view',$more=array()){
        if(MOBILE_DEVICE) {
            $cap = DB::GetOne('SELECT caption FROM recordbrowser_table_properties WHERE tab=%s',array($tab));
            return mobile_stack_href(array('Utils_RecordBrowserCommon','mobile_rb_view'),array($tab,$id),$cap);
        }
        return Module::create_href(self::get_record_href_array($tab,$id,$action)+$more);
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
			$ret = '';
			$tip = '';
			self::$del_or_a = '';
            $has_access = self::get_access($tab, 'view', self::get_record($tab, $id));
            $is_active = DB::GetOne('SELECT active FROM '.$tab.'_data_1 WHERE id=%d',array($id));

			if (!$is_active) {
				$tip = __('This record was deleted from the system, please edit current record or contact system administrator');
				$ret = '<del>';
				self::$del_or_a = '</del>';                    
			}
            if (!$has_access) {
                $tip = ($tip?'<br>':'').__('You don\'t have permission to view this record.');
            }
            $tip = $tip ? Utils_TooltipCommon::open_tag_attrs($tip) : '';
            if (!$nolink) {
                if($has_access) {
                    $href = self::create_record_href($tab, $id, $action);
                    $ret = '<a '.$tip.' '.$href.'>'.$ret;
                    self::$del_or_a .= '</a>';
                } else {
                    $ret = '<span '.$tip.'>'.$ret;
                    self::$del_or_a .= '</span>';
                }
            }
        }
        return $ret;
    }
    public static function record_link_close_tag(){
        return self::$del_or_a;
    }
    public static function create_linked_label($tab, $cols, $id, $nolink=false){
        if (!is_numeric($id)) return '';
        if (!is_array($cols))
            $cols = explode('|', $cols);
        self::init($tab);
        $vals = array();
        foreach ($cols as $k=>$col) {
            if (isset(self::$table_rows[$col])) $cols[$k] = self::$table_rows[$col]['id'];
            elseif (!isset(self::$hash[$col])) trigger_error('Unknown column name: '.$col,E_USER_ERROR);
		}
        $row = DB::GetRow('SELECT f_'.implode(', f_',$cols).' FROM '.$tab.'_data_1 WHERE id=%d', array($id));
        foreach ($cols as $col) {
			$val = & $row['f_'.$col];
            if (isset($val) && $val)
                $vals[] = $val;
        }
        return self::record_link_open_tag($tab, $id, $nolink) . 
                implode(' ', $vals ) . self::record_link_close_tag();
    }
    public static function create_default_linked_label($tab, $id, $nolink=false, $table_name=true){
        if (!is_numeric($id)) return '';
        $rec = self::get_record($tab,$id);
        if(!$rec) return '';
        $tpro = DB::GetRow('SELECT caption,description_callback FROM recordbrowser_table_properties WHERE tab=%s',array($tab));
        if(!$tpro) return '';
        $cap = _V($tpro['caption']);
        if(!$cap) $cap = $tab;
        $descr = $tpro['description_callback'];
        if($descr) {
            if(preg_match('/::/',$descr)) {
                $descr = explode('::',$descr);
            }
            if(!is_callable($descr))
                $descr = '';
        }
        if($descr)
            $label = call_user_func($descr,$rec,$nolink);
        else {
            $field = DB::GetOne('SELECT field FROM '.$tab.'_field WHERE (type=\'text\' OR type=\'commondata\' OR type=\'integer\' OR type=\'date\') AND required=1 AND visible=1 AND active=1 ORDER BY position');
            if(!$field)
                $label = ($table_name?$cap.': ':'').$id;
            else
                $label = ($table_name?$cap.': ':'').self::get_val($tab,$field,$rec,$nolink);
        }
        $ret = self::record_link_open_tag($tab, $id, $nolink).$label.self::record_link_close_tag();
        return $ret;
    }
    public static function create_linked_label_r($tab, $cols, $r, $nolink=false){
        if (!is_array($cols))
            $cols = array($cols);
        $open_tag = $close_tag = '';
        if (isset($r['id']) && is_numeric(($r['id']))) {
            $open_tag = self::record_link_open_tag($tab, $r['id'], $nolink);
            $close_tag = self::record_link_close_tag();
        }
        self::init($tab);
        $vals = array();
        foreach ($cols as $col) {
            if (isset(self::$table_rows[$col]))
                $col = self::$table_rows[$col]['id'];
            elseif (!isset(self::$hash[$col]))
                trigger_error('Unknown column name: ' . $col, E_USER_ERROR);
            if ($r[$col])
                $vals[] = $r[$col];
        }

        return $open_tag . implode(' ', $vals) . $close_tag;
    }
    public static function record_bbcode($tab, $fields, $text, $param, $opt) {
        if (!is_numeric($param)) {
            $parts = explode(' ', $text);
            $crits = array();
            foreach ($parts as $k=>$v) {
                $v = DB::Concat(DB::qstr('%'),DB::qstr($v),DB::qstr('%'));
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
                    $v = DB::Concat(DB::qstr('%'),DB::qstr($v),DB::qstr('%'));
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
        return Utils_BBCodeCommon::create_bbcode(null, $param, $text, __('Record not found'));
    }
    public static function applet_settings($some_more = array()) {
        $some_more = array_merge($some_more,array(
            array('label'=>__('Actions'),'name'=>'actions_header','type'=>'header'),
            array('label'=>__('Info'),'name'=>'actions_info','type'=>'checkbox','default'=>true),
            array('label'=>__('View'),'name'=>'actions_view','type'=>'checkbox','default'=>false),
            array('label'=>__('Edit'),'name'=>'actions_edit','type'=>'checkbox','default'=>true),
            array('label'=>__('Delete'),'name'=>'actions_delete','type'=>'checkbox','default'=>false),
            array('label'=>__('View edit history'),'name'=>'actions_history','type'=>'checkbox','default'=>false),
        ));
        return $some_more;
    }

	/**
	 *	Returns older version of te record.
	 *
	 *	@param RecordSet - name of the recordset
	 *	@param Record ID - ID of the record
	 *	@param Revision ID - RB will backtrace all edits on that record down-to and including edit with this ID
	 */
	public static function get_record_revision($tab, $id, $rev_id) {
		self::init($tab);
		$r = self::get_record($tab, $id);
		$ret = DB::Execute('SELECT id, edited_on, edited_by FROM '.$tab.'_edit_history WHERE '.$tab.'_id=%d AND id>=%d ORDER BY edited_on DESC, id DESC',array($id, $rev_id));
		while ($row = $ret->FetchRow()) {
			$changed = array();
			$ret2 = DB::Execute('SELECT * FROM '.$tab.'_edit_history_data WHERE edit_id=%d',array($row['id']));
			while($row2 = $ret2->FetchRow()) {
				$k = $row2['field'];
				$v = $row2['old_value'];
				if ($k=='id') $r['active'] = ($v!='DELETED');
				else {
					if (!isset(self::$hash[$k])) continue;
					$r[$k] = $v;
				}
			}
		}
		return $r;
	}
	
	public static function get_edit_details($tab, $rid, $edit_id,$details=true) {
		return self::get_edit_details_modify_record($tab, $rid, $edit_id,$details);
	}

	public static function get_edit_details_modify_record($tab, & $rid, $edit_id,$details=true) {
		self::init($tab);
		if (is_numeric($rid)) {
			$prev_rev = DB::GetOne('SELECT MIN(id) FROM '.$tab.'_edit_history WHERE '.$tab.'_id=%d AND id>%d', array($rid, $edit_id));
			$r = self::get_record_revision($tab, $rid, $prev_rev);
		} else $r = $rid;
		$edit_info = DB::GetRow('SELECT * FROM '.$tab.'_edit_history WHERE id=%d',array($edit_id));
		$event_display = array('what'=>'Error, Invalid event: '.$edit_id);
		if (!$edit_info) return $event_display;

		$event_display = array(
							'who'=>Base_UserCommon::get_user_label($edit_info['edited_by']),
							'when'=>Base_RegionalSettingsCommon::time2reg($edit_info['edited_on']),
							'what'=>array()
						);
		if (!$details) return $event_display;
		$edit_details = DB::GetAssoc('SELECT field, old_value FROM '.$tab.'_edit_history_data WHERE edit_id=%d',array($edit_id));
		foreach ($r as $k=>$v) {
			if (isset(self::$hash[$k]) && self::$table_rows[self::$hash[$k]]['type']=='multiselect')
				$r[$k] = self::decode_multi($r[$k]); // We have to decode all fields, because access and some display relay on it, regardless which field changed
		}
		$r2 = $r;
		self::init($tab); // because get_user_label messes up
		foreach ($edit_details as $k=>$v) {
			$k = self::get_field_id($k); // failsafe
			if (!isset(self::$hash[$k])) continue;
			if (self::$table_rows[self::$hash[$k]]['type']=='multiselect') {
				$v = $edit_details[$k] = self::decode_multi($v);
//				$r[$k] = self::decode_multi($r[$k]);
			}
			$r2[$k] = $v;
		}
		$access = self::get_access($tab,'view',$r);
		foreach ($edit_details as $k=>$v) {
			$k = self::get_field_id($k); // failsafe
			if (!isset(self::$hash[$k])) continue;
			if (!$access[$k]) continue;
			self::init($tab);
			$field = self::$hash[$k];
			$params = self::$table_rows[$field];
			$event_display['what'][] = array(
										_V($field),
										self::get_val($tab, $field, $r2, true, $params),
										self::get_val($tab, $field, $r, true, $params)
									);
		}
		$r = $r2;
		foreach ($edit_details as $k=>$v) {
			$k = self::get_field_id($k); // failsafe
			if (!isset(self::$hash[$k])) continue;
			if (self::$table_rows[self::$hash[$k]]['type']=='multiselect') {
				$r[$k] = self::encode_multi($r[$k]);
			}
		}
		return $event_display;
	}

    public static function get_edit_details_label($tab, $rid, $edit_id,$details = true) {
		$ret = self::watchdog_label($tab, '', $rid, array('E_'.$edit_id), '', $details);
		return $ret['events'];
	}
	
    public static function watchdog_label($tab, $cat, $rid, $events = array(), $label = null, $details = true) {
        $ret = array('category'=>$cat);
        if ($rid!==null) {
            $r = self::get_record($tab, $rid);
            if ($r===null) return null;
			if (!self::get_access($tab, 'view', $r)) return null;
            if (is_array($label)) $label = call_user_func($label, $r, true);
            elseif ($label) $label = $r[$label];
            $ret['title'] = Utils_RecordBrowserCommon::record_link_open_tag($tab, $rid).$label;
            $close = Utils_RecordBrowserCommon::record_link_close_tag();
            $ret['title'] .= $close;
            $ret['view_href'] = Utils_RecordBrowserCommon::create_record_href($tab, $rid);
            $events_display = array();
            $events = array_reverse($events);
            $other_events = array();
            $header = false;
            foreach ($events as $v) {
        	if (count($events_display)>20) {
        		$other_events[__('And more...')] = 1;
        		break;
        	}
                $param = explode('_', $v);
                switch ($param[0]) {
                    case 'C':   $what = 'Created';
                    case 'D':   if (!isset($what)) $what = 'Deleted';
                    case 'R':   if (!isset($what)) $what = 'Restored';
								$event_display = array(
									'who'=> Base_UserCommon::get_user_label($r['created_by']),
									'when'=>Base_RegionalSettingsCommon::time2reg($r['created_on']),
									'what'=>_V($what)
									);
                                break;
                    case 'E':   $event_display = self::get_edit_details_modify_record($tab, $r['id'], $param[1] ,$details);
				if (!empty($event_display['what'])) $header = true;
                                break;

                    case 'N':   $event_display = false;
                                switch($param[1]) {
                                    case '+':
                                        $action = __('Note added');
                                        break;
                                    case '~':
                                        $action = __('Note edited');
                                        break;
                                    case '-':
                                        $action = __('Note deleted');
                                        break;
                                    case 'r':
                                        $action = __('Note restored');
                                        break;
                                    case 'p':
                                        $action = __('Note pasted');
                                        break;
                                    default:
                                	if (!isset($other_events[$param[1]])) $other_events[$param[1]] = 0;
                                	$other_events[$param[1]]++;
                                	$event_display = null;
                                	break;
                                }
                                if($event_display===false)
                                    $event_display = array('what'=>$action);
                                break;
                    default:    $event_display = array('what'=>_V($v));
                }
                if ($event_display) $events_display[] = $event_display;
            }
            foreach ($other_events as $k=>$v)
		$events_display[] = array('what'=>_V($k).($v>1?' ['.$v.']':''));

			$theme = Base_ThemeCommon::init_smarty();

			if ($header) {
				$theme->assign('header', array(__('Field'), __('Old value'), __('New value')));
			}

			$theme->assign('events', $events_display);

			ob_start();
			Base_ThemeCommon::display_smarty($theme,'Utils_RecordBrowser','changes_list');
			$output = ob_get_clean();

			$ret['events'] = $output;
        }
        return $ret;
    }
    public static function get_tables($tab){
        return array(   $tab.'_callback',
                        $tab.'_recent',
                        $tab.'_favorite',
                        $tab.'_edit_history_data',
                        $tab.'_edit_history',
                        $tab.'_field',
                        $tab.'_data_1');
    }

    public static function applet_new_record_button($tab, $defaults = array()) {
		if (!self::get_access($tab, 'add')) return '';
        return '<a '.Utils_TooltipCommon::open_tag_attrs(__('New record')).' '.Utils_RecordBrowserCommon::create_new_record_href($tab,$defaults).'><img src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','add.png').'" border="0"></a>';
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
            if (!is_numeric($id)) return false;
            Utils_RecordBrowserCommon::check_table_name($tab);
            if (!self::get_access($tab,'browse')) return false;
			if (!DB::GetOne('SELECT id FROM '.$tab.'_data_1 WHERE id=%d', $id)) return false;
            unset($_REQUEST['__jump_to_RB_record']);
            unset($_REQUEST['__jump_to_RB_table']);
            unset($_REQUEST['__jump_to_RB_action']);
            $x = ModuleManager::get_instance('/Base_Box|0');
            if (!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
            $x->push_main('Utils/RecordBrowser','view_entry_with_REQUEST',array($action, $id, array(), true, $_REQUEST),array($tab));
            return true;
        }
        return false;
    }

    public static function cut_string($str, $len, $tooltip=true, &$cut=null) {
		return $str;
        if ($len==-1) return $str;
        $ret = '';
        $strings = explode('<br>',$str);
        foreach ($strings as $str) {
            if ($ret) $ret .= '<br>';
            $label = '';
            $i = 0;
            $curr_len = 0;
            $tags = array();
            $inside = 0;
			preg_match_all('/./u', $str, $a);
			$a = $a[0];
            $strlen = count($a);
            while ($curr_len<=$len && $i<$strlen) {
                if ($a[$i] == '&' && !$inside) {
                    $e = -1;
                    if (isset($a[$i+3]) && $a[$i+3]==';') $e = 3;
                    elseif (isset($a[$i+4]) && $a[$i+4]==';') $e = 4;
                    elseif (isset($a[$i+5]) && $a[$i+5]==';') $e = 5;
                    if ($e!=-1) {
                        $hsc = implode("", array_slice($a, $i, $e+1));
                        if ($hsc=='&nbsp;' || strlen(htmlspecialchars_decode($hsc))==1) {
                            $label .= implode("", array_slice($a, $i, $e));
                            $i += $e;
                            $curr_len++;
                        }
                    }
                } elseif ($a[$i] == '<' && !$inside) {
                    $inside = 1;
                    if (isset($a[$i+1]) && $a[$i+1] == '/') {
                        if (!empty($tags)) array_pop($tags);
                    } else {
                        $j = 1;
                        $next_tag = '';
                        while ($i+$j<=$strlen && $a[$i+$j]!=' ' && $a[$i+$j]!='>' && $a[$i+$j]!='/') {
                            $next_tag .= $a[$i+$j];
                            $j++;
                        }
                        $tags[] = $next_tag;
                    }
				} elseif ($a[$i] == '"' && $inside==1) {
					$inside = 2;
				} elseif ($a[$i] == '"' && $inside==2) {
					$inside = 1;
                } elseif ($a[$i] == '>' && $inside==1) {
                    if ($i>0 && $a[$i-1] == '/') array_pop($tags);
                    $inside = 0;
                } elseif (!$inside) {
					$curr_len++;
				}
                $label .= $a[$i];
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
	
	public static function autoselect_label($id, $def) {
		$param = $def[3];
        $param = explode(';', $param);
        $ref = explode('::', $param[0]);
		return self::create_default_linked_label($ref[0], $id, true, false);
	}

    public static function automulti_suggestbox($str, $tab, $crits, $f_callback, $params) {
        $param = explode(';', $params);
        $ref = explode('::', $param[0]);
        $fields = explode('|', $ref[1]);
        $crits2 = array();
        $str = DB::Concat(DB::qstr('%'),DB::qstr($str),DB::qstr('%'));
        $op = '(';
        foreach ($fields as $f) {
            $crits2[$op.'~"'.self::get_field_id($f)] = $str;
            $op = '|';
        }
        $crits = self::merge_crits($crits,$crits2);
        $records = self::get_records($ref[0], $crits, array(), array(), 10);
        $ret = array();
        foreach ($records as $r) {
			if ($f_callback) $ret[$r['id']] = call_user_func($f_callback, $r['id'], array($tab, $crits, $f_callback, $params));
			else $ret[$r['id']] = self::create_default_linked_label($ref[0], $r['id'], true, false);
		}
        return $ret;
    }

/**
 * Function to manipulate clipboard pattern
 * @param string $tab recordbrowser table name
 * @param string|null $pattern pattern, or when it's null the pattern stays the same, only enable state changes
 * @param bool $enabled new enabled state of clipboard pattern
 * @param bool $force make it true to allow any changes or overwrite when clipboard pattern exist
 * @return bool true if any changes were made, false otherwise
 */
    public static function set_clipboard_pattern($tab, $pattern, $enabled = true, $force = false) {
        $ret = null;
        $enabled = $enabled ? 1 : 0;
        $r = self::get_clipboard_pattern($tab, true);
        /* when pattern exists and i can overwrite it... */
        if($r && $force) {
            /* just change enabled state, when pattern is null */
            if($pattern === null) {
                $ret = DB::Execute('UPDATE recordbrowser_clipboard_pattern SET enabled=%d WHERE tab=%s',array($enabled,$tab));
            } else {
                /* delete if it's not necessary to hold any value */
                if($enabled == 0 && strlen($pattern) == 0) $ret = DB::Execute('DELETE FROM recordbrowser_clipboard_pattern WHERE tab = %s', array($tab));
                /* or update values */
                else $ret = DB::Execute('UPDATE recordbrowser_clipboard_pattern SET pattern=%s,enabled=%d WHERE tab=%s',array($pattern,$enabled,$tab));
            }
        }
        /* there is no such pattern in database so create it*/
        if(!$r) {
            $ret = DB::Execute('INSERT INTO recordbrowser_clipboard_pattern values (%s,%s,%d)',array($tab, $pattern, $enabled));
        }
        if($ret) return true;
        return false;
    }

/**
 * Returns clipboard pattern string only if it is enabled. If 'with_state' is true return value is associative array with pattern and enabled keys.
 * @param string $tab name of RecordBrowser table
 * @param bool $with_state return also state of pattern
 * @return string|array string by default, array when with_state=true
 */
    public static function get_clipboard_pattern($tab, $with_state = false) {
        if($with_state) {
            $ret = DB::GetArray('SELECT pattern,enabled FROM recordbrowser_clipboard_pattern WHERE tab=%s', array($tab));
            if(sizeof($ret)) return $ret[0];
        }
        return DB::GetOne('SELECT pattern FROM recordbrowser_clipboard_pattern WHERE tab=%s AND enabled=1', array($tab));
    }
	
	public static function get_field_tooltip($label) {
		$args = func_get_args();
		array_shift($args);
		return Utils_TooltipCommon::ajax_create($label, array('Utils_RecordBrowserCommon', 'ajax_get_field_tooltip'), $args);
	}
	
	public static function ajax_get_field_tooltip() {
		$args = func_get_args();
		$type = array_shift($args);
		switch ($type) {
			case 'calculated':	return __('This field is not editable');
			case 'integer':		
			case 'float':		return __('Enter a numeric value in the text field');
			case 'checkbox':	return __('Click to switch between checked/unchecked state');
			case 'currency':	return __('Enter the amount in text field and select currency');
			case 'text':		$ret = __('Enter the text in the text field');
								if (isset($args[0]) && is_numeric($args[0])) $ret .= '<br />'.__('Maximum allowed length is %s characters', array('<b>'.$args[0].'</b>'));
								return $ret;
			case 'long text':	$example_text = __('Example text');
								return __('Enter the text in the text area').'<br />'.__('Maximum allowed length is %s characters', array('<b>400</b>')).'<br/>'.'<br/>'.
									__('BBCodes are supported:').'<br/>'.
									'[b]'.$example_text.'[/b] - <b>'.$example_text.'</b>'.'<br/>'.
									'[u]'.$example_text.'[/u] - <u>'.$example_text.'</u>'.'<br/>'.
									'[i]'.$example_text.'[/i] - <i>'.$example_text.'</i>';
			case 'date':		return __('Enter the date in your selected format').'<br />'.__('Click on the text field to bring up a popup Calendar that allows you to pick the date').'<br />'.__('Click again on the text field to close popup Calendar');
			case 'timestamp':	return __('Enter the date in your selected format and the time using select elements').'<br />'.__('Click on the text field to bring up a popup Calendar that allows you to pick the date').'<br />'.__('Click again on the text field to close popup Calendar').'<br />'.__('You can change 12/24-hour format in Control Panel, Regional Settings');
			case 'time':		return __('Enter the time using select elements').'<br />'.__('You can change 12/24-hour format in Control Panel, Regional Settings');
			case 'commondata':	$ret = __('Select value');
								if (isset($args[0])) $ret .= ' '.__('from %s table', array('<b>'.str_replace('_', '/', $args[0]).'</b>'));
								return $ret;
			case 'select':		$ret = __('Select one');
								if (isset($args[0])) {
									if (is_array($args[0])) {
										$cap = array();
										foreach ($args[0] as $t) $cap[] = '<b>'.self::get_caption($t).'</b>';
										$cap = implode(' '.__('or').' ',$cap);
									} $cap = '<b>'.self::get_caption($args[0]).'</b>';
									$ret .= ' '.__('of').' '.$cap;
								}
								if (isset($args[1])) {
									$val = implode('<br />&nbsp;&nbsp;&nbsp;',self::crits_to_words($args[0], $args[1]));
									if ($val) $ret .= ' '.__('for which').'<br />&nbsp;&nbsp;&nbsp;'.$val;
								}
								return $ret;
			case 'multiselect':	$ret = __('Select multiple');
								if (isset($args[0])) {
									if (is_array($args[0])) {
										$cap = array();
										foreach ($args[0] as $t) $cap[] = '<b>'.self::get_caption($t).'</b>';
										$cap = implode(' '.__('or').' ',$cap);
									} $cap = '<b>'.self::get_caption($args[0]).'</b>';
									$ret .= ' '.$cap;
								}
								if (isset($args[1])) {
									$val = implode('<br>&nbsp;&nbsp;&nbsp;',self::crits_to_words($args[0], $args[1]));
									if ($val) $ret .= ' '.__('for which').'<br />&nbsp;&nbsp;&nbsp;'.$val;
								}
								return $ret;
		}
		return __('No additional information');
	}
	
	public static $date_values = array('-1 year'=>'1 year back','-6 months'=>'6 months back','-3 months'=>'3 months back','-2 months'=>'2 months back','-1 month'=>'1 month back','-2 weeks'=>'2 weeks back','-1 week'=>'1 week back','-6 days'=>'6 days back','-5 days'=>'5 days back','-4 days'=>'4 days back','-3 days'=>'3 days back','-2 days'=>'2 days back','-1 days'=>'1 days back','today'=>'current day','+1 days'=>'1 days forward','+2 days'=>'2 days forward','+3 days'=>'3 days forward','+4 days'=>'4 days forward','+5 days'=>'5 days forward','+6 days'=>'6 days forward','+1 week'=>'1 week forward','+2 weeks'=>'2 weeks forward','+1 month'=>'1 month forward','+2 months'=>'2 months forward','+3 months'=>'3 months forward','+6 months'=>'6 months forward','+1 year'=>'1 year forward');
	public static function crits_to_words($tab, $crits, $inline_joints=true) {
		$ret = array();
		$or_started = false;
        foreach($crits as $k=>$v){
            self::init($tab, false);
			$next = '';
            $negative = $noquotes = $or_start = $or = false;
            $operator = '=';
            while (($k[0]<'a' || $k[0]>'z') && ($k[0]<'A' || $k[0]>'Z') && $k[0]!=':') {
				if ($k[0]=='!') $negative = true;
				if ($k[0]=='"') $noquotes = true;
				if ($k[0]=='(') $or_start = true;
				if ($k[0]=='|') $or = true;
				if ($k[0]=='<') $operator = '<';
				if ($k[0]=='>') $operator = '>';
				if ($k[0]=='~') $operator = DB::like();
				if ($k[1]=='=' && $operator!=DB::like()) {
					$operator .= '=';
					$k = substr($k, 2);
				} else $k = substr($k, 1);
				if (!isset($k[0])) trigger_error('Invalid criteria in build query: missing word. Crits:'.print_r($crits,true), E_USER_ERROR);
			}
			$or |= $or_start;

			if (!isset($r[$k]) && $k[strlen($k)-1]==']') {
				list($ref, $sub_field) = explode('[', trim($k, ']'));
				$args = self::$table_rows[self::$hash[$ref]];
				$commondata = $args['commondata'];
				if (!$commondata) {
					if (!isset($args['ref_table'])) trigger_error('Invalid crits, field '.$ref.' is not a reference; crits: '.print_r($crits,true),E_USER_ERROR);
					$is_multiselect = ($args['type']=='multiselect');
					$tab2 = $tab;
					$col2 = $k;
					$tab = $args['ref_table'];
					$k = $sub_field;
					
					$f_dis = self::$table_rows[self::$hash[$ref]]['name'];
					self::init($tab);
					$next .= '<b>'._V($f_dis).'</b> '.' is set to record with ';
				}
			}

            if ($k[0]!=':' && $k!=='id' && !isset(self::$table_rows[$k]) && (!isset(self::$hash[$k]) || !isset(self::$table_rows[self::$hash[$k]]))) continue; //failsafe

			if (!empty($ret)) {
				if ($or_start) $joint = 'and';
				elseif ($or) $joint = 'or';
				else $joint = 'and';
				if ($inline_joints) $next .= _V($joint).' ';
				else $ret[] = $joint;
			}

            if ($k[0]==':') {
                switch ($k) {
                    case ':Fav' :   		$next .= (!$v || ($negative && $v))?__('is not on %sfavorites%s', array('<b>','</b>')):__('is on %sfavorites%s', array('<b>','</b>'));
											$ret[] = $next;
											continue;
                    case ':Recent'  :   	$next .= (!$v || ($negative && $v))?__('wasn\'t %srecently%s viewed', array('<b>','</b>')):__('was %srecently%s viewed', array('<b>','</b>'));
											$ret[] = $next;
											continue;
                    case ':Created_on'  :	$next .= '<b>'.__('Created on').'</b> ';
											break;
                    case ':Created_by'  :	$next .= '<b>'.__('Created by').'</b> ';
											break;
                    case ':Edited_on'   :	$next .= '<b>'.__('Edited on').'</b> ';
											break;
				}
			} else {
				if ($k=='id') $next .= '<b>'.__('ID').'</b> ';
				else $next .= '<b>'._V(self::$table_rows[self::$hash[$k]]['name']).'</b> ';
			}
			$operand = '';
			if (!isset($tab2)) {
				if ($negative) $operand .= '<i>'.__('is not').'</i> ';
				else $operand .= __('is').' ';
			}
			if ($v==='') {
				$next .= $operand.__('empty');
			} else {
				switch ($operator) {
					case '<':	$operand .= __('smaller than'); break;
					case '<=':	$operand .= __('smaller or equal to'); break;
					case '>':	$operand .= __('greater than'); break;
					case '>=':	$operand .= __('greater or equal to'); break;
					case DB::like(): $operand .= __('contains'); break;
					default:	$operand .= __('equal to');
				}
				$operand = $operand.' ';
				$next .= $operand;
				
				switch ($k) {
					case 'id':			if (!is_array($v)) $v = array($v); break;
                    case ':Created_by': $v = array(is_numeric($v)?Base_UserCommon::get_user_login($v):$v); break;
					case ':Created_on': 
                    case ':Edited_on':  if (isset(self::$date_values[$v])) $v = array(self::$date_values[$v]);
										else $v = array(Base_RegionalSettingCommon::time2reg($v)); break;
					default: 			if (!is_array($v) && isset(self::$date_values[$v])) {
											$v = array(self::$date_values[$v]);
											break;
										}
										if (!is_array($v)) $v = array($v);
										$args = self::$table_rows[self::$hash[$k]];
										foreach ($v as $kk=>$vv) {
											if (!is_numeric($vv) && !$args['commondata'] && isset($args['ref_table'])) {
												$v[$kk] = $vv;
												continue;
											}
											$v[$kk] = self::get_val($tab, $k, array($k=>$vv), true);
										}	
				}
				foreach ($v as $kk=>$vv)
					$v[$kk] = '<b>'.$vv.'</b>';
				$next .= implode(' or ', $v);
			}

			$ret[] = $next;
			if (isset($tab2)) {
				$tab = $tab2;
				unset($tab2);
			}
		}
//		$ret[] = print_r($crits,true);
		return $ret;
	}

    ///////////////////////////////////////////
    // mobile devices

    public static function mobile_rb($table,array $crits=array(),array $sort=array(),$info=array(),$defaults=array()) {
        $_SESSION['rb_'.$table.'_defaults'] = $defaults;
        require_once('modules/Utils/RecordBrowser/mobile.php');
    }

    public static function mobile_rb_view($tab,$id) {
        if (Utils_RecordBrowserCommon::get_access($tab, 'browse')===false) {
            print(__('You are not authorised to browse this data.'));
            return;
        }
        self::add_recent_entry($tab, Acl::get_user() ,$id);
        $rec = self::get_record($tab,$id);

        $access = Utils_RecordBrowserCommon::get_access($tab, 'view',$rec);
        if (is_array($access))
            foreach ($access as $k=>$v)
                if (!$v) $rec[$k] = '';

        $cols = Utils_RecordBrowserCommon::init($tab);
        if(IPHONE) {
            print('<ul class="field">');
            foreach($cols as $k=>$col) {
                $val = Utils_RecordBrowserCommon::get_val($tab,$col['name'],$rec,true,$col);
                if($val==='') continue;
                print('<li>'._V($col['name']).': '.$val.'</li>'); // TRSL
            }
            print('</ul>');
        } else {
            foreach($cols as $k=>$col) {
                $val = Utils_RecordBrowserCommon::get_val($tab,$col['name'],$rec,true,$col);
                if($val==='') continue;
                print(_V($col['name']).': '.$val.'<br>'); // TRSL
            }
        }

        if(Utils_RecordBrowserCommon::get_access($tab, 'edit', $rec))
            print('<a '.(IPHONE?'class="button blue" ':'').mobile_stack_href(array('Utils_RecordBrowserCommon','mobile_rb_edit'), array($tab,$id),__('Record edition')).'>'.__('Edit').'</a>'.(IPHONE?'':'<br />'));

        if(Utils_RecordBrowserCommon::get_access($tab, 'delete', $rec))
            print('<a '.(IPHONE?'class="button red" ':'').mobile_stack_href(array('Utils_RecordBrowserCommon','mobile_rb_delete'), array($tab,$id),__('Record deletion')).'>'.__('Delete').'</a>'.(IPHONE?'':'<br />'));

    }

    public static function mobile_rb_edit($tab,$id) {
        if($id===false)
            $rec = array();
        else
            $rec = self::get_record($tab,$id);
        $cols = Utils_RecordBrowserCommon::init($tab);

        $defaults = array();
        if($id===false) {
            $mode = 'add';
            $access = array();
			$defaults = self::record_processing($tab, $defaults, 'adding');
        } else {
            $mode = 'edit';
            $access = Utils_RecordBrowserCommon::get_access($tab, 'view',$rec);
            if (is_array($access))
                foreach ($access as $k=>$v)
                    if (!$v) unset($rec[$k]);
        }

        $QFfield_callback_table = array();
        $ret = DB::Execute('SELECT * FROM '.$tab.'_callback WHERE freezed=0');
        while ($row = $ret->FetchRow()) {
            $QFfield_callback_table[$row['field']] = explode('::',$row['callback']);
        }
        $defaults = array_merge($defaults,$_SESSION['rb_'.$tab.'_defaults']);

        $qf = new HTML_QuickForm('rb_edit', 'post', 'mobile.php?'.http_build_query($_GET));
        foreach($cols as $field=>$args) {
            if(isset($access[$args['id']]) && !$access[$args['id']]) continue;

            $label = _V($args['name']); // TRSL
            //ignore callback fields
            if(isset($QFfield_callback_table[$field])) {
//              if($id===false) continue;
//              $val = Utils_RecordBrowserCommon::get_val($tab,$args['name'],$rec,true,$args);
//              if($val==='') continue;
//              $qf->addElement('static',$args['id'],$label);
//              $qf->setDefaults(array($args['id']=>$val));
//              unset($defaults[$args['id']]);
                $ff = $QFfield_callback_table[$field];
                if(isset($rec[$args['id']]))
                    $val = $rec[$args['id']];
                elseif(isset($defaults[$args['id']]))
                    $val = $defaults[$args['id']];
                else
                    $val = null;
				$mobile_rb = new Utils_RecordBrowserMobile($tab, $rec);
                call_user_func_array($ff, array(&$qf, $args['id'], $label, $mode, $val, $args, $mobile_rb, null));
                if($mode=='edit')
                    unset($defaults[$args['id']]);
                continue;
            }

            switch ($args['type']) {
                case 'calculated':  $qf->addElement('static', $args['id'], $label);
                            if (!is_array($rec))
                                $values = $defaults;
                            else {
                                $values = $rec;
                                if (is_array($defaults)) $values = $values+$defaults;
                            }
                            $val = @Utils_RecordBrowserCommon::get_val($tab,$args['name'],$values,true,$args);
                            if (!$val) $val = '['.__('formula').']';
                            $qf->setDefaults(array($args['id']=>$val));
                            break;
                case 'integer':
                case 'float':       $qf->addElement('text', $args['id'], $label);
                            if ($args['type']=='integer')
                                $qf->addRule($args['id'], __('Only integer numbers are allowed.'), 'regex', '/^[0-9]*$/');
                            else
                                $qf->addRule($args['id'], __('Only numbers are allowed.'), 'numeric');
                            if($id!==false)
                                $qf->setDefaults(array($args['id']=>$rec[$args['id']]));
                            break;
                case 'checkbox':    $qf->addElement('checkbox', $args['id'], $label, '');
                            if($id!==false)
                                $qf->setDefaults(array($args['id']=>$rec[$args['id']]));
                            break;
                case 'currency':    $qf->addElement('currency', $args['id'], $label);
                            if($id!==false)
                                $qf->setDefaults(array($args['id']=>$rec[$args['id']]));
                            break;
                case 'text':        $qf->addElement('text', $args['id'], $label, array('maxlength'=>$args['param']));
                            $qf->addRule($args['id'], __('Maximum length for this field is %s characters.', array($args['param'])), 'maxlength', $args['param']);
                            if($id!==false)
                                $qf->setDefaults(array($args['id']=>$rec[$args['id']]));
                            break;
                case 'long text':   $qf->addElement('textarea', $args['id'], $label,array('maxlength'=>200));
                            $qf->addRule($args['id'], __('Maximum length for this field in mobile edition is 200 chars.'), 'maxlengt',200);
                            if($id!==false)
                                $qf->setDefaults(array($args['id']=>$rec[$args['id']]));
                            break;
                case 'commondata':  $param = explode('::',$args['param']['array_id']);
                            foreach ($param as $k=>$v) if ($k!=0) $param[$k] = self::get_field_id($v);
                            if(count($param)==1) {
                                $qf->addElement($args['type'], $args['id'], $label, $param, array('empty_option'=>true, 'id'=>$args['id'], 'order_by_key'=>$args['param']['order_by_key']));
                                if($id!==false)
                                    $qf->setDefaults(array($args['id']=>$rec[$args['id']]));
                            }
                            break;
                case 'select':      $comp = array();
                            $ref = explode(';',$args['param']);
                            if (isset($ref[1])) $crits_callback = $ref[1];
                                else $crits_callback = null;
                            if (isset($ref[2])) $multi_adv_params = call_user_func(explode('::',$ref[2]));
                                else $multi_adv_params = null;
                            if (!isset($multi_adv_params) || !is_array($multi_adv_params)) $multi_adv_params = array();
                            if (!isset($multi_adv_params['order'])) $multi_adv_params['order'] = array();
                            if (!isset($multi_adv_params['cols'])) $multi_adv_params['cols'] = array();
                            if (!isset($multi_adv_params['format_callback'])) $multi_adv_params['format_callback'] = array();
                            $ref = $ref[0];
                            @(list($tab2, $col) = explode('::',$ref));
                            if (!isset($col)) trigger_error($field);
                            if ($tab2=='__COMMON__') {
                                $data = Utils_CommonDataCommon::get_translated_tree($col);
                                if (!is_array($data)) $data = array();
                                $comp = $comp+$data;
                            } else {
                                if (isset($crits_callback)) {
                                    $crit_callback = explode('::',$crits_callback);
                                    if (is_callable($crit_callback)) {
                                        $crits = call_user_func($crit_callback, false, $rec);
                                        $adv_crits = call_user_func($crit_callback, true, $rec);
                                    } else $crits = $adv_crits = array();
                                    if ($adv_crits === $crits) $adv_crits = null;
                                    if ($adv_crits !== null) {
                                        continue; //skip record picker
                                    }
                                } else $crits = array();
                                $col = explode('|',$col);
                                $col_id = array();
                                foreach ($col as $c) $col_id[] = self::get_field_id($c);
                                $records = Utils_RecordBrowserCommon::get_records($tab2, $crits, empty($multi_adv_params['format_callback'])?$col_id:array(), !empty($multi_adv_params['order'])?$multi_adv_params['order']:array());
                                $ext_rec = array();
                                if (isset($rec[$args['id']])) {
                                    if (!is_array($rec[$args['id']])) {
                                        if ($rec[$args['id']]!='') $rec[$args['id']] = array($rec[$args['id']]=>$rec[$args['id']]); else $rec[$args['id']] = array();
                                    }
                                }
                                if (isset($defaults[$args['id']])) {
                                    if (!is_array($defaults[$args['id']]))
                                        $rec[$args['id']][$defaults[$args['id']]] = $defaults[$args['id']];
                                    else {
                                        foreach ($defaults[$args['id']] as $v)
                                            $rec[$args['id']][$v] = $v;
                                    }
                                }
                                $single_column = (count($col_id)==1);
                                if (isset($rec[$args['id']])) {
                                    $ext_rec = array_flip($rec[$args['id']]);
                                    foreach($ext_rec as $k=>$v) {
                                        $c = Utils_RecordBrowserCommon::get_record($tab2, $k);
                                        if (!empty($multi_adv_params['format_callback'])) $n = call_user_func($multi_adv_params['format_callback'], $c);
                                        else {
                                            if ($single_column) $n = $c[$col_id[0]];
                                            else {
                                                $n = array();
                                                foreach ($col_id as $cid) $n[] = $c[$cid];
                                                $n = implode(' ',$n);
                                            }
                                        }
                                        $comp[$k] = $n;
                                    }
                                }
                                if (!empty($multi_adv_params['order'])) natcasesort($comp);
                                foreach ($records as $k=>$v) {
                                    if (!empty($multi_adv_params['format_callback'])) $n = call_user_func($multi_adv_params['format_callback'], $v);
                                    else {
//                                      $n = $v[$col_id];
                                        if ($single_column) $n = $v[$col_id[0]];
                                        else {
                                            $n = array();
                                            foreach ($col_id as $cid) $n[] = $v[$cid];
                                            $n = implode(' ',$n);
                                        }
                                    }
                                    $comp[$k] = $n;
                                    unset($ext_rec[$v['id']]);
                                }
                                if (empty($multi_adv_params['order'])) natcasesort($comp);
                            }
                            if ($args['type']==='select') $comp = array(''=>'---')+$comp;
                            $qf->addElement($args['type'], $args['id'], $label, $comp, array('id'=>$args['id']));
                            if($id!==false)
                                $qf->setDefaults(array($args['id']=>$rec[$args['id']]));
                            break;
                case 'date':        $qf->addElement('date',$args['id'],$label,array('format'=>'d M Y', 'minYear'=>date('Y')-95,'maxYear'=>date('Y')+5, 'addEmptyOption'=>true, 'emptyOptionText'=>'--'));
                            if(isset($rec[$args['id']]) && $rec[$args['id']] && $id!==false)
                                $qf->setDefaults(array($args['id']=>$rec[$args['id']]));
                            break;
                case 'timestamp':   $qf->addElement('date',$args['id'],$label,array('format'=>'d M Y H:i', 'minYear'=>date('Y')-95,'maxYear'=>date('Y')+5, 'addEmptyOption'=>true, 'emptyOptionText'=>'--'));
                            if(isset($rec[$args['id']]) && $rec[$args['id']] && $id!==false)
                                $qf->setDefaults(array($args['id']=>$rec[$args['id']]));
                            break;
                case 'time':        $qf->addElement('date',$args['id'],$label,array('format'=>'H:i', 'addEmptyOption'=>true, 'emptyOptionText'=>'--'));
                            if(isset($rec[$args['id']]) && $rec[$args['id']] && $id!==false)
                                $qf->setDefaults(array($args['id']=>$rec[$args['id']]));
                            break;
                case 'multiselect': //ignore
                            if($id===false) continue;
                            $val = Utils_RecordBrowserCommon::get_val($tab,$args['name'],$rec,true,$args);
                            if($val==='') continue;
                            $qf->addElement('static',$args['id'],$label);
                            $qf->setDefaults(array($args['id']=>$val));
                            unset($defaults[$args['id']]);
                            break;
            }
            if($args['required'])
                $qf->addRule($args['id'],__('Field required'),'required');
        }

        $qf->setDefaults($defaults);

        $qf->addElement('submit', 'submit_button', __('Save'),IPHONE?'class="button white"':'');

        if($qf->validate()) {
            $values = $qf->exportValues();
            foreach ($cols as $v) {
                if ($v['type']=='checkbox' && !isset($values[$v['id']])) $values[$v['id']]=0;
                elseif($v['type']=='date') {
                    if(is_array($values[$v['id']]) && $values[$v['id']]['Y']!=='' && $values[$v['id']]['M']!=='' && $values[$v['id']]['d']!=='')
                        $values[$v['id']] = $values[$v['id']]['Y'].'-'.$values[$v['id']]['M'].'-'.$values[$v['id']]['d'];
                    else
                        $values[$v['id']] = '';
                } elseif($v['type']=='timestamp') {
                    if($values[$v['id']]['Y']!=='' && $values[$v['id']]['M']!=='' && $values[$v['id']]['d']!=='' && $values[$v['id']]['H']!=='' && $values[$v['id']]['i']!=='')
                        $values[$v['id']] = $values[$v['id']]['Y'].'-'.$values[$v['id']]['M'].'-'.$values[$v['id']]['d'].' '.$values[$v['id']]['H'].':'.$values[$v['id']]['i'];
                    else
                        $values[$v['id']] = '';
                } elseif($v['type']=='time') {
                    if($values[$v['id']]['H']!=='' && $values[$v['id']]['i']!=='')
                        $values[$v['id']] = $values[$v['id']]['H'].':'.$values[$v['id']]['i'];
                    else
                        $values[$v['id']] = '';
                }
            }
            foreach ($defaults as $k=>$v)
                if (!isset($values[$k])) $values[$k] = $v;
            if($id!==false) {
                $values['id'] = $id;
                Utils_RecordBrowserCommon::update_record($tab, $id, $values);
            } else {
                $id = Utils_RecordBrowserCommon::new_record($tab, $values);
            }
            return false;
        }

        $renderer =& $qf->defaultRenderer();
        $qf->accept($renderer);
        print($renderer->toHtml());
    }

    public static function mobile_rb_delete($tab, $id) {
        if(!isset($_GET['del_ok'])) {
            print('<a '.(IPHONE?'class="button green" ':'').' href="mobile.php?'.http_build_query($_GET).'&del_ok=0">'.__('Cancel deletion').'</a>');
            print('<a '.(IPHONE?'class="button red" ':'').' href="mobile.php?'.http_build_query($_GET).'&del_ok=1">'.__('Delete').'</a>');
        } else {
            if($_GET['del_ok'])
                Utils_RecordBrowserCommon::delete_record($tab, $id);
                return 2;
            return false;
        }
        return true;
    }
}

class Utils_RecordBrowserMobile { // mini class to simulate full RB object, TODO: consider passing tab and record as statics linked to RBCommon instead
	public $tab;
	public $record;
	
	public function __construct($tab, $record) {
		$this->tab = $tab;
		$this->record = $record;
	}
}

require_once 'modules/Utils/RecordBrowser/object_wrapper/include.php';

?>
