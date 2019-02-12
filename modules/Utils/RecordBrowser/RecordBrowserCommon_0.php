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
    public static $admin_filter = '';
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
        if($tab=='__RECORDSETS__' || preg_match('/,/',$tab)) return;
        if (!isset(self::$display_callback_table[$tab])) {
            $ret = DB::Execute('SELECT * FROM '.$tab.'_callback WHERE freezed=1');
            while ($row = $ret->FetchRow())
                self::$display_callback_table[$tab][$row['field']] = $row['callback'];
        }
	}

    public static function callback_check_function($callback, $only_check_syntax = false)
    {
        if (is_array($callback)) $callback = implode('::', $callback);
        $func = null;
        if (preg_match('/^([\\\\a-zA-Z_\x7f-\xff][\\\\a-zA-Z0-9_\x7f-\xff]*)::([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)$/', $callback, $match)) {
            $func = array($match[1], $match[2]);
        } elseif (preg_match('/^([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)$/', $callback, $match)) {
            $func = $match[1];
        }
        if (is_callable($func, $only_check_syntax)) {
            return $func;
        }
        return false;
    }

    public static function call_display_callback($callback, $record, $links_not_recommended, $field, $tab)
    {
        $callback_func = self::callback_check_function($callback, true);
        if ($callback_func) {
            if (is_callable($callback_func)) {
                $ret = call_user_func($callback_func, $record, $links_not_recommended, $field, $tab);
            } else {
                $callback_str = (is_array($callback_func) ? implode('::', $callback_func) : $callback_func);
                trigger_error("Callback $callback_str for field: '$field[id]', recordset: '$tab' not found", E_USER_NOTICE);
                $ret = $record[$field['id']];
            }
        } else {
            ob_start();
            $ret = eval($callback);
            if($ret===false) trigger_error($callback,E_USER_ERROR);
            else print($ret);
            $ret = ob_get_contents();
            ob_end_clean();
        }
        return $ret;
    }

    public static function call_QFfield_callback($callback, &$form, $field, $label, $mode, $default, $desc, $rb_obj, $display_callback_table = null)
    {
        if ($display_callback_table === null) {
            $display_callback_table = self::display_callback_cache($rb_obj->tab);
        }
        $callback_func = self::callback_check_function($callback, true);
        if ($callback_func) {
            if (is_callable($callback_func)) {
                call_user_func_array($callback_func, array(&$form, $field, $label, $mode, $default, $desc, $rb_obj, $display_callback_table));
            } else {
                $callback_str = (is_array($callback_func) ? implode('::', $callback_func) : $callback_func);
                trigger_error("Callback $callback_str for field: '$field', recordset: '{$rb_obj->tab}' not found", E_USER_NOTICE);
            }
        } else {
            eval($callback);
        }
    }
	
	public static function get_val($tab, $field, $record, $links_not_recommended = false, $desc = null) {
        static $recurrence_call_stack = array();
        self::init($tab);
        if (!isset(self::$table_rows[$field])) {
            if (!isset(self::$hash[$field])) trigger_error('Unknown field "'.$field.'" for recordset "'.$tab.'"',E_USER_ERROR);
            $field = self::$hash[$field];
        }
      
        $desc = array_merge(self::$table_rows[$field], $desc?: []);
        if(!array_key_exists('id',$record)) $record['id'] = null;
        if (!array_key_exists($desc['id'],$record)) trigger_error($desc['id'].' - unknown field for record '.serialize($record), E_USER_ERROR);
        $val = $record[$desc['id']];
        $function_call_id = implode('|', array($tab, $field, serialize($val)));
        if (isset($recurrence_call_stack[$function_call_id])) {
            return '!! ' . __('recurrence issue') . ' !!';
        } else {
            $recurrence_call_stack[$function_call_id] = true;
        }
		self::display_callback_cache($tab);
		if (isset(self::$display_callback_table[$tab][$field])) {
			$display_callback = self::$display_callback_table[$tab][$field];
		} else {
			$display_callback = self::get_default_display_callback($desc['type']);
		}

        if ($display_callback) {
            $ret = self::call_display_callback($display_callback, $record, $links_not_recommended, $desc, $tab);
        } else {
		    $ret = $val;
        }

        unset($recurrence_call_stack[$function_call_id]);
        return $ret;
    }
    
    ////////////////////////////
    // default display callbacks
    
    public static function get_default_display_callback($type) {
    	$types = array('select', 'multiselect', 'commondata', 'autonumber', 'currency', 'checkbox', 
    			'date', 'timestamp', 'time', 'long text', 'file');
    	if (array_search($type, $types) !== false) {
    		return __CLASS__. '::display_' . self::get_field_id($type);
    	}
    	return null;
    }
    public static function display_select($record, $nolink=false, $desc=null, $tab=null) {
    	$ret = '---';
    	if (isset($desc['id']) && isset($record[$desc['id']]) && $record[$desc['id']]!=='') {
    		$val = $record[$desc['id']];
    		$commondata_sep = '/';
    		if ((is_array($val) && empty($val))) return $ret;
    		
    		$param = self::decode_select_param($desc['param']);
    		
    		if(!$param['array_id'] && $param['single_tab'] == '__COMMON__') return;
    		
    		if (!is_array($val)) $val = array($val);
    		
    		$ret = '';
    		foreach ($val as $v) {
    			$ret .= ($ret!=='')? '<br>': '';
    			
    			if ($param['single_tab'] == '__COMMON__') {
    				$array_id = $param['array_id'];
    				$path = explode('/', $v);
    				$tooltip = '';
    				$res = '';
    				if (count($path) > 1) {
    					$res .= Utils_CommonDataCommon::get_value($array_id . '/' . $path[0], true);
    					if (count($path) > 2) {
    						$res .= $commondata_sep . '...';
    						$tooltip = '';
    						$full_path = $array_id;
    						foreach ($path as $w) {
    							$full_path .= '/' . $w;
    							$tooltip .= ($tooltip? $commondata_sep: '').Utils_CommonDataCommon::get_value($full_path, true);
    						}
    					}
    					$res .= $commondata_sep;
    				}
    				$label = Utils_CommonDataCommon::get_value($array_id . '/' . $v, true);
    				if (!$label) continue;
    				$res .= $label;    				
    				$res = self::no_wrap($res);
    				if ($tooltip) $res = '<span '.Utils_TooltipCommon::open_tag_attrs($tooltip, false) . '>' . $res . '</span>';
    			} else {
    				$tab_id = self::decode_record_token($v, $param['single_tab']);
    				
    				if (!$tab_id) continue;
    					
    				list($select_tab, $id) = $tab_id;

    				if ($param['cols']) {
    					$res = self::create_linked_label($select_tab, $param['cols'], $id, $nolink);
    				} else {
    					$res = self::create_default_linked_label($select_tab, $id, $nolink);
    				}
    			}
    			
    			$ret .= $res;
    		}
    	}
    	 
    	return $ret;
    }
    public static function display_multiselect($record, $nolink=false, $desc=null, $tab=null) {
    	return self::display_select($record, $nolink, $desc, $tab);
    }
    public static function display_commondata($record, $nolink=false, $desc=null, $tab=null) {
    	$ret = '';    	
    	if (isset($desc['id']) && isset($record[$desc['id']]) && $record[$desc['id']]!=='') {
    		$arr = explode('::', $desc['param']['array_id']);
    		$path = array_shift($arr);
    		foreach($arr as $v) $path .= '/' . $record[self::get_field_id($v)];
    		$path .= '/' . $record[$desc['id']];
    		$ret = Utils_CommonDataCommon::get_value($path, true);
    	}
    	
    	return $ret;
    }
	public static function display_autonumber($record, $nolink=false, $desc=null, $tab=null) {
    	$ret = '';
    	if (isset($desc['id']) && isset($record[$desc['id']]) && $record[$desc['id']]!=='') {
    		$ret = $record[$desc['id']];
    		
    		if (!$nolink && isset($record['id']) && $record['id'])
    			$ret = self::record_link_open_tag_r($tab, $record) . $ret . self::record_link_close_tag();
    	}
    	
    	return $ret;    	
    }
    public static function display_currency($record, $nolink=false, $desc=null, $tab=null) {
    	$ret = '';
    	if (isset($desc['id']) && isset($record[$desc['id']]) && $record[$desc['id']]!=='') {
    		$val = Utils_CurrencyFieldCommon::get_values($record[$desc['id']]);
            $ret = Utils_CurrencyFieldCommon::format($val[0], $val[1]);
    	}
    	 
    	return $ret;
    }
    public static function display_checkbox($record, $nolink=false, $desc=null, $tab=null) {
    	$ret = '';
    	if (isset($desc['id']) && array_key_exists($desc['id'], $record)) {
    		$ret = $record[$desc['id']]? __('Yes'): __('No');
    	}
    	 
    	return $ret;
    }
	public static function display_checkbox_icon($record, $nolink, $desc=null) {
		$ret = '';
		if (isset($desc['id']) && isset($record[$desc['id']]) && $record[$desc['id']]!=='') {
			$ret = '<img src="'.Base_ThemeCommon::get_template_file('images', ($record[$desc['id']]? 'checkbox_on': 'checkbox_off') . '.png') .'">';;
		}
		
		return $ret;
    }
    public static function display_checkbox_setting($record, $nolink=false, $desc=null, $tab=null) {
    	$img = self::display_checkbox_icon($record, $nolink, $desc);
    
    	$rb_obj = Utils_RecordBrowser::$rb_obj;
    
    	if (!$img || $nolink || !$desc || !($rb_obj instanceof Utils_RecordBrowser)) return $img;
    		
    	$href = $rb_obj->create_callback_href(array('Utils_RecordBrowserCommon', 'set_checkbox_setting'), array($tab, $record['id'], $desc['id'], $record[$desc['id']]?0:1));
    
    	$tooltip_attrs = Utils_TooltipCommon::open_tag_attrs(__('Click to toggle'));
    
    	return "<a $href $tooltip_attrs>" . $img . '</a>';
    }
    public static function set_checkbox_setting($tab, $id, $field, $active=1) {
    	self::update_record($tab, $id, array($field=>$active));
    }
    public static function display_date($record, $nolink, $desc=null) {
    	$ret = '';
    	if (isset($desc['id']) && isset($record[$desc['id']]) && $record[$desc['id']]!=='') {
    		$ret = Base_RegionalSettingsCommon::time2reg($record[$desc['id']], false, true, false);
    	}
    	 
    	return $ret;
    }
    public static function display_timestamp($record, $nolink, $desc=null) {
    	$ret = '';
    	if (isset($desc['id']) && isset($record[$desc['id']]) && $record[$desc['id']]!=='') {
    		$ret = Base_RegionalSettingsCommon::time2reg($record[$desc['id']], 'without_seconds');
    	}
    
    	return $ret;
    }
    public static function display_time($record, $nolink, $desc=null) {
    	$ret = '';
    	if (isset($desc['id']) && isset($record[$desc['id']])) {
            $ret = $record[$desc['id']] !== '' && $record[$desc['id']] !== false
                ? Base_RegionalSettingsCommon::time2reg($record[$desc['id']], 'without_seconds', false)
                : '---';
    	}
    
    	return $ret;
    }
    public static function display_long_text($record, $nolink, $desc=null) {
    	$ret = '';
    	if (isset($desc['id']) && isset($record[$desc['id']]) && $record[$desc['id']]!=='') {
    		$ret = self::format_long_text($record[$desc['id']]);
    	}
    
    	return $ret;
    }
    public static function multiselect_from_common($arrid) {
        return '__COMMON__::'.$arrid;
    }
    public static function format_long_text($text){
		$ret = htmlspecialchars($text);
		$ret = str_replace("\n",'<br>',$ret);
		$ret = Utils_BBCodeCommon::parse(htmlspecialchars_decode($ret));
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
        if (isset($param[1])) {
            $order = Utils_CommonDataCommon::validate_order($param[0]);
            $array_id = $param[1];
        } else {
        	$order = 'value';
        	$array_id = $param[0];        	
        }
        return array('order'=>$order, 'order_by_key'=>$order, 'array_id'=>$array_id);
    }
    public static function encode_commondata_param($param) {
    	if (!is_array($param)) 
    		$param = array($param);
               
    	$order = 'value';
        if (isset($param['order']) || isset($param['order_by_key'])) {
        	$order = Utils_CommonDataCommon::validate_order(isset($param['order'])? $param['order']: $param['order_by_key']);
        	
        	unset($param['order']);
        	unset($param['order_by_key']);
        }
        
        $array_id = implode('::', $param);
         
        return implode('__', array($order, $array_id));
    }
    public static function decode_autonumber_param($param, &$prefix, &$pad_length, &$pad_mask) {
        $parsed = explode('__', $param, 4);
        if (!is_array($parsed) || count($parsed) != 3)
            trigger_error("Not well formed autonumber parameter: $param", E_USER_ERROR);
        list($prefix, $pad_length, $pad_mask) = $parsed;
    }
    public static function encode_autonumber_param($prefix, $pad_length, $pad_mask) {
        return implode('__', array($prefix, $pad_length, $pad_mask));
    }
    public static function format_autonumber_str($param, $id) {
        self::decode_autonumber_param($param, $prefix, $pad_length, $pad_mask);
        if ($id === null)
            $pad_mask = '?';
        return $prefix . str_pad($id, $pad_length, $pad_mask, STR_PAD_LEFT);
    }
    public static function format_autonumber_str_all_records($tab, $field, $param) {
        self::decode_autonumber_param($param, $prefix, $pad_length, $pad_mask);
        $ids = DB::GetCol('SELECT id FROM '.$tab.'_data_1');
        DB::StartTrans();
        foreach ($ids as $id) {
            $str = $prefix . str_pad($id, $pad_length, $pad_mask, STR_PAD_LEFT);
            DB::Execute('UPDATE ' . $tab . '_data_1 SET indexed = 0, f_' . $field . '=%s WHERE id=%d', array($str, $id));
        }
        DB::CompleteTrans();
    }
    public static function decode_select_param($param) {
    	if (is_array($param)) return $param;
    	
    	$param = explode(';', $param);
    	$reference = explode('::', $param[0]);
    	$crits_callback = isset($param[1]) && $param[1] != '::' ? explode('::', $param[1]): null;
    	$adv_params_callback = isset($param[2]) && $param[2] != '::' ? explode('::', $param[2]): null;
    	
    	//in case RB records select
    	$select_tab = $reference[0];
        $cols = isset($reference[1]) ? array_filter(explode('|', $reference[1])) : null;

    	//in case __COMMON__
    	$array_id = isset($reference[1])? $reference[1]: null;
    	$order = isset($reference[2])? $reference[2]: 'value';

    	if ($select_tab == '__RECORDSETS__') {
    		$select_tabs = DB::GetCol('SELECT tab FROM recordbrowser_table_properties');
    		$single_tab = false;
    	}
    	else {
    		$select_tabs = array_filter(explode(',',$select_tab));
    		$single_tab = count($select_tabs)==1? $select_tab: false;
    	}
    	
    	return array(
    			'single_tab'=>$single_tab? $select_tab: false, //returns single tab name, __COMMON__ or false
    			'select_tabs'=>$select_tabs, //returns array of tab names
    			'cols'=>$cols, // returns array of columns for formatting the display value (used in case RB records select)
    			'array_id'=>$array_id, //returns array_id (used in case __COMMON__)
    			'order'=>$order, //returns order code (used in case __COMMON__)
    			'crits_callback'=>$crits_callback, //returns crits callback (used in case RB records select)
    			'adv_params_callback'=>$adv_params_callback //returns adv_params_callback (used in case RB records select)
    	);
    }
    
    public static function decode_record_token($token, $single_tab=false) {
    	$kk = explode('/',$token);
    	if(count($kk)==1) {
    		if ($single_tab && is_numeric($kk[0])) {
    			$tab = $single_tab;
    			$record_id = $kk[0];
    		} else return false;
    	} else {
    		$record_id = array_pop($kk);
    		$tab = $single_tab?$single_tab:$kk[0];
    		if (!self::check_table_name($tab) || !is_numeric($record_id)) return false;
    	}
    	
    	return array('tab'=>$tab, 'id'=>$record_id, $tab, $record_id);
    }
    
    public static function call_select_adv_params_callback($callback, $record=null) {
    	$ret = array(
    		'order'=>array(),
    		'cols'=>array(),
    		'format_callback'=>array('Utils_RecordBrowserCommon', 'autoselect_label')
    	);
    	
    	$adv_params = array();
    	if (is_callable($callback))
    		$adv_params = call_user_func($callback, $record);
    	
    	if (!is_array($adv_params))
    		$adv_params = array();
    		
    	return array_merge($ret, $adv_params);
    }
    
    public static function get_select_tab_crits($param, $record=null) {
    	$param = self::decode_select_param($param);
    	
   		$ret = array();
    	if (is_callable($param['crits_callback']))
    		$ret = call_user_func($param['crits_callback'], false, $record);
    	
    	$tabs = $param['select_tabs'];    		

    	$ret = !empty($ret)? $ret: array();
    	if ($param['single_tab'] && (!is_array($ret) || !isset($ret[$param['single_tab']]))) {
    		$ret = array($param['single_tab'] => $ret);
    	}
    	elseif(is_array($ret) && !array_intersect($tabs, array_keys($ret))) {
    		$tab_crits = array();
    		foreach($tabs as $tab)
    			$tab_crits[$tab] = $ret;
    			
    		$ret = $tab_crits;
    	}
    	
    	foreach ($ret as $tab=>$crits) {
    		if (!$tab || !self::check_table_name($tab, false, false)) {
    			unset($ret[$tab]);
    			continue;
    		}
    		$access_crits = self::get_access_crits($tab, 'selection');
    		if ($access_crits===false) unset($ret[$tab]);
    		if ($access_crits===true) continue;
    		if (is_array($access_crits) || $access_crits instanceof Utils_RecordBrowser_CritsInterface) {
    			if((is_array($crits) && $crits) || $crits instanceof Utils_RecordBrowser_CritsInterface)
    				$ret[$tab] = self::merge_crits($crits, $access_crits);
    			else
    				$ret[$tab] = $access_crits;
    		}
    	}

    	return $ret;
    }
    
    public static function call_select_item_format_callback($callback, $tab_id, $args) {
//     	$args = array($tab, $tab_crits, $format_callback, $params);

    	$param = self::decode_select_param($args[3]);
    	
    	$val = self::decode_record_token($tab_id, $param['single_tab']);
    	
    	if (!$val) return '';
    	
    	list($tab, $record_id) = $val;
    	
    	$tab_caption = '';
    	if (!$param['single_tab']) {
    		$tab_caption = self::get_caption($tab);
    	
    		$tab_caption = '[' . ((!$tab_caption || $tab_caption == '---')? $tab: $tab_caption) . '] ';
    	}
    	
    	$callback = is_callable($callback)? $callback: array('Utils_RecordBrowserCommon', 'autoselect_label');
    	
    	return $tab_caption . call_user_func($callback, $tab_id, $args);
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
            }
			$settings[0][] = array('name'=>$row['tab'].'_show_filters','label'=>'','type'=>'hidden','default'=>0);
        }
        $final_settings = array();
        $subscribe_settings = array();
        $final_settings[] = array('name'=>'add_in_table_shown','label'=>__('Quick new record - show by default'),'type'=>'checkbox','default'=>0);
        $final_settings[] = array('name'=>'hide_empty','label'=>__('Hide empty fields'),'type'=>'checkbox','default'=>0);
        $final_settings[] = array('name'=>'enable_autocomplete','label'=>__('Enable autocomplete in select/multiselect at'),'type'=>'select','default'=>50, 'values'=>array(0=>__('Always'), 20=>__('%s records', array(20)), 50=>__('%s records', array(50)), 100=>__('%s records', array(100))));
        $final_settings[] = array('name'=>'grid','label'=>__('Grid edit (experimental)'),'type'=>'checkbox','default'=>0);
        $final_settings[] = array('name'=>'confirm_leave','label'=>__('Confirm before leave edit page'),'type'=>'checkbox','default'=>1);
        $final_settings[] = array('name'=>'header_default_view','label'=>__('Default data view'),'type'=>'header');
        $final_settings = array_merge($final_settings,$settings[0]);
        $final_settings[] = array('name'=>'header_auto_fav','label'=>__('Automatically add to favorites records created by me'),'type'=>'header');
        $final_settings = array_merge($final_settings,$settings[1]);
        $final_settings[] = array('name'=>'header_auto_subscriptions','label'=>__('Automatically watch records created by me'),'type'=>'header');
        $final_settings = array_merge($final_settings,$settings[2]);
        return array(__('Browsing records')=>$final_settings);
    }
    public static function check_table_name($tab, $flush=false, $failure_on_missing=true){
        static $tables = null;
        if($tab=='__RECORDSETS__' || preg_match('/,/',$tab)) return true;
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
    public static function admin_access() {
        return DEMO_MODE?false:true;
    }
	public static function admin_access_levels() {
		return array(
			'records'=>array('label'=>__('Manage Records'), 'values'=>array(0=>__('No access'), 1=>__('View'), 2=>__('Full')), 'default'=>1),
			'fields'=>array('label'=>__('Manage Fields'), 'values'=>array(0=>__('No access'), 1=>__('View'), 2=>__('Full')), 'default'=>1),
			'settings'=>array('label'=>__('Manage Settings'), 'values'=>array(0=>__('No access'), 1=>__('View'), 2=>__('Full')), 'default'=>1),
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
        if($tab=='__RECORDSETS__' || preg_match('/,/',$tab)) return;
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
            if ($row['type'] == 'file') {
                $row['param'] = json_decode($row['param'], true);
                if (!is_array($row['param'])) $row['param'] = [];
                if (!isset($row['param']['max_files'])) $row['param']['max_files'] = false;
            }
            $next_field =
                array(  'name'=>str_replace('%','%%',$row['caption']?$row['caption']:$row['field']),
                        'id'=>self::get_field_id($row['field']),
                        'pkey'=>$row['id'],
                        'type'=>$row['type'],
                        'caption'=>$row['caption'],
                        'visible'=>$row['visible'],
                        'required'=>($row['type']=='calculated'?false:$row['required']),
                        'extra'=>$row['extra'],
                        'active'=>$row['active'],
                        'export'=>$row['export'],
                        'tooltip'=>$row['tooltip'],
                        'position'=>$row['position'],
                        'processing_order' => $row['processing_order'],
                        'filter'=>$row['filter'],
                        'style'=>$row['style'],
                        'param'=>$row['param'],
                        'help' =>$row['help'],
                		'template'=>$row['template']
                );
			if (isset(self::$display_callback_table[$tab][$row['field']]))
				$next_field['display_callback'] = self::$display_callback_table[$tab][$row['field']];
			if (($row['type']=='select' || $row['type']=='multiselect') && $row['param']) {
				$pos = strpos($row['param'], ':');
				$next_field['ref_table'] = substr($row['param'], 0, $pos);
				if ($next_field['ref_table']=='__COMMON__') {
					$next_field['ref_field'] = '__COMMON__';
                    $exploded = explode('::', $row['param']);
                    $next_field['commondata_array'] = $next_field['ref_table'] = $exploded[1];
                    $next_field['commondata_order'] = isset($exploded[2]) ? $exploded[2] : 'value';
					$commondata = true;
				} else {
				    $end = strpos($row['param'], ';', $pos+2);
				    if ($end==0) $end = strlen($row['param']);
				    $next_field['ref_field'] = substr($row['param'], $pos+2, $end-$pos-2);
				}
			}
			$next_field['commondata'] = $commondata;
            if ($commondata) {
                if (!isset($next_field['commondata_order'])) {
                    if (isset($next_field['param']['order'])) {
                        $next_field['commondata_order'] = $next_field['param']['order'];
                    } else {
                        $next_field['commondata_order'] = 'value';
                    }
                }
                if (!isset($next_field['commondata_array'])) {
                    $next_field['commondata_array'] = $next_field['param']['array_id'];
                }
            }
            self::$table_rows[$row['field']] = $next_field;
            self::$hash[$next_field['id']] = $row['field'];
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
                    'id I2 AUTO KEY NOTNULL,'.
                    'field C(32) UNIQUE NOTNULL,'.
                    'caption C(255),'.
                    'type C(32),'.
                    'extra I1 DEFAULT 1,'.
                    'visible I1 DEFAULT 1,'.
                    'tooltip I1 DEFAULT 1,'.
                    'required I1 DEFAULT 1,'.
                    'export I1 DEFAULT 1,'.
                    'active I1 DEFAULT 1,'.
                    'position I2,'.
                    'processing_order I2 NOTNULL,'.
                    'filter I1 DEFAULT 0,'.
                    'param C(255),'.
                    'style C(64),'.
        			'template C(255),'.
                    'help X',
                    array('constraints'=>''));
        DB::CreateTable($tab.'_callback',
                    'field C(32),'.
                    'callback C(255),'.
                    'freezed I1',
                    array('constraints'=>''));

        DB::Execute('INSERT INTO '.$tab.'_field(field, type, extra, visible, position, processing_order) VALUES(\'id\', \'foreign index\', 0, 0, 1, 1)');
        DB::Execute('INSERT INTO '.$tab.'_field(field, type, extra, position, processing_order) VALUES(\'General\', \'page_split\', 0, 2, 2)');

		$fields_sql = '';
        foreach ($fields as $v)
            $fields_sql .= Utils_RecordBrowserCommon::new_record_field($tab, $v, false, false);
        DB::CreateTable($tab.'_data_1',
                    'id I AUTO KEY,'.
                    'created_on T NOT NULL,'.
                    'created_by I NOT NULL,'.
                    'indexed I1 NOT NULL DEFAULT 0,'.
                    'active I1 NOT NULL DEFAULT 1'.
					$fields_sql,
                    array('constraints'=>''));
        DB::CreateIndex($tab.'_idxed',$tab.'_data_1','indexed,active');
        DB::CreateIndex($tab.'_act',$tab.'_data_1','active');

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
					'crits X',
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
        DB::Execute('DELETE FROM recordbrowser_processing_methods WHERE tab=%s', array($tab));
        DB::Execute('DELETE FROM recordbrowser_browse_mode_definitions WHERE tab=%s', array($tab));
        DB::Execute('DELETE FROM recordbrowser_clipboard_pattern WHERE tab=%s', array($tab));
        DB::Execute('DELETE FROM recordbrowser_addon WHERE tab=%s', array($tab));
        DB::Execute('DELETE FROM recordbrowser_access_methods WHERE tab=%s', array($tab));
        return true;
    }

    public static function delete_record_field($tab, $field){
        self::init($tab);
        self::$clear_get_val_cache = true;
        $exists = DB::GetOne('SELECT 1 FROM '.$tab.'_field WHERE field=%s', array($field));
        if(!$exists) return;
        // move to the end
        self::change_field_position($tab, $field, 16000);
        DB::Execute('DELETE FROM '.$tab.'_field WHERE field=%s', array($field));
        DB::Execute('DELETE FROM '.$tab.'_callback WHERE field=%s', array($field));
        
        if (isset(self::$table_rows[$field]['id'])) {
			$f_id = self::$table_rows[$field]['id'];
	        @DB::Execute('ALTER TABLE '.$tab.'_data_1 DROP COLUMN f_'.$f_id);
			@DB::Execute('DELETE FROM '.$tab.'_access_fields WHERE block_field=%s', array($f_id));
	        self::init($tab, false, true);
	        @DB::Execute('UPDATE '.$tab.'_data_1 SET indexed=0');
        }
    }

    private static $datatypes = null;
    public static function new_record_field($tab, $definition, $alter=true, $set_empty_position_before_first_page_split=true){
        if (self::$datatypes===null) {
            self::$datatypes = array();
            $ret = DB::Execute('SELECT * FROM recordbrowser_datatype');
            while ($row = $ret->FetchRow())
                self::$datatypes[$row['type']] = array($row['module'], $row['func']);
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
                    8=>'position',
                    9=>'processing_order',
                    10=>'caption') as $k=>$w)
                if (isset($args[$k])) $definition[$w] = $args[$k];
        }
        if (!isset($definition['type'])) trigger_error(print_r($definition,true));
        if (!isset($definition['param'])) $definition['param'] = '';
        if (!isset($definition['caption'])) $definition['caption'] = '';
        if (!isset($definition['style'])) {
            if (in_array($definition['type'], array('time','timestamp','currency')))
                $definition['style'] = $definition['type'];
            else {
                if (in_array($definition['type'], array('float','integer', 'autonumber')))
                    $definition['style'] = 'number';
                else
                    $definition['style'] = '';
            }
        }
        if (!isset($definition['extra'])) $definition['extra'] = true;
        if (!isset($definition['export'])) $definition['export'] = true;
        if (!isset($definition['visible'])) $definition['visible'] = false;
        if (!isset($definition['tooltip'])) $definition['tooltip'] = $definition['visible'];
        if (!isset($definition['required'])) $definition['required'] = false;
        if (!isset($definition['filter'])) $definition['filter'] = false;
        if (!isset($definition['position'])) $definition['position'] = null;
        if (!isset($definition['template'])) $definition['template'] = '';
        if (!isset($definition['help'])) $definition['help'] = '';
        $definition['template'] = is_array($definition['template'])? implode('::', $definition['template']): $definition['template'];
        if (isset(self::$datatypes[$definition['type']])) $definition = call_user_func(self::$datatypes[$definition['type']], $definition);

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
        if (is_string($definition['position'])) $definition['position'] = self::get_field_position($tab, $definition['position'])+1;
        if ($definition['position']===null || $definition['position']===false) {
            $first_page_split = $set_empty_position_before_first_page_split?DB::GetOne('SELECT MIN(position) FROM '.$tab.'_field WHERE type=%s AND field!=%s', array('page_split', 'General')):0;
            $definition['position'] = $first_page_split?$first_page_split:DB::GetOne('SELECT MAX(position) FROM '.$tab.'_field')+1;
        }
        DB::Execute('UPDATE '.$tab.'_field SET position = position+1 WHERE position>=%d', array($definition['position']));
        DB::CompleteTrans();
        if (!isset($definition['processing_order'])) $definition['processing_order'] = DB::GetOne('SELECT MAX(processing_order) FROM '.$tab.'_field') + 1;

        $param = $definition['param'];
        if (is_array($param)) {
            if ($definition['type']=='commondata') {
                $param = self::encode_commondata_param($param);
            } elseif($definition['type'] == 'file') {
                $param = json_encode($param);
            } else {
                $tmp = array();
                foreach ($param as $k=>$v) $tmp[] = $k.'::'.$v;
                $param = implode(';',$tmp);
            }
        }
        $f = self::actual_db_type($definition['type'], $param);
        DB::Execute('INSERT INTO '.$tab.'_field(field, caption, type, visible, param, style, position, processing_order, extra, required, filter, export, tooltip, template, help) VALUES(%s, %s, %s, %d, %s, %s, %d, %d, %d, %d, %d, %d, %d, %s, %s)', array($definition['name'], $definition['caption'], $definition['type'], $definition['visible']?1:0, $param, $definition['style'], $definition['position'], $definition['processing_order'], $definition['extra']?1:0, $definition['required']?1:0, $definition['filter']?1:0, $definition['export']?1:0, $definition['tooltip']?1:0, $definition['template'], $definition['help']));
		$column = 'f_'.self::get_field_id($definition['name']);
		if ($alter) {
			self::init($tab, false, true);
			if ($f!=='') {
                @DB::Execute('ALTER TABLE '.$tab.'_data_1 ADD COLUMN '.$column.' '.$f);
                if ($definition['type'] === 'autonumber') {
                    self::format_autonumber_str_all_records($tab, self::get_field_id($definition['name']), $param);
                }
            }
		} else {
			if ($f!=='') return ','.$column.' '.$f;
			else return '';
		}
        @DB::Execute('UPDATE '.$tab.'_data_1 SET indexed=0');
    }
    public static function change_field_position($tab, $field, $new_pos){
    	$new_pos = is_string($new_pos)? (self::get_field_position($tab, $new_pos)+1): $new_pos;

        if ($new_pos <= 2) return; // make sure that no field is before "General" tab split
        
        DB::StartTrans();
        if ($pos = self::get_field_position($tab, $field)) {
            // move all following fields back
            DB::Execute('UPDATE '.$tab.'_field SET position=position-1 WHERE position>%d',array($pos));
            // make place for moved field
            DB::Execute('UPDATE '.$tab.'_field SET position=position+1 WHERE position>=%d',array($new_pos));
            // set new field position
            DB::Execute('UPDATE '.$tab.'_field SET position=%d WHERE field=%s',array($new_pos, $field));
        }
        DB::CompleteTrans();
    }
    
    public static function get_field_position($tab, $field){    
    	return DB::GetOne('SELECT position FROM '.$tab.'_field WHERE field=%s', array($field));
    }

    /**
     * @param string $tab Recordset identifier. e.g. contact, company
     * @param string $old_name Current field name. Not field id. e.g. "First Name"
     * @param string $new_name New field name.
     */
    public static function rename_field($tab, $old_name, $new_name)
    {
        $id = self::get_field_id($old_name);
        $new_id = self::get_field_id($new_name);
        self::check_table_name($tab);

        $type = DB::GetOne('SELECT type FROM ' . $tab . '_field WHERE field=%s', array($old_name));
        $old_param = DB::GetOne('SELECT param FROM ' . $tab . '_field WHERE field=%s', array($old_name));
        
        $db_field_exists = !(in_array($type, ['calculated', 'hidden'], true) && !$old_param) && $type!== 'page_split';
        
        DB::StartTrans();
        if ($db_field_exists) {
        	if (DB::is_postgresql()) {
        		DB::Execute('ALTER TABLE ' . $tab . '_data_1 RENAME COLUMN f_' . $id . ' TO f_' . $new_id);
        	} else {
        		 
        		DB::RenameColumn($tab . '_data_1', 'f_' . $id, 'f_' . $new_id, self::actual_db_type($type, $old_param));
        	}
        	DB::Execute('UPDATE ' . $tab . '_edit_history_data SET field=%s WHERE field=%s', array($new_id, $id));
        }
        DB::Execute('UPDATE ' . $tab . '_field SET field=%s WHERE field=%s', array($new_name, $old_name));
        DB::Execute('UPDATE ' . $tab . '_access_fields SET block_field=%s WHERE block_field=%s', array($new_id, $id));
        DB::Execute('UPDATE ' . $tab . '_callback SET field=%s WHERE field=%s', array($new_name, $old_name));
        
        $result = DB::Execute('SELECT * FROM ' . $tab . '_access');
        while ($row = $result->FetchRow()) {
        	$crits = self::unserialize_crits($row['crits']);
        	 
        	if (!$crits) continue;
        	 
        	if (!is_object($crits))
        		$crits = Utils_RecordBrowser_Crits::from_array($crits);
        
        	foreach ($crits->find($id)?:[] as $c) $c->set_field($new_id);
        
        	DB::Execute('UPDATE ' . $tab . '_access SET crits=%s WHERE id=%d', array(self::serialize_crits($crits), $row['id']));
        }
        
        DB::CompleteTrans();
    }
    
    public static function set_field_template($tab, $fields, $template)
    {
    	Utils_RecordBrowserCommon::check_table_name($tab);
    	$template = is_array($template)? implode('::', $template): $template;
    	$fields = is_array($fields)? $fields: array($fields);
    	$s = array_fill(0, count($fields), '%s');
    	
    	DB::Execute('UPDATE ' . $tab . '_field SET template=%s WHERE field IN ('. implode(',', $s)  .')', array_merge(array($template), $fields));
    }

    /**
     * List all installed recordsets.
     * @param string $format Simple formatting of the values
     *
     *      You can use three keys that will be replaced with values: <br>
     *       - %tab - table identifier <br>
     *       - %orig_caption - original table caption <br>
     *       - %caption - translated table caption <br>
     *      Default is '%caption'. If no caption is specified then
     *      table identifier is used as caption. <br>
     *      Other common usage: '%caption (%tab)'
     * @return array Keys are tab identifiers, values according to $format param
     */
    public static function list_installed_recordsets($format = '%caption')
    {
        $tabs = DB::GetAssoc('SELECT tab, caption FROM recordbrowser_table_properties');
        $ret = array();
        foreach ($tabs as $tab_id => $caption) {
            if (!$caption) {
                $translated_caption = $caption = $tab_id;
            } else {
                $translated_caption = _V($caption);
            }
            $ret[$tab_id] = str_replace(
                array('%tab', '%orig_caption', '%caption'),
                array($tab_id, $caption, $translated_caption),
                $format
            );
        }
        return $ret;
    }

    public static function actual_db_type($type, $param=null) {
        $f = '';
        switch ($type) {
            case 'page_split': $f = ''; break;

            case 'text': $f = DB::dict()->ActualType('C').'('.$param.')'; break;
            case 'select': 
            	$param = self::decode_select_param($param);
                if($param['single_tab']) $f = DB::dict()->ActualType('I4'); 
                else $f = DB::dict()->ActualType('X'); 
                break;
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
            case 'autonumber': $len = strlen(self::format_autonumber_str($param, null));
                $f = DB::dict()->ActualType('C') . "($len)"; break;
	        case 'file': $f = DB::dict()->ActualType('X'); break;
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
        if(self::$datatypes!==null) self::$datatypes[$type] = array($module,$func);
        DB::Execute('INSERT INTO recordbrowser_datatype (type, module, func) VALUES (%s, %s, %s)', array($type, $module, $func));
    }
    public static function unregister_datatype($type) {
        if(self::$datatypes!==null) unset(self::$datatypes[$type]);
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
        if (is_array($callback)) $callback = implode('::',$callback);
        if(!DB::GetOne('SELECT 1 FROM recordbrowser_processing_methods WHERE tab=%s AND func=%s', array($tab, $callback)))
            DB::Execute('INSERT INTO recordbrowser_processing_methods (tab, func) VALUES (%s, %s)', array($tab, $callback));
    }
    public static function unregister_processing_callback($tab, $callback) {
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
    /**
     * Enable search
     * @param string $tab recordset identifier
     * @param int $mode 0 - search disabled, 1 - enabled by default, 2 - optional
     * @param int $priority Possible values: -2, -1, 0, 1, 2
     */
    public static function set_search($tab, $mode,$priority=0) {
        DB::Execute('UPDATE recordbrowser_table_properties SET search_include=%d,search_priority=%d WHERE tab=%s', array($mode, $priority, $tab));
    }
    public static function set_description_callback($tab, $callback){
        if (is_array($callback)) $callback = implode('::',$callback);
        DB::Execute('UPDATE recordbrowser_table_properties SET description_callback=%s WHERE tab=%s', array($callback, $tab));
    }

    /**
     * Set description fields to be used as default linked label
     *
     * You can use double quotes to put any text between field values
     * e.g. 'Last Name, ", ", First Name,'
     *
     * @param string $tab recordset name
     * @param string|array $fields comma separated list of fields or array of fields
     */
    public static function set_description_fields($tab, $fields)
    {
        if (is_array($fields)) $fields = implode(',', $fields);
        DB::Execute('UPDATE recordbrowser_table_properties SET description_fields=%s WHERE tab=%s', array($fields, $tab));
    }
    public static function set_printer($tab,$class) {
        Base_PrintCommon::register_printer(new $class());
        DB::Execute('UPDATE recordbrowser_table_properties SET printer=%s WHERE tab=%s', array($class, $tab));
    }

    public static function unset_printer($tab)
    {
        $printer_class = DB::GetOne('SELECT printer FROM recordbrowser_table_properties WHERE tab=%s',$tab);
        if ($printer_class) {
            Base_PrintCommon::unregister_printer($printer_class);
            DB::Execute('UPDATE recordbrowser_table_properties SET printer=%s WHERE tab=%s', array('', $tab));
            return $printer_class;
        }
        return false;
    }

    /**
     * Enable or disable jump to id. By default it is enabled.
     *
     * @param string $tab     Recordset identifier
     * @param bool   $enabled True to enable, false to disable
     */
    public static function set_jump_to_id($tab, $enabled = true)
    {
        $sql = 'UPDATE recordbrowser_table_properties SET jump_to_id=%d WHERE tab=%s';
        DB::Execute($sql, array($enabled ? 1 : 0, $tab));
    }
    public static function get_caption($tab) {
		static $cache = null;
        if ($cache===null) $cache = DB::GetAssoc('SELECT tab, caption FROM recordbrowser_table_properties');
		if (is_string($tab) && isset($cache[$tab])) return _V($cache[$tab]);
		return '---';
    }
    public static function get_description_callback($tab) {
    	static $cache = null;
    	if ($cache===null) $cache = DB::GetAssoc('SELECT tab, description_callback FROM recordbrowser_table_properties');

    	if (is_string($tab) && isset($cache[$tab])) {
    		if(is_string($cache[$tab]) && preg_match('/::/',$cache[$tab])) {
    			$cache[$tab] = explode('::',$cache[$tab]);
    		}
    		if(!is_callable($cache[$tab]))
    			$cache[$tab] = false;
    		
    		return $cache[$tab];
    	}
    	
    	return false;
    }
    public static function get_description_fields($tab) {
        static $cache = null;
        if ($cache===null) {
            $db_ret = DB::GetAssoc('SELECT tab, description_fields FROM recordbrowser_table_properties');
            foreach ($db_ret as $t => $fields) {
                if ($fields) {
                    $fields = str_replace('"', '\'"', $fields);
                    $cache[$t] = array_filter(array_map('trim', str_getcsv($fields, ',', "'")));
                }
            }
        }

        if (is_string($tab) && isset($cache[$tab])) {
            return $cache[$tab];
        }

        return false;
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
		if ($mode=='cloned') $current = array('original'=>$clone, 'clone'=>$current);
		foreach ($cache[$tab] as $callback) {
			$return = call_user_func($callback, $current, $mode, $tab);
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
		foreach(self::$table_rows as $field=>$desc)
			if ($desc['type']==='multiselect') {
				if (!isset($for_processing[$desc['id']]) || !$for_processing[$desc['id']])
					$for_processing[$desc['id']] = array();
			} elseif (!isset($for_processing[$desc['id']])) $for_processing[$desc['id']] = '';

		$values = self::record_processing($tab, $for_processing, 'add');
		if ($values===false) return;

        self::init($tab);
        $fields = 'created_on,created_by,active';
        $fields_types = '%T,%d,%d';
        $vals = array(date('Y-m-d H:i:s'), $user, 1);
	    $filestorageIds = [];
        foreach(self::$table_rows as $field => $desc) {

	        if ($desc['type'] == 'file') {
                $files = $values[$desc['id']];
                if (is_string($files)) $files = self::decode_multi($files);
                if ($desc['param']['max_files'] && count($files) > $desc['param']['max_files']) {
                    throw new Exception('Too many files in field ' . $desc['id']);
                }
                $filestorageIds[$field] = Utils_FileStorageCommon::add_files($files);
                $values[$desc['id']] = self::encode_multi($filestorageIds[$field]);
	        }

	        if (($desc['type']=='calculated' || $desc['type']=='hidden') && preg_match('/^[a-z]+(\([0-9]+\))?$/i',$desc['param'])===0) continue; // FIXME move DB definiton to *_field table
            if (!isset($values[$desc['id']]) || $values[$desc['id']]==='') continue;
			if (!is_array($values[$desc['id']])) $values[$desc['id']] = trim($values[$desc['id']]);
            if ($desc['type']=='long text')
                $values[$desc['id']] = Utils_BBCodeCommon::optimize($values[$desc['id']]);
            if ($desc['type']=='multiselect' && empty($values[$desc['id']])) continue;
            if ($desc['type']=='multiselect')
                $values[$desc['id']] = self::encode_multi($values[$desc['id']]);
            if ($desc['type']=='multiselect' || $desc['type']=='select') $values[$desc['id']] = str_replace(array('P:', 'C:'), array('contact/', 'company/'), $values[$desc['id']]);
            $fields_types .= ','.self::get_sql_type($desc['type']);
            $fields .= ',f_'.$desc['id'];
            if (is_bool($values[$desc['id']])) $values[$desc['id']] = $values[$desc['id']]?1:0;
            $vals[] = $values[$desc['id']];
        }
        Utils_SafeHtml_SafeHtml::setSafeHtml(new Utils_SafeHtml_HtmlPurifier());
        foreach ($vals as $k => $v) {
            $vals[$k] = Utils_SafeHtml_SafeHtml::outputSafeHtml($v);
        }
        DB::Execute('INSERT INTO '.$tab.'_data_1 ('.$fields.') VALUES ('.$fields_types.')',$vals);
        $id = DB::Insert_ID($tab.'_data_1', 'id');
        if ($user) self::add_recent_entry($tab, $user, $id);
        if (Base_User_SettingsCommon::get('Utils_RecordBrowser',$tab.'_auto_fav'))
            DB::Execute('INSERT INTO '.$tab.'_favorite (user_id, '.$tab.'_id) VALUES (%d, %d)', array($user, $id));
		self::init($tab);
		foreach(self::$table_rows as $field=>$desc) {
			if ($desc['type']==='multiselect') {
				if (!isset($values[$desc['id']])) $values[$desc['id']] = array();
				elseif (!is_array($values[$desc['id']]))
					$values[$desc['id']] = self::decode_multi($values[$desc['id']]);
			}
            if ($desc['type'] === 'autonumber') {
                $autonumber_value = self::format_autonumber_str($desc['param'], $id);
                self::update_record($tab, $id, array($desc['id'] => $autonumber_value), false, null, true);
                $values[$desc['id']] = $autonumber_value;
            }
			if ($desc['type'] === 'file') {
			    // update backref
			    Utils_FileStorageCommon::add_files($filestorageIds[$field], "rb:$tab/$id/$desc[pkey]");
                $values[$desc['id']] = $filestorageIds[$field];
            }
        }
		$values['id'] = $id;
		self::record_processing($tab, $values, 'added');

        if (Base_User_SettingsCommon::get('Utils_RecordBrowser',$tab.'_auto_subs')==1)
            Utils_WatchdogCommon::subscribe($tab,$id);
        Utils_WatchdogCommon::new_event($tab,$id,'C');

        return $id;
    }

    public static function new_record_history($tab,$id,$old_value) {
        DB::Execute('INSERT INTO ' . $tab . '_edit_history(edited_on, edited_by, ' . $tab . '_id) VALUES (%T,%d,%d)', array(date('Y-m-d G:i:s'), Acl::get_user(), $id));
        $edit_id = DB::Insert_ID($tab . '_edit_history', 'id');
        DB::Execute('INSERT INTO ' . $tab . '_edit_history_data(edit_id, field, old_value) VALUES (%d,%s,%s)', array($edit_id, 'id', $old_value));
        return $edit_id;
    }

    public static function update_record($tab,$id,$values,$all_fields = false, $date = null, $dont_notify = false) {
        DB::StartTrans();
        self::init($tab);
        $record = self::get_record($tab, $id, false);
        if (!is_array($record)) {
            DB::CompleteTrans();
            return false;
        }

		$process_method_args = $values;
		$process_method_args['id'] = $id;
		foreach ($record as $k=>$v)
			if (!isset($process_method_args[$k])) $process_method_args[$k] = $v;

		$values = self::record_processing($tab, $process_method_args, 'edit');

        $diff = array();
        self::init($tab);
        foreach(self::$table_rows as $field => $desc){
			if ($desc['type']=='calculated' && preg_match('/^[a-z]+(\([0-9]+\))?$/i',$desc['param'])===0) continue; // FIXME move DB definiton to *_field table
            if ($desc['id']=='id') continue;
            if (!isset($values[$desc['id']])) {
                if ($all_fields) $values[$desc['id']] = '';
                else continue;
            }
			if ($desc['type']=='checkbox') {
				if ($values[$desc['id']]) $values[$desc['id']] = 1;
				else $values[$desc['id']] = 0;
				if ($record[$desc['id']]) $record[$desc['id']] = 1;
				else $record[$desc['id']] = 0;
			}
            if ($desc['type']=='long text')
                $values[$desc['id']] = Utils_BBCodeCommon::optimize($values[$desc['id']]);
            if ($desc['type']=='multiselect') {
                if (!is_array($values[$desc['id']])) $values[$desc['id']] = array($values[$desc['id']]);
                $array_diff = array_diff($record[$desc['id']], $values[$desc['id']]);
                if (empty($array_diff)) {
                    $array_diff = array_diff($values[$desc['id']], $record[$desc['id']]);
                    if (empty($array_diff)) continue;
                }
                $v = self::encode_multi($values[$desc['id']]);
                $old = self::encode_multi($record[$desc['id']]);
            } elseif ($desc['type'] == 'file') {
                $files = $values[$desc['id']];
                if (is_string($files)) {
                    $files = self::decode_multi($files);
                }
                if ($desc['param']['max_files'] && count($files) > $desc['param']['max_files']) {
                    throw new Exception('Too many files in field ' . $desc['id']);
                }
                $filestorageIds = Utils_FileStorageCommon::add_files($files, "rb:$tab/$id/$desc[pkey]");
                $values[$desc['id']] = $filestorageIds;
                // Delete files not present in the field right now
                $old = $record[$desc['id']];
                sort($old);
                if ($old == $filestorageIds) continue;
                foreach ($record[$desc['id']] as $file) {
                    if (!in_array($file, $filestorageIds)) {
                        // delete file
                        Utils_FileStorageCommon::delete($file);
                    }
                }
                $v = self::encode_multi($filestorageIds);
                $old = self::encode_multi($old);
            } else {
                if ($record[$desc['id']]===$values[$desc['id']]) continue;
                $v = $values[$desc['id']];
                $old = $record[$desc['id']];
            }
            if ($v!=='') DB::Execute('UPDATE '.$tab.'_data_1 SET f_'.$desc['id'].'='.self::get_sql_type($desc['type']).' WHERE id=%d',array($v, $id));
            else DB::Execute('UPDATE '.$tab.'_data_1 SET f_'.$desc['id'].'=NULL WHERE id=%d',array($id));
            $diff[$desc['id']] = $old;
        }
        if(!empty($diff)) {
            @DB::Execute('UPDATE '.$tab.'_data_1 SET indexed=0 WHERE id=%d',array($id));
            self::record_processing($tab, $values, 'edited');
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
        static $rec_size = array();
        if (!isset($rec_size[$tab])) $rec_size[$tab] = DB::GetOne('SELECT recent FROM recordbrowser_table_properties WHERE tab=%s', array($tab));
		$ids = array();
		if ($rec_size[$tab]) {
			$ret = DB::SelectLimit('SELECT '.$tab.'_id FROM '.$tab.'_recent WHERE user_id = %d ORDER BY visited_on DESC',
						$rec_size[$tab],
						-1,
						array($user_id));
			while($row = $ret->FetchRow()) {
				if ($row[$tab.'_id']==$id || !$row[$tab.'_id']) continue;
				if (count($ids)>=$rec_size[$tab]-1) continue;
				$ids[] = $row[$tab.'_id'];
			}
		}
		if (empty($ids)) $where = '';
		else $where = ' AND '.$tab.'_id NOT IN ('.implode(',',$ids).')';
		DB::Execute('DELETE FROM '.$tab.'_recent WHERE user_id = %d'.$where,
					array($user_id));
		if ($rec_size[$tab])
			DB::Execute('INSERT INTO '.$tab.'_recent ('.$tab.'_id, user_id, visited_on) VALUES (%d, %d, %T)',
						array($id,
						$user_id,
						date('Y-m-d H:i:s')));
    }
    public static function merge_crits($a = array(), $b = array(), $or=false) {
        return Utils_RecordBrowser_Crits::merge($a, $b, $or);
    }
    public static function build_query($tab, $crits = null, $admin = false, $order = array(), $tab_alias = 'r') {
        static $stack = array();
        static $cache;
        if (!is_object($crits)) {
            $crits = Utils_RecordBrowser_Crits::from_array($crits);
        }
        $cache_key = $tab . '__' . $tab_alias . '__' . md5(serialize($crits)) . '__' . $admin . '__' . md5(serialize($order)) . '__' . Base_AclCommon::get_user();
        if (isset($cache[$cache_key])) {
            return $cache[$cache_key];
        }

        $access_crits = ($admin || in_array($tab, $stack)) ? true : self::get_access_crits($tab, 'browse');
        if ($access_crits === false) return array();
        elseif ($access_crits !== true) {
            $crits = self::merge_crits($crits, $access_crits);
        }

        if ($admin) {
            $admin_filter = str_replace('<tab>', $tab_alias, self::$admin_filter);
        } else {
            $admin_filter = $tab_alias . '.active=1 AND ';
        }
        array_push($stack, $tab);
        $query_builder = new Utils_RecordBrowser_QueryBuilder($tab, $tab_alias, $admin);
        $ret = $query_builder->build_query($crits, $order, $admin_filter);
        $cache[$cache_key] = $ret;
        array_pop($stack);
        return $ret;
    }

    /**
     * Get records count
     *
     * @param string                          $tab
     * @param array|Utils_RecordBrowser_Crits $crits
     * @param bool                            $admin
     * @param array                           $order Just for SQL cache optimization. Same query will be used to fetch records
     *
     * @return int records count
     */
    public static function get_records_count( $tab, $crits = null, $admin = false, $order = array()) {
        $par = self::build_query($tab, $crits, $admin, $order);
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

    /**
     * @param string $tab Recordset identifier
     * @param array|Utils_RecordBrowser_Crits $crits
     * @param array $cols not used anymore
     * @param array $order
     * @param int|array $limit nr of rows or array('offset'=>X, 'numrows'=>Y); 
     * @param bool  $admin
     *
     * @return array|bool
     */
    public static function get_records( $tab, $crits = array(), $cols = array(), $order = array(), $limit = array(), $admin = false) {
        if (!$tab) return false;
        if (is_numeric($limit)) {
            $limit = array('numrows'=>$limit,'offset'=>0);
        } else {
            if (!isset($limit['offset'])) $limit['offset'] = 0;
            if (!isset($limit['numrows'])) $limit['numrows'] = -1;
        }
        if (!$order) $order = array();
        $tab_alias = 'r';
        $fields = "$tab_alias.*";
        self::init($tab);
        $par = self::build_query($tab, $crits, $admin, $order);
        if (empty($par)) return array();
        $ret = DB::SelectLimit('SELECT '.$fields.' FROM'.$par['sql'].$par['order'], $limit['numrows'], $limit['offset'], $par['vals']);
        $records = array();
        self::init($tab);
        $fields = self::$table_rows;
        while ($row = $ret->FetchRow()) {
            if (isset($records[$row['id']])) {
                continue;
            }
            $r = array( 'id'=>$row['id'],
                        ':active'=>$row['active'],
                        'created_by'=>$row['created_by'],
                        'created_on'=>$row['created_on']);
            foreach($fields as $desc){
                if (isset($row['f_'.$desc['id']])) {
                    if ($desc['type'] == 'multiselect' || $desc['type'] == 'file') {
                        $r[$desc['id']] = self::decode_multi($row['f_' . $desc['id']]);
                    } elseif ($desc['type']=='text' || $desc['type']=='long text') {
                        $r[$desc['id']] = $row['f_' . $desc['id']];
                    } else {
                        $r[$desc['id']] = $row['f_' . $desc['id']];
                    }
                } else {
                    if ($desc['type']=='multiselect') $r[$desc['id']] = array();
                    else $r[$desc['id']] = '';
                }
            }
            if($admin || self::get_access($tab,'view',$r)) $records[$row['id']] = $r;
        }
        return $records;
    }
    public static function check_record_against_crits($tab, $id, $crits, & $problems = array()) {
        if (is_numeric($id)) $r = self::get_record($tab, $id);
        else $r = $id;
        if (!is_object($crits)) {
            $crits = Utils_RecordBrowser_Crits::from_array($crits);
        }
        $crits_validator = new Utils_RecordBrowser_CritsValidator($tab);
        $crits->normalize();
        list($success, $issues) = $crits_validator->validate($crits, $r);
        if (!$success) {
            $problems = $issues;
        }
        return $success;
    }
    public static function crits_special_values()
    {
        $ret = array();
        $ret[] = new Utils_RecordBrowser_ReplaceValue('USER_ID', __('User Login'), Base_AclCommon::get_user());
        foreach (array('VIEW', 'VIEW_ALL', 'EDIT', 'EDIT_ALL', 'PRINT', 'PRINT_ALL', 'DELETE', 'DELETE_ALL') as $a) {
            $description = 'Allow ' . str_replace('_', ' ', strtolower($a)) . ' record(s)';
            $ret[] = new Utils_RecordBrowser_ReplaceValue("ACCESS_$a", _V($description), 'Utils_RecordBrowserCommon::get_recursive_'.strtolower($a).'_access');
        }
        return $ret;
    }
	public static function get_recursive_access($otab,&$r,$field,$action,$any) {
            self::init($otab);
            $desc = self::$table_rows[self::$hash[$field]];

            $param = self::decode_select_param($desc['param']);

            if($param['single_tab']=='__COMMON__') return $r[$field];
            
            $ret = true;
            $field_is_empty = true;
        if (!isset($r[$field])) $values = array();
            elseif(!is_array($r[$field])) $values = array($r[$field]);
            else $values = $r[$field];
            foreach($values as $rid) {
            	if (!$rid) continue;
            	$val = self::decode_record_token($rid, $param['single_tab']);
				if(!$val) continue;
            	list($tab, $rid) = $val;
            	$rr = self::get_record($tab, $rid);
            	$access = self::get_access($tab, $action, $rr);
            	$field_is_empty = false;
            	if($any && $access) return $r[$field];
            	$ret &= $access;
            }
            return $field_is_empty ? true : ($ret ? true : false);
	}
	public static function get_recursive_view_access($tab,&$r,$field) {
            return self::get_recursive_access($tab,$r,$field,'view',true);
	}
        public static function get_recursive_view_all_access($tab,&$r,$field) {
            return self::get_recursive_access($tab,$r,$field,'view',false);
        }
        public static function get_recursive_edit_access($tab,&$r,$field) {
            return self::get_recursive_access($tab,$r,$field,'edit',true);
        }
        public static function get_recursive_edit_all_access($tab,&$r,$field) {
            return self::get_recursive_access($tab,$r,$field,'edit',false);
        }
        public static function get_recursive_print_access($tab,&$r,$field) {
            return self::get_recursive_access($tab,$r,$field,'print',true);
        }
        public static function get_recursive_print_all_access($tab,&$r,$field) {
            return self::get_recursive_access($tab,$r,$field,'print',false);
        }        
        public static function get_recursive_delete_access($tab,&$r,$field) {
            return self::get_recursive_access($tab,$r,$field,'delete',true);
        }
        public static function get_recursive_delete_all_access($tab,&$r,$field) {
            return self::get_recursive_access($tab,$r,$field,'delete',false);
        }

    public static function serialize_crits($crits)
    {
        $serialized = serialize($crits);
        if (DB::is_postgresql()) {
            $serialized = bin2hex($serialized);
        }
        return $serialized;
    }
    public static function unserialize_crits($str)
    {
        $ret = @unserialize($str);
        if ($ret === false && DB::is_postgresql()) {
            $ret = unserialize(hex2bin($str));
        }
        return $ret;
    }
	public static function parse_access_crits($str, $human_readable = false) {
		$ret = self::unserialize_crits($str);
        if (!is_object($ret)) {
            $ret = Utils_RecordBrowser_Crits::from_array($ret);
        }
		return $ret->replace_special_values($human_readable);
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
    public static function delete_access_rules($tab, $action, $clearance, $crits = array())
    {
        if (!self::check_table_name($tab, false, false)) return;
        if (!is_array($clearance)) $clearance = array($clearance);
        $clearance_c = count($clearance);
        $serialized = self::serialize_crits($crits);
        $ids = DB::GetCol('SELECT id FROM ' . $tab . '_access WHERE crits=%s AND action=%s', array($serialized, $action));
        $ret = 0;
        foreach ($ids as $rule_id) {
            $existing_clearance = DB::GetCol('SELECT clearance FROM ' . $tab . '_access_clearance WHERE rule_id=%d', array($rule_id));
            if ($clearance_c == count($existing_clearance) &&
                $clearance_c == count(array_intersect($existing_clearance, $clearance))) {
                self::delete_access($tab, $rule_id);
                $ret += 1;
            }
        }
        return $ret;
    }
	public static function add_access($tab, $action, $clearance, $crits=array(), $blocked_fields=array()) {
		if (!self::check_table_name($tab, false, false)) return;
        $serialized = self::serialize_crits($crits);
		DB::Execute('INSERT INTO '.$tab.'_access (crits, action) VALUES (%s, %s)', array($serialized, $action));
        $rule_id = DB::Insert_ID($tab.'_access','id');
		if (!is_array($clearance)) $clearance = array($clearance);
		foreach ($clearance as $c)
			DB::Execute('INSERT INTO '.$tab.'_access_clearance (rule_id, clearance) VALUES (%d, %s)', array($rule_id, $c));
		foreach ($blocked_fields as $f)
			DB::Execute('INSERT INTO '.$tab.'_access_fields (rule_id, block_field) VALUES (%d, %s)', array($rule_id, $f));
	}
	public static function update_access($tab, $id, $action, $clearance, $crits=array(), $blocked_fields=array()) {
		if(is_string($id) && in_array($id,array('grant','restrict'))) return;
		elseif(!is_numeric($id)) throw new Exception('Utils_RecordBrowserCommon::update_access - id have to be a number');
		
        $serialized = self::serialize_crits($crits);
        DB::Execute('UPDATE ' . $tab . '_access SET crits=%s, action=%s WHERE id=%d', array($serialized, $action, $id));
		if (!is_array($clearance)) $clearance = array($clearance);
		DB::Execute('DELETE FROM '.$tab.'_access_clearance WHERE rule_id=%d', array($id));
		DB::Execute('DELETE FROM '.$tab.'_access_fields WHERE rule_id=%d', array($id));
		foreach ($clearance as $c)
			DB::Execute('INSERT INTO '.$tab.'_access_clearance (rule_id, clearance) VALUES (%d, %s)', array($id, $c));
		foreach ($blocked_fields as $f)
			DB::Execute('INSERT INTO '.$tab.'_access_fields (rule_id, block_field) VALUES (%d, %s)', array($id, $f));
	}

    public static function register_custom_access_callback($tab, $callback, $priority = 10)
    {
        if (!is_callable($callback)) {
            return false;
        }
        if (is_array($callback)) {
            $callback = implode('::', $callback);
        }
        $existing = self::get_custom_access_callbacks($tab);
        if (in_array($callback, $existing)) {
            return false;
        }
        DB::Execute('INSERT INTO recordbrowser_access_methods (tab, func, priority) VALUES (%s, %s, %d)', array($tab, $callback, $priority));
        self::get_custom_access_callbacks(null, true);
        return true;
    }

    public static function unregister_custom_access_callback($tab, $callback)
    {
        if (is_array($callback)) {
            $callback = implode('::', $callback);
        }
        DB::Execute('DELETE FROM recordbrowser_access_methods WHERE tab=%s AND func=%s', array($tab, $callback));
    }

    public static function get_custom_access_callbacks($tab = null, $force_reload = false)
    {
        static $custom_access_callbacks;
        if ($force_reload || $custom_access_callbacks === null) {
            $custom_access_callbacks = array();
            $db = DB::GetAll('SELECT * FROM recordbrowser_access_methods ORDER BY priority DESC');
            foreach ($db as $row) {
                if (!isset($custom_access_callbacks[$row['tab']])) {
                    $custom_access_callbacks[$row['tab']] = array();
                }
                $custom_access_callbacks[$row['tab']][] = $row['func'];
            }
        }
        if ($tab === null) {
            return $custom_access_callbacks;
        }
        
        return isset($custom_access_callbacks[$tab]) ? $custom_access_callbacks[$tab] : array();
    }
    public static function call_custom_access_callbacks($tab, $action, $record = null)
    {
        $callbacks = self::get_custom_access_callbacks($tab);
        $ret = array('grant'=>null, 'restrict'=>null);
        foreach ($callbacks as $callback) {
            $callback_crits = call_user_func($callback, $action, $record, $tab);
            
            if (is_bool($callback_crits)) {
            	$ret[$callback_crits? 'grant': 'restrict'] = true;
            	break;
            }
            
            if ($callback_crits === null) continue;
				
			// if callback return is crits or crits array use it by default in restrict mode for backward compatibility
			$crits = array(
				'grant' => null,
				'restrict' => $callback_crits
			);
			
			if (is_array($callback_crits) && (isset($callback_crits['grant']) || isset($callback_crits['restrict']))) {
				// if restrict rules are not set make sure the restrict crits are clean
				if (! isset($callback_crits['restrict'])) $callback_crits['restrict'] = null;
				$crits = array_merge($crits, $callback_crits);
			}
			
			if (!$crits['grant'])
				$crits['grant'] = null;
			
			foreach ($crits as $mode => $c) {
				$c = is_array($c) ? Utils_RecordBrowser_Crits::from_array($c): $c;
				
				if ($c instanceof Utils_RecordBrowser_Crits) 
					$ret[$mode] = ($ret[$mode] !== null) ? self::merge_crits($ret[$mode], $c, $mode === 'grant'): $c;
				elseif (is_bool($c))
					$ret[$mode] = $c;
			}
		}

        return $ret;
    }

	/**
	 * 
	 * Check if user has access to recordset, record or recordset fields based on action performed
	 * 
	 * @param string $tab
	 * @param string $action
	 * @param array $record
	 * @param boolean $return_crits - deprecated, use method Utils_RecordBrowserCommon::get_access_crits instead
	 * @param string $return_in_array - deprecated, use method Utils_RecordBrowserCommon::get_access_rule_crits instead
	 * @return false - deny access | array - fields access array
	 */
	public static function get_access($tab, $action, $record=null, $return_crits=false, $return_in_array=false){
		$access = Utils_RecordBrowser_Access::create($tab, $action, $record);
		
		//start deprecated code - used for backward compatibility
		if ($return_crits) {
			if ($return_in_array)
				return $access->getRuleCrits();
				
			return $access->getCrits();;
		}
		//end deprecated code
		
		return $access->getUserAccess(self::$admin_access);  
    }
    
    /**
     * @param string $tab
     * @param string $action
     * @param array $record
     * @return null|boolean|Utils_RecordBrowser_Crits
     */
    public static function get_access_crits($tab, $action, $record=null) { 
    	$access = Utils_RecordBrowser_Access::create($tab, $action, $record);
    	
    	return $access->getCrits();
    }
    
    /**
     * @param string $tab
     * @param string $action
     * @param array $record
     * @return array - rule_id => rule
     */
    public static function get_access_rule_crits($tab, $action, $record=null) {
    	$access = Utils_RecordBrowser_Access::create($tab, $action, $record);
    	
    	return $access->getRuleCrits();
    }

    public static function is_record_active($record) {
    	if (isset($record[':active']) && !$record[':active'])
    		return false;
    	
    	return true;
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
		return '<a '.Utils_TooltipCommon::open_tag_attrs(($isfav?__('This item is on your favorites list').'<br>'.__('Click to remove it from your favorites'):__('Click to add this item to favorites'))).' onclick="utils_recordbrowser_set_favorite('.($isfav?0:1).',\''.$tab.'\','.$id.',\''.$tag_id.'\')" href="javascript:void(0);"><img style="width: 14px; height: 14px;" border="0" src="'.($isfav==false?$star_off:$star_on).'" /></a>';
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
        if (is_string($id)) {
            //  to separate id in recurrent event
            $tmp = explode('_', $id);
            $id = $tmp[0];
        }
        if (is_numeric($id)) $info = Utils_RecordBrowserCommon::get_record_info($tab, $id);
        elseif (is_array($id)) $info = $id;
        else trigger_error('Cannot decode record id: ' . print_r($id, true), E_USER_ERROR);
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
        if (isset($id)) {
            if(!self::check_table_name($tab,false,false)) return null;
            self::init($tab);
            $row = DB::GetRow('SELECT * FROM '.$tab.'_data_1 WHERE id=%d', array($id));
            $record = array('id'=>$id);
            if (!isset($row['active'])) return null;
            foreach(array('created_by','created_on') as $v)
                $record[$v] = $row[$v];
            $record[':active'] = $row['active'];
            foreach(self::$table_rows as $field=>$desc) {
                if ($desc['type']==='multiselect' || $desc['type'] === 'file') {
                    if (!isset($row['f_'.$desc['id']])) $r = array();
                    else $r = self::decode_multi($row['f_'.$desc['id']]);
                    $record[$desc['id']] = $r;
                } else {
                    $record[$desc['id']] = (isset($row['f_'.$desc['id']])?$row['f_'.$desc['id']]:'');
                    if ($htmlspecialchars && $desc['type'] == 'text') $record[$desc['id']] = htmlspecialchars($record[$desc['id']]);
                }
            }
            return $record;
        } else {
            return null;
        }
    }

    public static function get_record_respecting_access($tab, $id, $access_mode = 'view', $htmlspecialchars = true)
    {
        $record = self::get_record($tab, $id, $htmlspecialchars);
        return self::filter_record_by_access($tab, $record, $access_mode);
    }

    public static function filter_record_by_access($tab, $record, $access_mode = 'view')
    {
        if (!$record) {
            return $record;
        }
        $access = self::get_access($tab, $access_mode, $record);
        if (is_array($access)) {
            foreach ($access as $field => $has_access) {
                if (!$has_access) {
                    $record[$field] = null;
                }
            }
        } else if (!$access) {
            $record = false;
        }
        return $record;
    }

    /**
     * Change record state: active / inactive. Soft delete.
     *
     * @param $tab   Recordset identifier
     * @param $id    Record ID
     * @param $state Active / Inactive state
     *
     * @return bool True when status has been changed, false otherwise
     */
    public static function set_active($tab, $id, $state)
    {
        self::check_table_name($tab);
        $current = DB::GetOne('SELECT active FROM ' . $tab . '_data_1 WHERE id=%d', array($id));
        if ($current == ($state ? 1 : 0)) {
            return false;
        }
        $record = self::get_record($tab, $id);
        if (!$record) {
            return false;
        }
        $values = self::record_processing($tab, $record, $state ? 'restore' : 'delete');
        if ($values === false) {
            return false;
        }
        @DB::Execute('UPDATE ' . $tab . '_data_1 SET active=%d,indexed=0 WHERE id=%d', array($state ? 1 : 0, $id));
        $tab_prop = DB::GetRow('SELECT id,search_include FROM recordbrowser_table_properties WHERE tab=%s',array($tab));
        if ($tab_prop['search_include'] > 0) {
            DB::Execute('DELETE FROM recordbrowser_search_index WHERE tab_id=%d AND record_id=%d',array($tab_prop['id'],$id));
        }
        $edit_id = self::new_record_history($tab,$id,$state ? 'RESTORED' : 'DELETED');
        Utils_WatchdogCommon::new_event($tab, $id, ($state ? 'R' : 'D').'_'.$edit_id);
        self::record_processing($tab, $record, $state ? 'restored' : 'deleted');
        return true;
    }

    /**
     * Delete record.
     *
     * @param string $tab   Recordset identifier
     * @param int    $id    Record ID
     * @param bool   $perma Delete permanently with all edit history
     *
     * @return bool True when record has been deleted, false otherwise
     */
    public static function delete_record($tab, $id, $perma = false)
    {
        $ret = false;
        if (!$perma) {
            $ret = self::set_active($tab, $id, false);
        } elseif (self::check_table_name($tab)) {
            $record = self::get_record($tab, $id);
            $values = self::record_processing($tab, $record, 'delete');
            if ($values === false) {
                $ret = false;
            } else {
                self::delete_record_history($tab, $id);
                self::delete_from_favorite($tab, $id);
                self::delete_from_recent($tab, $id);

                DB::Execute('DELETE FROM ' . $tab . '_data_1 WHERE id=%d', array($id));
                $ret = DB::Affected_Rows() > 0;
                if ($ret) {
                    self::record_processing($tab, $record, 'deleted');
                }
            }
        }
        return $ret;
    }

    /**
     * Delete all history entries for specified record.
     *
     * @param $tab Recordset identifier
     * @param $id  Record ID
     *
     * @return int Number of affected edits deleted
     */
    public static function delete_record_history($tab, $id)
    {
        $sql = 'DELETE FROM ' . $tab . '_edit_history_data WHERE edit_id IN' .
               ' (SELECT id FROM ' . $tab . '_edit_history WHERE ' . $tab . '_id = %d)';
        DB::Execute($sql, array($id));
        $sql = 'DELETE FROM ' . $tab . '_edit_history WHERE ' . $tab . '_id = %d';
        DB::Execute($sql, array($id));
        return DB::Affected_Rows();
    }

    /**
     * Delete favorites entries for specified record.
     *
     * @param $tab Recordset identifier
     * @param $id  Record ID
     *
     * @return int Number of favorites entries deleted
     */
    public static function delete_from_favorite($tab, $id)
    {
        $sql = 'DELETE FROM ' . $tab . '_favorite WHERE ' . $tab . '_id = %d';
        DB::Execute($sql, array($id));
        return DB::Affected_Rows();
    }

    /**
     * Delete recent entries for specified record.
     *
     * @param $tab Recordset identifier
     * @param $id  Record ID
     *
     * @return int Number of recent entries deleted
     */
    public static function delete_from_recent($tab, $id)
    {
        $sql = 'DELETE FROM ' . $tab . '_recent WHERE ' . $tab . '_id = %d';
        DB::Execute($sql, array($id));
        return DB::Affected_Rows();
    }

    /**
     * Restore record.
     *
     * @param $tab Recordset identifier
     * @param $id  Record ID
     *
     * @return bool True when record has been restored, false otherwise
     */
    public static function restore_record($tab, $id) {
        return self::set_active($tab, $id, true);
    }
    public static function no_wrap($s) {
        $content_no_wrap = $s;
        preg_match_all('/>([^\<\>]*)</', $s, $match);
		if (empty($match[1])) return str_replace(' ','&nbsp;', $s); // if no matches[1] then that's not html
        // below handle html
        foreach ($match[1] as $v) {
            if ($v === ' ') continue; // do not replace single space in html
            $content_no_wrap = str_replace($v, str_replace(' ', '&nbsp;', $v), $content_no_wrap);
        }
        return $content_no_wrap;
    }
    public static function get_new_record_href($tab, $def, $id='none', $check_defaults=true){
        self::check_table_name($tab);
        if (class_exists('Utils_RecordBrowser') && Utils_RecordBrowser::$clone_result!==null) {
            if (is_numeric(Utils_RecordBrowser::$clone_result)) Base_BoxCommon::push_module(Utils_RecordBrowser::module_name(),'view_entry',array('view', Utils_RecordBrowser::$clone_result), array(Utils_RecordBrowser::$clone_tab));
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
                Base_BoxCommon::push_module(Utils_RecordBrowser::module_name(),'view_entry',array('add', null, $def), array($tab));
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
                    $cds[] = array( 'label'=>_V($k),
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
            Base_BoxCommon::push_module(Utils_RecordBrowser::module_name(),'view_entry_with_REQUEST',array($action, $id, array(), true, $_REQUEST),array($tab));
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
    public static function record_link_open_tag_r($tab, $record, $nolink=false, $action='view', $more=array())
    {
        self::check_table_name($tab);
        $ret = '';
        if (!isset($record['id']) || !is_numeric($record['id'])) {
            return self::$del_or_a = '';
        }
        if (class_exists('Utils_RecordBrowser') &&
            isset(Utils_RecordBrowser::$access_override) &&
            Utils_RecordBrowser::$access_override['tab']==$tab &&
            Utils_RecordBrowser::$access_override['id']==$record['id']) {
            self::$del_or_a = '</a>';
            if (!$nolink) $ret = '<a '.self::create_record_href($tab, $record['id'], $action, $more).'>';
            else self::$del_or_a = '';
        } else {
            $ret = '';
            $tip = '';
            self::$del_or_a = '';
            $has_access = self::get_access($tab, 'view', $record);

            if (!self::is_record_active($record)) {
                $tip = __('This record was deleted from the system, please edit current record or contact system administrator');
                $ret = '<del>';
                self::$del_or_a = '</del>';
            }
            if (!$has_access) {
                $tip .= ($tip?'<br>':'').__('You don\'t have permission to view this record.');
            }
            $tip = $tip ? Utils_TooltipCommon::open_tag_attrs($tip) : '';
            if (!$nolink) {
                if($has_access) {
                    $href = self::create_record_href($tab, $record['id'], $action, $more);
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

    public static function record_link_open_tag($tab, $id, $nolink=false, $action='view', $more=array()){
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
            if (!$nolink) $ret = '<a '.self::create_record_href($tab, $id, $action, $more).'>';
            else self::$del_or_a = '';
        } else {
            $record = self::get_record($tab, $id);
            $ret = self::record_link_open_tag_r($tab, $record, $nolink, $action, $more);
        }
        return $ret;
    }
    public static function record_link_close_tag(){
        return self::$del_or_a;
    }
	public static function create_linked_label($tab, $cols, $id, $nolink=false, $tooltip=false, $more=array()){
    	if (!is_numeric($id)) return '';
    	if (!is_array($cols))
    		$cols = explode('|', $cols);
    	
    	$record = self::get_record($tab, $id);
    	$fields = array_map(array('Utils_RecordBrowserCommon', 'get_field_id'), $cols);
    	$record_vals = self::get_record_vals($tab, $record, true, $fields, false);
    	if (empty($record_vals)) return '';
    	
    	$vals = array();
    	foreach ($fields as $field) {
    		if (empty($record_vals[$field])) continue;
    		$vals[] = $record_vals[$field];
    	}
        $record_label = implode(' ', $vals);
        if (!$record_label) $record_label = self::get_caption($tab) . ": " . sprintf("#%06d", $id);
        $text = self::create_record_tooltip($record_label, $tab, $id, $nolink, $tooltip);

    	return self::record_link_open_tag_r($tab, $record, $nolink, 'view', $more) .
    			$text . self::record_link_close_tag();
    }
	public static function create_linked_text($text, $tab, $id, $nolink=false, $tooltip=true, $more=array()){
		if ($nolink) return $text;
		
    	if (!is_numeric($id)) return '';
    	
    	$text = self::create_record_tooltip($text, $tab, $id, $nolink, $tooltip);
    	
    	return self::record_link_open_tag($tab, $id, $nolink, 'view', $more) . 
    			$text . self::record_link_close_tag();
    }
    public static function create_record_tooltip($text, $tab, $id, $nolink=false, $tooltip=true){
    	if (!$tooltip || $nolink || Utils_TooltipCommon::is_tooltip_code_in_str($text)) 
    		return $text;
    	 
    	if (!is_array($tooltip))
    		return self::create_default_record_tooltip_ajax($text, $tab, $id);
    
    	//args name => expected index (in case of numeric indexed array)
    	$tooltip_create_args = array('tip'=>0, 'args'=>1, 'help'=>1, 'max_width'=>2);
    	 
    	foreach ($tooltip_create_args as $name=>&$key) {
    		switch (true) {
    			case isset($tooltip[$name]):
    				$key = $tooltip[$name];
    				break;
    			case isset($tooltip[$key]):
    				$key = $tooltip[$key];
    				break;
    			default:
    				$key = null;
    				break;
    		}
    	}
    	 
    	if (is_callable($tooltip_create_args['tip'])) {
    		unset($tooltip_create_args['help']);
    		 
    		if (!is_array($tooltip_create_args['args']))
    			$tooltip_create_args['args'] = array($tooltip_create_args['args']);
    		 
    		$tooltip_create_callback = array('Utils_TooltipCommon', 'ajax_create');
    	}
    	else {
    		unset($tooltip_create_args['args']);
    		$tooltip_create_callback = array('Utils_TooltipCommon', 'create');
    	}
    	 
    	array_unshift($tooltip_create_args, $text);
    	 
    	//remove null values from end of the create_tooltip_args to ensure default argument values are set in the callback
    	while (is_null(end($tooltip_create_args)))
    		array_pop($tooltip_create_args);
    	 
    	return call_user_func_array($tooltip_create_callback, $tooltip_create_args);
    }
    public static function get_record_vals($tab, $record, $nolink=false, $fields = array(), $silent = true){
    	if (is_numeric($record)) $record = self::get_record($tab, $record);
    	if (!is_array($record)) return array();
    	
    	self::init($tab);
    	if (empty($fields)) {
    		$fields = array_keys(self::$hash);
    	}
    	else {
    		$available_fields = array_intersect(array_keys(self::$hash), $fields);
    		
    		if (!$silent && count($available_fields) != count($fields)) {
    			trigger_error('Unknown field names: ' . implode(', ', array_diff($fields, $available_fields)), E_USER_ERROR);
    		}
    		
    		$fields = $available_fields;
    	}

    	$ret = array();
    	foreach ($fields as $field) {
    		if (!isset($record[$field])) continue;
    		
    		$ret[$field] = self::get_val($tab, $field, $record, $nolink);
    	}
    	return $ret;
    }
    public static function create_default_linked_label($tab, $id, $nolink=false, $table_name=true, $detailed_tooltip = true){
        if (!is_numeric($id)) return '';
        $record = self::get_record($tab,$id);
        if(!$record) return '';
        $description_callback = self::get_description_callback($tab);
        $description_fields = self::get_description_fields($tab);
        $access = self::get_access($tab, 'view', $record);

        $tab_caption = self::get_caption($tab);
        if(!$tab_caption || $tab_caption == '---') $tab_caption = $tab;

        $label = '';
        if ($access) {
            if ($description_fields) {
                $put_space_before = false;
                foreach ($description_fields as $field) {
                    if ($field[0] === '"') {
                        $label .= trim($field, '"');
                        $put_space_before = false;
                    } else {
                        $field_id = self::get_field_id($field);
                        if ($access === true || (array_key_exists($field_id, $access) && $access[$field_id])) {
                            $field_val = self::get_val($tab, $field, $record, true);
                            if ($field_val) {
                                if ($put_space_before) $label .= ' ';
                                $label .= $field_val;
                                $put_space_before = true;
                            }
                        }
                    }
                }
            } elseif ($description_callback) {
                $label = call_user_func($description_callback, $record, $nolink);
            } else {
                $field = DB::GetOne('SELECT field FROM ' . $tab . '_field WHERE (type=\'autonumber\' OR ((type=\'text\' OR type=\'commondata\' OR type=\'integer\' OR type=\'date\') AND required=1)) AND visible=1 AND active=1 ORDER BY position');
                if ($field) {
                    $label = self::get_val($tab, $field, $record, $nolink);
                }
            }
        }
        if (!$label) {
            $label = sprintf("%s: #%06d", $tab_caption, $id);
        } else {
            $label = ($table_name? $tab_caption . ': ': '') . $label;
        }

        $ret = self::record_link_open_tag_r($tab, $record, $nolink) . $label . self::record_link_close_tag();
        if ($nolink == false && $detailed_tooltip) {
            $ret = self::create_default_record_tooltip_ajax($ret, $tab, $id);
        }
        return $ret;
    }

    public static function create_default_record_tooltip_ajax($string, $tab, $id, $force = false)
    {
        if ($force == false && Utils_TooltipCommon::is_tooltip_code_in_str($string)) {
            return $string;
        }
        $string = Utils_TooltipCommon::ajax_create($string, array(__CLASS__, 'default_record_tooltip'), array($tab, $id));
        return $string;
    }

    public static function get_record_tooltip_data($tab, $record_id)
    {
        $record = self::get_record($tab, $record_id);
        if (!$record[':active']) {
            return array();
        }
        $cols = self::init($tab);
        $access = self::get_access($tab, 'view', $record);
        $data = array();
        foreach ($cols as $desc) {
            if ($desc['tooltip'] && $access[$desc['id']]) {
                $data[_V($desc['name'])] = self::get_val($tab, $desc['id'], $record, true);
            }
        }
        return $data;
    }
    public static function default_record_tooltip($tab, $record_id)
    {
        $data = self::get_record_tooltip_data($tab, $record_id);
        return Utils_TooltipCommon::format_info_tooltip($data);
    }
    public static function display_linked_field_label($record, $nolink=false, $desc=null, $tab = ''){
    	return Utils_RecordBrowserCommon::create_linked_label_r($tab, $desc['id'], $record, $nolink);
    }
    public static function create_linked_label_r($tab, $cols, $r, $nolink=false, $tooltip=false){
        if (!is_array($cols))
            $cols = array($cols);
        $open_tag = self::record_link_open_tag_r($tab, $r, $nolink);
        $close_tag = self::record_link_close_tag();
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
        $text = self::create_record_tooltip(implode(' ', $vals), $tab, $r['id'], $nolink, $tooltip);
        
        return $open_tag . $text . $close_tag;
    }
    public static function record_bbcode($tab, $fields, $text, $record_id, $opt, $tag = null) {
        if (!is_numeric($record_id)) {
            $parts = explode(' ', $text);
            $crits = array();
            foreach ($parts as $k=>$v) {
                $v = "%$v%";
                $chr = '(';
                foreach ($fields as $f) {
                    $crits[$chr.str_repeat('_', $k).$f] = $v;
                    $chr='|';
                }
            }
            $rec = Utils_RecordBrowserCommon::get_records($tab, $crits, array(), array(), 1);
            if (is_array($rec) && !empty($rec)) $rec = array_shift($rec);
            else {
                $crits = array();
                foreach ($parts as $k=>$v) {
                    $v = "%$v%";
                    $chr = '(';
                    foreach ($fields as $f) {
                        $crits[$chr.str_repeat('_', $k).'~'.$f] = $v;
                        $chr='|';
                    }
                }
                $rec = Utils_RecordBrowserCommon::get_records($tab, $crits, array(), array(), 1);
                if (is_array($rec)) $rec = array_shift($rec);
                else $rec = null;
            }
        } else {
            $rec = Utils_RecordBrowserCommon::get_record($tab, $record_id);
        }
        if ($opt) {
            if (!$rec) return null;
            $tag_param = $rec['id'];
            if ($tag == 'rb') $tag_param = "$tab/$tag_param";
            return Utils_BBCodeCommon::create_bbcode(null, $tag_param, $text);
        }
        if ($rec) {
            $access = self::get_access($tab, 'view', $rec);
            if (!$access) {
                $text = "[" . __('Link to record') . ']';
            }
            if (!$text) {
                if ($fields) {
                    return self::create_linked_label_r($tab, $fields, $rec);
                }
                return self::create_default_linked_label($tab, $rec['id']);
            }
            return Utils_RecordBrowserCommon::record_link_open_tag_r($tab, $rec).$text.Utils_RecordBrowserCommon::record_link_close_tag();
        }
        $msg = __('Record not found');
        if ($tag == 'rb') {
            if (!self::check_table_name($tab, false, false)) {
                $msg = __('Recordset not found');
            }
            return Utils_BBCodeCommon::create_bbcode($tag, "$tab/$record_id", $text, $msg);
        }
        return Utils_BBCodeCommon::create_bbcode($tag, $record_id, $text, $msg);
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

	public static function get_edit_details_modify_record($tab, $rid, $edit_id, $details=true) {
		self::init($tab);
		if (is_numeric($rid)) {
			$prev_rev = DB::GetOne('SELECT MIN(id) FROM '.$tab.'_edit_history WHERE '.$tab.'_id=%d AND id>%d', array($rid, $edit_id));
			$r = self::get_record_revision($tab, $rid, $prev_rev);
		} else $r = $rid;
		$edit_info = DB::GetRow('SELECT * FROM '.$tab.'_edit_history WHERE id=%d',array($edit_id));
		$event_display = array('what'=>'Error, Invalid event: '.$edit_id);
		if (!$edit_info) return $event_display;

		$event_display = array(
							'who'=>Base_UserCommon::get_user_label($edit_info['edited_by'], true),
							'when'=>Base_RegionalSettingsCommon::time2reg($edit_info['edited_on']),
							'what'=>array()
						);
		$edit_details = DB::GetAssoc('SELECT field, old_value FROM '.$tab.'_edit_history_data WHERE edit_id=%d',array($edit_id));
        self::init($tab); // because get_user_label messes up
		foreach ($r as $k=>$v) {
			if (isset(self::$hash[$k]) && self::$table_rows[self::$hash[$k]]['type']=='multiselect')
				$r[$k] = self::decode_multi($r[$k]); // We have to decode all fields, because access and some display relay on it, regardless which field changed
		}
		$r2 = $r;
		foreach ($edit_details as $k=>$v) {
			$k = self::get_field_id($k); // failsafe
			if (!isset(self::$hash[$k])) continue;
			if (self::$table_rows[self::$hash[$k]]['type']=='multiselect') {
				$v = $edit_details[$k] = self::decode_multi($v);
			}
			$r2[$k] = $v;
		}
		$access = self::get_access($tab,'view',$r);
        $modifications_to_show = 0;
		foreach ($edit_details as $k=>$v) {
            if($k=='id') {
                $modifications_to_show += 1;
                if (!$details) continue; // do not generate content when we dont want them
                $event_display['what'] = _V($v);
                continue;
            }
			$k = self::get_field_id($k); // failsafe
			if (!isset(self::$hash[$k])) continue;
			if (!$access[$k]) continue;
            $modifications_to_show += 1;
            if (!$details) continue; // do not generate content when we dont want them
			self::init($tab);
			$field = self::$hash[$k];
			$desc = self::$table_rows[$field];
			$event_display['what'][] = array(
										_V($desc['name']),
										self::get_val($tab, $field, $r2, true, $desc),
										self::get_val($tab, $field, $r, true, $desc)
									);
		}
        if ($modifications_to_show)
            return $event_display;
        return null;
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
            if (is_array($label) && is_callable($label)) {
            	$label = self::create_linked_text(call_user_func($label, $r, true), $tab, $rid);
            } elseif ($label) {
                $label = self::create_linked_label_r($tab, $label, $r, false, true);
            } else {
                $label = self::create_default_linked_label($tab, $rid, false, false);
            }

            $ret['title'] = $label;
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
                                $event_display = array(
                                    'who'=> Base_UserCommon::get_user_label($r['created_by'], true),
                                    'when'=>Base_RegionalSettingsCommon::time2reg($r['created_on']),
                                    'what'=>_V($what)
                                );
                                break;
                    case 'D':   if (!isset($what)) $what = 'Deleted';
                    case 'R':   if (!isset($what)) $what = 'Restored';
                                if(!isset($param[1])) {
                                    $event_display = array(
                                        'who' => '',
                                        'when' => '',
                                        'what' => _V($what)
                                    );
                                    break;
                                }
                    case 'E':   $event_display = self::get_edit_details_modify_record($tab, $r['id'], $param[1] ,$details);
				                if (isset($event_display['what']) && !empty($event_display['what'])) $header = true;
                                break;

                    case 'N':   $event_display = false;
                                switch($param[1]) {
                                    case '+':
                                        $action = __('Note linked');
                                        break;
                                    case '-':
                                        $action = __('Note unlinked');
                                        break;
                                    default:
                                	if (!isset($other_events[$param[1]])) $other_events[$param[1]] = 0;
                                	$other_events[$param[1]]++;
                                	$event_display = null;
                                	break;
                                }
                                if($event_display===false) {
                                    $date = isset($param[3]) ? Base_RegionalSettingsCommon::time2reg($param[3]) : '';
                                    $who = isset($param[4]) ? Base_UserCommon::get_user_label($param[4], true) : '';
                                    $action .= ' - ' . self::create_default_linked_label('utils_attachment', $param[2]);
                                    $event_display = array('what'=>$action,
                                         'who' => $who,
                                         'when' => $date);
                                }
                                break;
                    default:    $event_display = array('what'=>_V($v));
                }
                if ($event_display) $events_display[] = $event_display;
            }
            foreach ($other_events as $k=>$v)
        		$events_display[] = array('what'=>_V($k).($v>1?' ['.$v.']':''));

            if ($events_display) {
                $theme = Base_ThemeCommon::init_smarty();

                if ($header) {
                    $theme->assign('header', array(__('Field'), __('Old value'), __('New value')));
                }

                $theme->assign('events', $events_display);

                $tpl = 'changes_list';
                if (Utils_WatchdogCommon::email_mode()) {
                    $record_data = self::get_record_tooltip_data($tab, $rid);
                    $theme->assign('record', $record_data);
                    $tpl = 'changes_list_email';
                }
                ob_start();
                Base_ThemeCommon::display_smarty($theme,'Utils_RecordBrowser', $tpl);
                $output = ob_get_clean();

                $ret['events'] = $output;
            } else {
                // if we've generated empty events for certain record, then
                // it's possible that some of the fields, that have changed,
                // are hidden so we have to check if there are any other events
                // If all events are the same and output is empty we can safely
                // mark all as notified.
                $all_events = Utils_WatchdogCommon::check_if_notified($tab, $rid);
                if (count($all_events) == count($events)) {
                    Utils_WatchdogCommon::notified($tab, $rid);
                }
                $ret = null;
            }
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
            Base_BoxCommon::push_module(Utils_RecordBrowser::module_name(),'view_entry_with_REQUEST',array($action, $id, array(), true, $_REQUEST),array($tab));
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
        foreach (self::$table_rows as $desc) {
            if ($desc['visible'] && !isset($arg[$desc['id']])) $ret[$desc['id']] = false;
            elseif (!$desc['visible'] && isset($arg[$desc['id']])) $ret[$desc['id']] = true;
        }
        return $ret;
    }

    public static function autoselect_label($tab_id, $args) {
//     	$args = array($tab, $tab_crits, $format_callback, $params);

        $param = self::decode_select_param($args[3]);
        
        $val = self::decode_record_token($tab_id, $param['single_tab']);

        if (!$val) return '';

        list($tab, $record_id) = $val;

        if ($param['cols'])
        	return self::create_linked_label($tab, $param['cols'], $record_id, true);
        else
        	return self::create_default_linked_label($tab, $record_id, true, false);            
    }
    
    private static $automulti_order_tabs;
    public static function automulti_order_by($a,$b) {
	    $aa = _V($a);
	    $bb = _V($b);
	    $aam = preg_match(self::$automulti_order_tabs,$aa) || preg_match(self::$automulti_order_tabs,$a);
	    $bbm = preg_match(self::$automulti_order_tabs,$bb) || preg_match(self::$automulti_order_tabs,$b);
	    if($aam && !$bbm)
		return -1;
	    if($bbm && !$aam)
		return 1;
	    return strcasecmp($aa,$bb);
	}

    public static function automulti_suggestbox($str, $tab, $tab_crits, $f_callback, $param) {
    	$param = self::decode_select_param($param);
    	
		$words = array_filter(explode(' ',$str));
		$words_db = $words;
		self::$automulti_order_tabs = array();
		foreach($words_db as & $w) {
	    	if(mb_strlen($w)>=3) self::$automulti_order_tabs[] = preg_quote($w,'/');
	    	$w = "%$w%";
		}
		self::$automulti_order_tabs = '/('.implode('|',self::$automulti_order_tabs).')/i';

        $tabs = $param['select_tabs'];
        foreach($tabs as & $t) $t = DB::qstr($t);
	    $tabs = DB::GetAssoc('SELECT tab,caption FROM recordbrowser_table_properties WHERE tab IN ('.implode(',',$tabs).')');
	    
        $single_tab = $param['single_tab'];
	
		uasort($tabs,array('Utils_RecordBrowserCommon','automulti_order_by'));

        $ret = array();

        //backward compatibility
        if ($single_tab) {
        	if (is_array($tab_crits) && !isset($tab_crits[$single_tab])) $tab_crits = array($single_tab=>$tab_crits);
        }
        foreach($tabs as $t=>$caption) {
            if(!empty($tab_crits) && !isset($tab_crits[$t])) continue;
            
            $access_crits = self::get_access_crits($t, 'selection');
            if ($access_crits===false) continue;
            if ($access_crits!==true && (is_array($access_crits) || $access_crits instanceof Utils_RecordBrowser_CritsInterface)) {
            	if((is_array($tab_crits[$t]) && $tab_crits[$t]) || $tab_crits[$t] instanceof Utils_RecordBrowser_CritsInterface)
            		$tab_crits[$t] = self::merge_crits($tab_crits[$t], $access_crits);
                else 
                	$tab_crits[$t] = $access_crits;
            }
           
            $fields = $param['cols'];
            if(!$fields) $fields = DB::GetCol("SELECT field FROM {$t}_field WHERE active=1 AND visible=1 AND (type NOT IN ('calculated','page_split','hidden') OR (type='calculated' AND param is not null AND param!=''))");

            $words_db_tmp = $words_db;
            $words_tmp = $words;
            if (!$single_tab) {
                foreach ($words_tmp as $pos => $word) {
                    $expr = '/' . preg_quote($word, '/') . '/i';
                    if (preg_match($expr, $caption) || preg_match($expr, _V($caption))) {
                        unset($words_db_tmp[$pos]);
                        unset($words_tmp[$pos]);
                    }
                }
            }
            $str_db = '%' . implode(' ', $words_tmp) . '%';

            $crits2A = array();
            $crits2B = array();
            $order = array();
            foreach ($fields as $f) {
                $field_id = self::get_field_id($f);
                $crits2A = self::merge_crits($crits2A, array('~' . $field_id => $str_db), true);
                $crits2B = self::merge_crits($crits2B, array('~' . $field_id => $words_db_tmp), true);
                $order[$field_id] = 'ASC';
            }
            $crits3A = self::merge_crits(isset($tab_crits[$t])?$tab_crits[$t]:array(),$crits2A);
            $crits3B = self::merge_crits(isset($tab_crits[$t])?$tab_crits[$t]:array(),$crits2B);

            $records = self::get_records($t, $crits3A, array(), $order, 10);

            foreach ($records as $r) {
                if(!self::get_access($t,'view',$r)) continue;
                $ret[($single_tab?'':$t.'/').$r['id']] = self::call_select_item_format_callback($f_callback, $t.'/'.$r['id'], array($tab, $crits3A, $f_callback, $param));
            }

            $records = self::get_records($t, $crits3B, array(), $order, 10);

            foreach ($records as $r) {
				if(isset($ret[($single_tab?'':$t.'/').$r['id']]) ||
            	    !self::get_access($t,'view',$r)) continue;
                $ret[($single_tab?'':$t.'/').$r['id']] = self::call_select_item_format_callback($f_callback, $t.'/'.$r['id'], array($tab, $crits3B, $f_callback, $param));
            }
            
            if(count($ret)>=10) break;
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
        
    public static function replace_clipboard_pattern($text, $data) {
    	/* some complicate preg match to find every occurence
    	 * of %{ .. {f_name} .. } pattern
    	 */
    	$match = [];
    	if (preg_match_all('/%\{(([^%\}\{]*?\{[^%\}\{]+?\}[^%\}\{]*?)+?)\}/', $text, $match)) { // match for all patterns %{...{..}...}
    		foreach ($match[0] as $k => $matched_string) {
    			$text_replace = $match[1][$k];
    			$changed = false;
    			$second_match = [];
    			while(preg_match('/\{(.+?)\}/', $text_replace, $second_match)) { // match for keys in braces {key}
    				$replace_value = '';
    				if(array_key_exists($second_match[1], $data)) {
    					$replace_value = $data[$second_match[1]];
    					$changed = true;
    				}
    				$text_replace = str_replace($second_match[0], $replace_value, $text_replace);
    			}
    			if(! $changed ) $text_replace = '';
    			$text = str_replace($matched_string, $text_replace, $text);
    		}
    	}
    	
    	return $text;
    }
    	
	public static function get_field_tooltip($label) {
		if(strpos($label,'Utils_Tooltip')!==false) return $label;
		$args = func_get_args();
		array_shift($args);
		return Utils_TooltipCommon::ajax_create($label, array('Utils_RecordBrowserCommon', 'ajax_get_field_tooltip'), $args);
	}
	
	public static function ajax_get_field_tooltip() {
		$args = func_get_args();
		$type = array_shift($args);
		switch ($type) {
            case 'autonumber':
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
									} else $cap = '<b>'.self::get_caption($args[0]).'</b>';
									$ret .= ' '.__('of').' '.$cap;
								}
								if (isset($args[1])) {
									$val = self::crits_to_words($args[0], $args[1]);
									if ($val) $ret .= ' '.__('for which').'<br />&nbsp;&nbsp;&nbsp;'.$val;
								}
								return $ret;
			case 'multiselect':	$ret = __('Select multiple');
								if (isset($args[0])) {
									if (is_array($args[0])) {
										$cap = array();
										foreach ($args[0] as $t) $cap[] = '<b>'.self::get_caption($t).'</b>';
										$cap = implode(' '.__('or').' ',$cap);
									} else $cap = '<b>'.self::get_caption($args[0]).'</b>';
									$ret .= ' '.$cap;
								}
								if (isset($args[1])) {
									$val = self::crits_to_words($args[0], $args[1]);
									if ($val) $ret .= ' '.__('for which').'<br />&nbsp;&nbsp;&nbsp;'.$val;
								}
								return $ret;
		}
		return __('No additional information');
	}
	
	public static $date_values = array('-1 year'=>'1 year back','-6 months'=>'6 months back','-3 months'=>'3 months back','-2 months'=>'2 months back','-1 month'=>'1 month back','-2 weeks'=>'2 weeks back','-1 week'=>'1 week back','-6 days'=>'6 days back','-5 days'=>'5 days back','-4 days'=>'4 days back','-3 days'=>'3 days back','-2 days'=>'2 days back','-1 days'=>'1 days back','today'=>'current day','+1 days'=>'1 days forward','+2 days'=>'2 days forward','+3 days'=>'3 days forward','+4 days'=>'4 days forward','+5 days'=>'5 days forward','+6 days'=>'6 days forward','+1 week'=>'1 week forward','+2 weeks'=>'2 weeks forward','+1 month'=>'1 month forward','+2 months'=>'2 months forward','+3 months'=>'3 months forward','+6 months'=>'6 months forward','+1 year'=>'1 year forward');
	public static function crits_to_words($tab, $crits, $html_decoration=true) {
        if (!is_object($crits)) {
            $crits = Utils_RecordBrowser_Crits::from_array($crits);
        }
        $crits = $crits->replace_special_values(true);
        $c2w = new Utils_RecordBrowser_CritsToWords($tab);
        $c2w->enable_html_decoration($html_decoration);
        return $c2w->to_words($crits);
	}

    public static function get_printer($tab)
    {
        $class = DB::GetOne('SELECT printer FROM recordbrowser_table_properties WHERE tab=%s',$tab);
        if($class && class_exists($class))
            return new $class();
        return new Utils_RecordBrowser_RecordPrinter();
    }
    ////////////////////////////
    // default QFfield callbacks
    
    public static function get_default_QFfield_callback($type) {
        $types = array('hidden', 'checkbox', 'calculated', 'integer', 'float',
            'currency', 'text', 'long text', 'date', 'timestamp', 'time',
            'commondata', 'select', 'multiselect', 'autonumber', 'file');
        if (array_search($type, $types) !== false) {
            return __CLASS__. '::QFfield_' . self::get_field_id($type);
        }
        return null;
    }
    
    public static function QFfield_static_display(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
        if ($mode !== 'add' && $mode !== 'edit') {
            if ($desc['type'] != 'checkbox' || isset($rb_obj->display_callback_table[$field])) {
                $def = self::get_val($rb_obj->tab, $field, $rb_obj->record, false, $desc);
                $form->addElement('static', $field, $label, $def, array('id' => $field));
                return true;
            }
        }
        return false;
    }

    public static function QFfield_hidden(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
        $form->addElement('hidden', $field);
        $form->setDefaults(array($field => $default));
    }

    public static function QFfield_checkbox(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
        $label = Utils_RecordBrowserCommon::get_field_tooltip($label, $desc['type']);
        $el = $form->addElement('advcheckbox', $field, $label, '', array('id' => $field));
        $el->setValues(array('0','1'));
        if ($mode !== 'add')
            $form->setDefaults(array($field => $default));
    }

    public static function QFfield_calculated(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
        if (self::QFfield_static_display($form, $field, $label, $mode, $default, $desc, $rb_obj))
            return;
        
        $label = Utils_RecordBrowserCommon::get_field_tooltip($label, $desc['type']);
        $form->addElement('static', $field, $label);
        if (!is_array($rb_obj->record))
            $values = $rb_obj->custom_defaults;
        else {
            $values = $rb_obj->record;
            if (is_array($rb_obj->custom_defaults))
                $values = $values + $rb_obj->custom_defaults;
        }
        $val = isset($values[$desc['id']]) ?
            self::get_val($rb_obj->tab, $field, $values, true, $desc)
            : '';
        if (!$val)
            $val = '[' . __('formula') . ']';
        $record_id = isset($rb_obj->record['id']) ? $rb_obj->record['id'] : null;
        $form->setDefaults(array($field => '<div class="static_field" id="' . Utils_RecordBrowserCommon::get_calculated_id($rb_obj->tab, $field, $record_id) . '">' . $val . '</div>'));
    }

    public static function QFfield_integer(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
        if (self::QFfield_static_display($form, $field, $label, $mode, $default, $desc, $rb_obj))
            return;

        $label = Utils_RecordBrowserCommon::get_field_tooltip($label, $desc['type']);
        $form->addElement('text', $field, $label, array('id' => $field));
        $form->addRule($field, __('Only integer numbers are allowed.'), 'regex', '/^\-?[0-9]*$/');
        if ($mode !== 'add')
            $form->setDefaults(array($field => $default));
    }

    public static function QFfield_float(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
        if (self::QFfield_static_display($form, $field, $label, $mode, $default, $desc, $rb_obj))
            return;

        $label = Utils_RecordBrowserCommon::get_field_tooltip($label, $desc['type']);
        $form->addElement('text', $field, $label, array('id' => $field));
        $form->addRule($field, __('Only numbers are allowed.'), 'numeric');
        if ($mode !== 'add')
            $form->setDefaults(array($field => $default));
    }

    public static function QFfield_currency(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
        if (self::QFfield_static_display($form, $field, $label, $mode, $default, $desc, $rb_obj))
            return;

        $label = Utils_RecordBrowserCommon::get_field_tooltip($label, $desc['type']);
        $form->addElement('currency', $field, $label, (isset($desc['param']) && is_array($desc['param']))?$desc['param']:array(), array('id' => $field));
        if ($mode !== 'add')
            $form->setDefaults(array($field => $default));
        // set element value to persist currency over soft submit
        if ($form->isSubmitted() && $form->exportValue('submited') == false) {
            $default = $form->exportValue($field);
            $form->getElement($field)->setValue($default);
        }
    }

    public static function QFfield_text(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
        if (self::QFfield_static_display($form, $field, $label, $mode, $default, $desc, $rb_obj))
            return;
        
        $label = Utils_RecordBrowserCommon::get_field_tooltip($label, $desc['type'], $desc['param']);
        $form->addElement('text', $field, $label, array('id' => $field, 'maxlength' => $desc['param']));
        $form->addRule($field, __('Maximum length for this field is %s characters.', array($desc['param'])), 'maxlength', $desc['param']);
        if ($mode !== 'add')
            $form->setDefaults(array($field => $default));
    }

    public static function QFfield_long_text(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
        if (self::QFfield_static_display($form, $field, $label, $mode, $default, $desc, $rb_obj))
            return;
        
        $label = Utils_RecordBrowserCommon::get_field_tooltip($label, $desc['type']);
        $form->addElement('textarea', $field, $label, array('id' => $field));
        if ($mode !== 'add')
            $form->setDefaults(array($field => $default));
    }

    public static function QFfield_date(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
        if (self::QFfield_static_display($form, $field, $label, $mode, $default, $desc, $rb_obj))
            return;
        
        $label = Utils_RecordBrowserCommon::get_field_tooltip($label, $desc['type']);
        $form->addElement('datepicker', $field, $label, array('id' => $field));
        if ($mode !== 'add')
            $form->setDefaults(array($field => $default));
    }

    public static function timestamp_required($v) {
        return $v['__datepicker'] !== '' && Base_RegionalSettingsCommon::reg2time($v['__datepicker'], false) !== false;
    }

    public static function QFfield_timestamp(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
        if (self::QFfield_static_display($form, $field, $label, $mode, $default, $desc, $rb_obj))
            return;

        $f_param = array('id' => $field);
        if ($desc['param'])
            $f_param['optionIncrement'] = array('i' => $desc['param']);
        $label = Utils_RecordBrowserCommon::get_field_tooltip($label, $desc['type']);
        $form->addElement('timestamp', $field, $label, $f_param);
        static $rule_defined = false;
        if (!$rule_defined) {
            $form->registerRule('timestamp_required', 'callback', 'timestamp_required', __CLASS__);
            $rule_defined = true;
        }
        if (isset($desc['required']) && $desc['required'])
            $form->addRule($field, __('Field required'), 'timestamp_required');
        if ($mode !== 'add' && $default)
            $form->setDefaults(array($field => $default));
    }

    public static function QFfield_time(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
        if (self::QFfield_static_display($form, $field, $label, $mode, $default, $desc, $rb_obj))
            return;

        $time_format = Base_RegionalSettingsCommon::time_12h() ? 'h:i a' : 'H:i';
        $lang_code = Base_LangCommon::get_lang_code();
        $label = Utils_RecordBrowserCommon::get_field_tooltip($label, $desc['type']);
        $minute_increment = 5;
        if ($desc['param']) {
            $minute_increment = $desc['param'];
        }
        $form->addElement('timestamp', $field, $label, array('date' => false, 'format' => $time_format, 'optionIncrement' => array('i' => $minute_increment), 'language' => $lang_code, 'id' => $field));
        if ($mode !== 'add' && $default)
            $form->setDefaults(array($field => $default));
    }

    public static function QFfield_commondata(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
        if (self::QFfield_static_display($form, $field, $label, $mode, $default, $desc, $rb_obj))
            return;

        $param = explode('::', $desc['param']['array_id']);
        foreach ($param as $k => $v)
            if ($k != 0)
                $param[$k] = self::get_field_id($v);
        $label = Utils_RecordBrowserCommon::get_field_tooltip($label, $desc['type'], $desc['param']['array_id']);
        $form->addElement($desc['type'], $field, $label, $param, array('empty_option' => true, 'order' => $desc['param']['order']), array('id' => $field));
        if ($mode !== 'add')
            $form->setDefaults(array($field => $default));
    }

    public static function QFfield_select(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
        if (self::QFfield_static_display($form, $field, $label, $mode, $default, $desc, $rb_obj))
            return;
        
        $record = $rb_obj->record;
        $comp = array();
        $param = self::decode_select_param($desc['param']);
        $multi_adv_params = self::call_select_adv_params_callback($param['adv_params_callback'], $record);
        $format_callback = $multi_adv_params['format_callback'];
        $rec_count = 0;
        if ($param['single_tab'] == '__COMMON__') {
        	if (empty($param['array_id']))
        		trigger_error("Commondata array id not set for field: $field", E_USER_ERROR);
            $data = Utils_CommonDataCommon::get_translated_tree($param['array_id'], $param['order']);
            if (!is_array($data))
                $data = array();
            $comp = $comp + $data;
            $label = Utils_RecordBrowserCommon::get_field_tooltip($label, 'commondata', $param['array_id']);
        } else {
        	$tab_crits = self::get_select_tab_crits($param, $record);           
                
        	$tabs = array_keys($tab_crits);

        	foreach($tabs as $t) {
                $rec_count += Utils_RecordBrowserCommon::get_records_count($t, $tab_crits[$t]);
                
                if ($rec_count > Utils_RecordBrowserCommon::$options_limit) break;
            }
            if ($rec_count <= Utils_RecordBrowserCommon::$options_limit) {
                foreach($tabs as $t) {
                    $records = Utils_RecordBrowserCommon::get_records($t, $tab_crits[$t], array(), $multi_adv_params['order']);
                    foreach($records as $key=>$rec) {
                        if(!self::get_access($t,'view',$rec)) continue;
                        $tab_id = ($param['single_tab']?'':$t.'/').$key;
                        $comp[$tab_id] = self::call_select_item_format_callback($multi_adv_params['format_callback'], $tab_id, array($rb_obj->tab, $tab_crits[$t], $multi_adv_params['format_callback'], $param));
                    }
                }
            }
            
            if (isset($record[$field])) {
            	if (!is_array($record[$field])) {
            		if ($record[$field] != '')
            			$record[$field] = array($record[$field] => $record[$field]);
            		else
            			$record[$field] = array();
            	}
            }
            if ($default) {
            	if (!is_array($default))
            		$record[$field][$default] = $default;
            	else {
            		foreach ($default as $v)
            			$record[$field][$v] = $v;
            	}
            }            
            if (isset($record[$field])) {
            	foreach ($record[$field] as $tab_id) {
            		if (isset($comp[$tab_id])) continue;
            		$vals = self::decode_record_token($tab_id, $param['single_tab']);
            		if (!$vals) continue;
            		list($t,$rid) = $vals;
                    if (!isset($tab_crits[$t])) continue;
            		$comp[$tab_id] = self::call_select_item_format_callback($multi_adv_params['format_callback'], $tab_id, array($rb_obj->tab, $tab_crits[$t], $multi_adv_params['format_callback'], $param));
            	}
            }
            if (empty($multi_adv_params['order']))
                natcasesort($comp);

            if($param['single_tab'])
                $label = self::get_field_tooltip($label, $desc['type'], $param['single_tab'], $tab_crits[$param['single_tab']]);
        }
        if ($rec_count > Utils_RecordBrowserCommon::$options_limit) {
            if ($desc['type'] == 'multiselect') {
                $el = $form->addElement('automulti', $field, $label, array('Utils_RecordBrowserCommon', 'automulti_suggestbox'), array($rb_obj->tab, $tab_crits, $format_callback, $desc['param']), $format_callback);
                if (method_exists($rb_obj, 'init_module')) { // fixes mobile edit issue - to be removed when mobile.php will be removed
                    ${'rp_' . $field} = $rb_obj->init_module(Utils_RecordBrowser_RecordPicker::module_name(), array());
                    $filters_defaults = isset($multi_adv_params['filters_defaults']) ? $multi_adv_params['filters_defaults'] : array();
                    $rb_obj->display_module(${'rp_' . $field}, array($tabs, $field, $format_callback, $param['crits_callback']?:$tab_crits, array(), array(), array(), $filters_defaults));
                    $el->set_search_button('<a ' . ${'rp_' . $field}->create_open_href() . ' ' . Utils_TooltipCommon::open_tag_attrs(__('Advanced Selection')) . ' href="javascript:void(0);"><img border="0" src="' . Base_ThemeCommon::get_template_file('Utils_RecordBrowser', 'icon_zoom.png') . '"></a>');
                }
            } else
                $el = $form->addElement('autoselect', $field, $label, $comp, array(array('Utils_RecordBrowserCommon', 'automulti_suggestbox'), array($rb_obj->tab, $tab_crits, $format_callback, $desc['param'])), $format_callback);
        } else {
            if ($desc['type'] === 'select')
                $comp = array('' => '---') + $comp;
            $form->addElement($desc['type'], $field, $label, $comp, array('id' => $field));
        }
        if ($mode !== 'add')
            $form->setDefaults(array($field => $default));
    }

    public static function QFfield_multiselect(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
        self::QFfield_select($form, $field, $label, $mode, $default, $desc, $rb_obj);
    }
    
    public static function QFfield_autonumber(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
        if (self::QFfield_static_display($form, $field, $label, $mode, $default, $desc, $rb_obj))
            return;
        $label = Utils_RecordBrowserCommon::get_field_tooltip($label, $desc['type']);
        $value = $default ? $default : self::format_autonumber_str($desc['param'], null);
        $form->addElement('static', $field, $label);
        $record_id = isset($rb_obj->record['id']) ? $rb_obj->record['id'] : null;
        $field_id = Utils_RecordBrowserCommon::get_calculated_id($rb_obj->tab, $field, $record_id);
        $val = '<div class="static_field" id="' . $field_id . '">' . $value . '</div>';
        $form->setDefaults(array($field => $val));
    }

	//region File
	public static function display_file($r, $nolink=false, $desc=null, $tab=null)
	{
		$labels = [];
		$inline_nodes = [];
		$fileStorageIds = self::decode_multi($r[$desc['id']]);
		$fileHandler = new Utils_RecordBrowser_FileActionHandler();
		foreach($fileStorageIds as $fileStorageId) {
			if(!empty($fileStorageId)) {
				$actions = $fileHandler->getActionUrlsRB($fileStorageId, $tab, $r['id'], $desc['id']);
				$labels[]= Utils_FileStorageCommon::get_file_label($fileStorageId, $nolink, true, $actions);
				if (!($desc['nopreview']?? false))
					$inline_nodes[]= Utils_FileStorageCommon::get_file_inline_node($fileStorageId, $actions, $desc['max-width']?? '200px');
			}
		}
		$inline_nodes = array_filter($inline_nodes);
		
		return implode('<br>', $labels) . ($inline_nodes? '<hr>': '') . implode('&nbsp;', $inline_nodes);
	}

	public static function QFfield_file(&$form, $field, $label, $mode, $default, $desc, $rb_obj)
	{
		if (self::QFfield_static_display($form, $field, $label, $mode, $default, $desc, $rb_obj))
			return;
		$record_id = isset($rb_obj->record['id']) ? $rb_obj->record['id'] : 'new';
		$module_id = md5($rb_obj->tab . '/' . $record_id . '/' . $field);
		/** @var Utils_FileUpload_Dropzone $dropzoneField */
		$dropzoneField = Utils_RecordBrowser::$rb_obj->init_module('Utils_FileUpload#Dropzone', null, $module_id);
        $default = self::decode_multi($default);
		if ($default) {
		    $files = [];
            foreach ($default as $filestorageId) {
                $meta = Utils_FileStorageCommon::meta($filestorageId);
                $arr = [
                    'filename' => $meta['filename'],
                    'type' => $meta['type'],
                    'size' => $meta['size'],
                ];
                $backref = substr($meta['backref'], 0, 3) == 'rb:' ? explode('/', substr($meta['backref'], 3)) : [];
                if (count($backref) === 3) {
                    list ($br_tab, $br_record, $br_field) = $backref;
                    $file_handler = new Utils_RecordBrowser_FileActionHandler();
                    $actions = $file_handler->getActionUrlsRB($filestorageId, $br_tab, $br_record, $br_field);
                    if (isset($actions['preview'])) {
                        $arr['file'] = $actions['preview'];
                    }
                }
                $files[$filestorageId] = $arr;
            }
			$dropzoneField->set_defaults($files);
		}
        if (isset($desc['param']['max_files']) && $desc['param']['max_files'] !== false) {
            $dropzoneField->set_max_files($desc['param']['max_files']);
        }
        if (isset($desc['param']['accepted_files']) && $desc['param']['accepted_files'] !== false) {
        	$dropzoneField->set_accepted_files($desc['param']['accepted_files']);
        }
		$dropzoneField->add_to_form($form, $field, $label);
	}
	//endregion
    
    public static function cron() {
        return array('indexer' => 10);
    }

    public static function index_record($tab,$record,$table_rows=null,$tab_id=null) {
        if($tab_id===null) $tab_id = DB::GetOne('SELECT id FROM recordbrowser_table_properties WHERE tab=%s',array($tab));
        if($table_rows===null) $table_rows = self::init($tab);
        
        $record = self::record_processing($tab, $record, 'index');
        DB::StartTrans();
        if($record) {
            DB::Execute('DELETE FROM recordbrowser_search_index WHERE tab_id=%d AND record_id=%d',array($tab_id,$record['id']));
            $cleanup_str = function($value) {
                $decoded = html_entity_decode($value);
                $added_spaces = str_replace('<', ' <', $decoded);
                $stripped = strip_tags($added_spaces);
                $removed_spaces = preg_replace('/[ ]+/', ' ', $stripped);
                return mb_strtolower(trim($removed_spaces));
            };
            $insert_vals = array();
            foreach($table_rows as $field_info) {
                $field = $field_info['id'];
                if(!isset($record[$field])) continue;
                ob_start();
                $text = self::get_val($tab,$field,$record, true);
                ob_end_clean();
                $text = $cleanup_str($text);
                if ($text) {
                    $insert_vals[] = $tab_id;
                    $insert_vals[] = $record['id'];
                    $insert_vals[] = $field_info['pkey'];
                    $insert_vals[] = $text;
                }
            }
            $insert_query = implode(',', array_fill(0, count($insert_vals) / 4, '(%d, %d, %d, %s)'));
            DB::Execute('INSERT INTO recordbrowser_search_index VALUES ' . $insert_query, $insert_vals);
        }
        DB::Execute('UPDATE '.$tab.'_data_1 SET indexed=1 WHERE id=%d',array($record['id']));
        DB::CompleteTrans();
    }

    public static function clear_search_index($tab)
    {
        $tab_id = DB::GetOne('SELECT id FROM recordbrowser_table_properties WHERE tab=%s',array($tab));
        if ($tab_id) {
            DB::Execute('DELETE FROM recordbrowser_search_index WHERE tab_id=%d',array($tab_id));
            DB::Execute('UPDATE ' . $tab . '_data_1 SET indexed=0');
            return true;
        }
        return false;
    }
    
    public static function indexer($limit=null,&$total=0) {
        $limit_sum = 0;
        $limit_file = DATA_DIR.'/Utils_RecordBrowser/limit';
        if(defined('RB_INDEXER_LIMIT_QUERIES')) { //limit queries per hour
            $time = time();
            $limit_time = 0;
            if(file_exists($limit_file)) {
                $tmp = array_filter(explode("\n",file_get_contents($limit_file)));
                $limit_time = array_shift($tmp);
                if($limit_time>$time-3600) {
                    $limit_sum = array_sum($tmp);
                    if($limit_sum>RB_INDEXER_LIMIT_QUERIES) return;
                }
            }
            if($limit_sum==0)
                file_put_contents($limit_file,$time."\n", LOCK_EX);
        }

        if(!$limit) $limit = defined('RB_INDEXER_LIMIT_RECORDS') ? RB_INDEXER_LIMIT_RECORDS : 300;
        $tabs = DB::GetAssoc('SELECT id,tab FROM recordbrowser_table_properties WHERE search_include>0');
        foreach($tabs as $tab_id=>$tab) {
            $lock = DATA_DIR.'/Utils_RecordBrowser/'.$tab_id.'.lock';
            if(file_exists($lock) && filemtime($lock)>time()-1200) continue;
    
            $table_rows = self::init($tab);
            self::$admin_filter = ' <tab>.indexed=0 AND <tab>.active=1 AND ';
            $ret = self::get_records($tab,array(),array(),array(),$limit,true);
            self::$admin_filter = '';
            
            if(!$ret) continue;

            register_shutdown_function(create_function('','@unlink("'.$lock.'");'));
            if(file_exists($lock) && filemtime($lock)>time()-1200) continue;
            file_put_contents($lock,'');

            foreach($ret as $row) {
                self::index_record($tab,$row,$table_rows,$tab_id);
                
                $total++;
                if($total>=$limit) break;
                if(defined('RB_INDEXER_LIMIT_QUERIES') && RB_INDEXER_LIMIT_QUERIES<$limit_sum+DB::GetQueriesQty()) break;
            }
            
            @unlink($lock);
            
            if($total>=$limit) break;
            if(defined('RB_INDEXER_LIMIT_QUERIES') && RB_INDEXER_LIMIT_QUERIES<$limit_sum+DB::GetQueriesQty()) break;
        }

        if(defined('RB_INDEXER_LIMIT_QUERIES')) {
            file_put_contents($limit_file,DB::GetQueriesQty()."\n",FILE_APPEND | LOCK_EX);
        }
        
    }

    public static function search($search, $categories)
    {
        $x = new Utils_RecordBrowser_Search($categories);
        $ret = $x->search_results($search);
        return $ret;
    }
    
    public static function search_categories() {
        $tabs = DB::GetAssoc('SELECT t.id,t.tab,t.search_include FROM recordbrowser_table_properties t WHERE t.search_include>0 AND t.id IN (SELECT DISTINCT m.tab_id FROM recordbrowser_search_index m)');
        $ret = array();
        foreach($tabs as $tab_id=>$tab) {
            $caption = self::get_caption($tab['tab']);
            if(!$caption) continue;
            $ret[$tab_id] = array('caption'=>$caption,'checked'=>$tab['search_include']==1);
        }
        uasort($ret,create_function('$a,$b','return strnatcasecmp($a["caption"],$b["caption"]);'));
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
                $val = Utils_RecordBrowserCommon::get_val($tab,$k,$rec,true,$col);
                if($val==='') continue;
                print('<li>'._V($col['name']).': '.$val.'</li>'); // TRSL
            }
            print('</ul>');
        } else {
            foreach($cols as $k=>$col) {
                $val = Utils_RecordBrowserCommon::get_val($tab,$k,$rec,true,$col);
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
            $defaults = $rec = self::record_processing($tab, $rec, 'editing');
        }

        $QFfield_callback_table = array();
        $ret = DB::Execute('SELECT * FROM '.$tab.'_callback WHERE freezed=0');
        while ($row = $ret->FetchRow()) {
            $QFfield_callback_table[$row['field']] = $row['callback'];
        }
        $defaults = array_merge($defaults,$_SESSION['rb_'.$tab.'_defaults']);

        $qf = new HTML_QuickForm('rb_edit', 'post', 'mobile.php?'.http_build_query($_GET));
        foreach($cols as $field=>$args) {
            if(isset($access[$args['id']]) && !$access[$args['id']]) continue;

            if(isset($rec[$args['id']]))
                $val = $rec[$args['id']];
            elseif(isset($defaults[$args['id']]))
                $val = $defaults[$args['id']];
            else
                $val = null;
            $label = _V($args['name']); // TRSL
            if(isset($QFfield_callback_table[$field])) {
                $mobile_rb = new Utils_RecordBrowserMobile($tab, $rec);
                self::call_QFfield_callback($QFfield_callback_table[$field], $qf, $args['id'], $label, $mode, $val, $args, $mobile_rb, null);
                if($mode=='edit')
                    unset($defaults[$args['id']]);
                continue;
            }

            switch ($args['type']) {
                case 'calculated':
                    $qf->addElement('static', $args['id'], $label);
                    if (!is_array($rec))
                        $values = $defaults;
                    else {
                        $values = $rec;
                        if (is_array($defaults)) $values = $values + $defaults;
                    }
                    if(!isset($values[$args['id']])) $values[$args['id']] = '';
                    $val = Utils_RecordBrowserCommon::get_val($tab, $field, $values, true, $args);
                    if ($val !== null)
                        $qf->setDefaults(array($args['id'] => $val));
                    break;
                case 'integer':
                case 'float':
                    $qf->addElement('text', $args['id'], $label);
                    if ($args['type'] == 'integer')
                        $qf->addRule($args['id'], __('Only integer numbers are allowed.'), 'regex', '/^[0-9]*$/');
                    else
                        $qf->addRule($args['id'], __('Only numbers are allowed.'), 'numeric');
                    if ($val !== null)
                        $qf->setDefaults(array($args['id'] => $val));
                    break;
                case 'checkbox':
                    $qf->addElement('checkbox', $args['id'], $label, '');
                    if ($val !== null)
                        $qf->setDefaults(array($args['id'] => $val));
                    break;
                case 'currency':
                    $qf->addElement('currency', $args['id'], $label);
                    if ($val !== null)
                        $qf->setDefaults(array($args['id'] => $val));
                    break;
                case 'text':
                    $qf->addElement('text', $args['id'], $label, array('maxlength' => $args['param']));
                    $qf->addRule($args['id'], __('Maximum length for this field is %s characters.', array($args['param'])), 'maxlength', $args['param']);
                    if ($val !== null)
                        $qf->setDefaults(array($args['id'] => $val));
                    break;
                case 'long text':
                    $qf->addElement('textarea', $args['id'], $label, array('maxlength' => 200));
                    $qf->addRule($args['id'], __('Maximum length for this field in mobile edition is 200 chars.'), 'maxlengt', 200);
                    if ($val !== null)
                        $qf->setDefaults(array($args['id'] => $val));
                    break;
                case 'commondata':
                    $param = explode('::', $args['param']['array_id']);
                    foreach ($param as $k => $v) if ($k != 0) $param[$k] = self::get_field_id($v);
                    if (count($param) == 1) {
                        $qf->addElement($args['type'], $args['id'], $label, $param, array('empty_option' => true, 'id' => $args['id'], 'order' => $args['param']['order']));
                        if ($val !== null)
                            $qf->setDefaults(array($args['id'] => $val));
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
                            if($tab2=='__RECORDSETS__') continue; //skip multi recordsets chained selector
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
                            if ($val)
                                $qf->setDefaults(array($args['id'] => $val));
                            break;
                case 'timestamp':   $qf->addElement('date',$args['id'],$label,array('format'=>'d M Y H:i', 'minYear'=>date('Y')-95,'maxYear'=>date('Y')+5, 'addEmptyOption'=>true, 'emptyOptionText'=>'--'));
                            if($val) {
                                $default = Base_RegionalSettingsCommon::time2reg($val, true, true, true, false);
                                $qf->setDefaults(array($args['id'] => $default));
                            }
                            break;
                case 'time':        $qf->addElement('date',$args['id'],$label,array('format'=>'H:i', 'addEmptyOption'=>true, 'emptyOptionText'=>'--'));
                            if($val) {
                                $default = Base_RegionalSettingsCommon::time2reg($val, true, true, true, false);
                                $qf->setDefaults(array($args['id'] => $default));
                            }
                            break;
                case 'multiselect': //ignore
                            if($id===false) continue;
                            $val = Utils_RecordBrowserCommon::get_val($tab,$field,$rec,true,$args);
                            if($val==='') continue;
                            $qf->addElement('static',$args['id'],$label);
                            $qf->setDefaults(array($args['id']=>$val));
                            unset($defaults[$args['id']]);
                            break;
            }
            if($args['required'])
                $qf->addRule($args['id'],__('Field required'),'required');
        }

        $qf->addElement('submit', 'submit_button', __('Save'),IPHONE?'class="button white"':'');

        if($qf->validate()) {
            $values = $qf->exportValues();
            foreach ($cols as $v) {
                if ($v['type']=='checkbox' && !isset($values[$v['id']])) $values[$v['id']]=0;
                elseif($v['type']=='date') {
                    if(is_array($values[$v['id']]) && $values[$v['id']]['Y']!=='' && $values[$v['id']]['M']!=='' && $values[$v['id']]['d']!=='')
                        $values[$v['id']] = sprintf("%d-%02d-%02d", $values[$v['id']]['Y'], $values[$v['id']]['M'], $values[$v['id']]['d']);
                    else
                        $values[$v['id']] = '';
                } elseif($v['type']=='timestamp') {
                    if($values[$v['id']]['Y']!=='' && $values[$v['id']]['M']!=='' && $values[$v['id']]['d']!=='' && $values[$v['id']]['H']!=='' && $values[$v['id']]['i']!=='') {
                        $timestamp = $values[$v['id']]['Y'] . '-' . $values[$v['id']]['M'] . '-' . $values[$v['id']]['d'] . ' ' . $values[$v['id']]['H'] . ':' . $values[$v['id']]['i'];
                        $values[$v['id']] = Base_RegionalSettingsCommon::reg2time($timestamp, true);
                    } else
                        $values[$v['id']] = '';
                } elseif($v['type']=='time') {
                    if($values[$v['id']]['H']!=='' && $values[$v['id']]['i']!=='') {
                        $time = recalculate_time(date('Y-m-d'), $values[$v['id']]);
                        $timestamp = Base_RegionalSettingsCommon::reg2time(date('1970-01-01 H:i:s', $time), true);
                        $values[$v['id']] = date('1970-01-01 H:i:s', $timestamp);
                    } else
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

function rb_or($crits, $_ = null)
{
    $args = func_get_args();
    if (count($args) > 1) {
        foreach ($args as $k => $v) {
            if (is_array($v)) {
                $args[$k] = new Utils_RecordBrowser_Crits($v, true);
            }
        }
        $crits = $args;
    } else {
        $crits = $args[0];
    }
    $ret = new Utils_RecordBrowser_Crits($crits, true);
    return $ret;
}

function rb_and($crits, $_ = null)
{
    $args = func_get_args();
    if (count($args) > 1) {
        foreach ($args as $k => $v) {
            if (is_array($v)) {
                $args[$k] = new Utils_RecordBrowser_Crits($v);
            }
        }
        $crits = $args;
    } else {
        $crits = $args[0];
    }
    $ret = new Utils_RecordBrowser_Crits($crits);
    return $ret;
}

require_once 'modules/Utils/RecordBrowser/object_wrapper/include.php';

Utils_RecordBrowser_Crits::register_special_value_callback(array('Utils_RecordBrowserCommon', 'crits_special_values'));

if(!READ_ONLY_SESSION) {
    if(!isset($_SESSION['rb_indexer_token']))
        $_SESSION['rb_indexer_token'] = md5(microtime(true));
    load_js('modules/Utils/RecordBrowser/indexer.js');
    eval_js_once('rb_indexer("'.$_SESSION['rb_indexer_token'].'")');
}
?>