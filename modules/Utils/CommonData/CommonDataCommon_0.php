<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-utils
 * @subpackage CommonData
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_CommonDataCommon extends ModuleCommon {

	/**
	 * For internal use only.
	 */
	public static function admin_caption(){
		return array('label'=>__('Common data'), 'section'=>__('Data'));
	}
	
	public static function admin_access_levels() {
		return false;
	}

	public static function get_id($name, $clear_cache=false) {
		static $cache;
		$name = trim($name,'/');
		$pcs = explode('/',$name);
		$id = -1;
		foreach($pcs as $v) {
			if($v==='') continue; //ignore empty paths
			if(isset($cache[$id][$v]) && $cache[$id][$v]) {
				$id = $cache[$id][$v];
			} else {
				$old_id = $id;
				$id = DB::GetOne('SELECT id FROM utils_commondata_tree WHERE parent_id=%d AND akey=%s',array($id,$v));
				if($id===null)
					$id = false;
				$cache[$old_id][$v] = $id;
				if($id===false)
					return false;
			}
		}
        if($clear_cache) $cache = array();
		return $id;
	}

	public static function new_id($name) {
		$name = trim($name,'/');
		if(!$name) return false;
		$pcs = explode('/',$name);
		$id = -1;
		foreach($pcs as $v) {
			if($v==='') continue;
			$id2 = DB::GetOne('SELECT id FROM utils_commondata_tree WHERE parent_id=%d AND akey=%s',array($id,$v));
			if($id2===false || $id2===null) {
				DB::Execute('INSERT INTO utils_commondata_tree(parent_id,akey) VALUES(%d,%s)',array($id,$v));
				$id = DB::Insert_ID('utils_commondata_tree','id');
			} else
				$id=$id2;
		}
		return $id;
	}

	/**
	 * Creates new node with value.
	 *
	 * @param string array name
	 * @param array initialization value
	 * @param bool whether method should overwrite if array already exists, otherwise the data will be appended
	 */
	public static function set_value($name,$value,$overwrite=true,$readonly=false){
		$id = self::get_id($name);
		if ($id===false){
			$id = self::new_id($name);
			if($id===false) return false;
		} else {
			if (!$overwrite) return false;
		}
		DB::Execute('UPDATE utils_commondata_tree SET value=%s,readonly=%b WHERE id=%d',array($value,$readonly,$id));
		return true;
	}

	/**
	 * Gets node value.
	 *
	 * @param string array name
	 * @param boolean translate?
	 * @return mixed false on invalid name
	 */
	public static function get_value($name,$translate=false){
		static $cache;
		if (isset($cache[$name.'__'.$translate])) return $cache[$name.'__'.$translate];
		$val = false;
		$id = self::get_id($name);
		if($id===false) return false;
		$ret = DB::GetOne('SELECT value FROM utils_commondata_tree WHERE id=%d',array($id));
		if($translate)
			$ret = _V($ret); // ****** CommonData value translation
		$cache[$name.'__'.$translate] = $ret;
		return $ret;
	}

	/**
	 * Gets nodes by keys.
	 *
	 * @param string array name
	 * @return mixed false on invalid name
	 */
	public static function get_nodes($root, array $names){
		static $cache;
		sort($names);
		$uid = md5(serialize($names));
		if(isset($cache[$root][$uid]))
			return $cache[$root][$uid];
		$val = false;
		$id = self::get_id($root);
		if($id===false) return false;
		$ret = DB::GetAssoc('SELECT id,value FROM utils_commondata_tree WHERE parent_id=%d AND (akey=\''.implode($names,'\' OR akey=\'').'\')',array($id));
		$cache[$root][$uid] = $ret;
		return $ret;
	}

	/**
	 * Creates new array for common use.
	 *
	 * @param string array name
	 * @param array initialization value
	 * @param bool whether method should overwrite if array already exists, otherwise the data will be appended
	 */
	public static function new_array($name,$array,$overwrite=false,$readonly=false){
		foreach($array as $k=>$v)
		    if(strpos($k,'/')!==false)
		        trigger_error('Invalid common data key: '.$k,E_USER_ERROR);

		$id = self::get_id($name);
		if ($id!==false){
			if (!$overwrite) {
				self::extend_array($name,$array);
				return true;
			} else {
				self::remove($name);
			}
		}
		$id = self::new_id($name);
		if($id===false) return false;
		if($overwrite)
			DB::Execute('UPDATE utils_commondata_tree SET readonly=%b WHERE id=%d',array($readonly,$id));
		$qty = count($array);
		if ($qty!=0) {
			$qvals = array();
			foreach($array as $k=>$v) {
				$qvals[] = $id;
				$qvals[] = $k;
				$qvals[] = $v;
				$qvals[] = $readonly;
			}
			DB::Execute('INSERT INTO utils_commondata_tree (parent_id, akey, value, readonly) VALUES '.implode(',',array_fill(0, $qty, '(%d,%s,%s,%b)')),$qvals);
		}
		return true;
	}

	/**
	 * Extends common data array.
	 *
	 * @param string array name
	 * @param array values to insert
	 * @param bool whether method should overwrite data if array key already exists, otherwise the data will be preserved
	 */
	public static function extend_array($name,$array,$overwrite=false,$readonly=false){
		foreach($array as $k=>$v)
		    if(strpos($k,'/')!==false)
		        trigger_error('Invalid common data key: '.$k,E_USER_ERROR);

		$id = self::get_id($name);
		if ($id===false){
			self::new_array($name,$array,$overwrite,$readonly);
			return;
		}
		$in_db = DB::GetCol('SELECT akey FROM utils_commondata_tree WHERE parent_id=%s',array($id));
		foreach($array as $k=>$v){
			if (in_array($k,$in_db)) {
				if (!$overwrite) continue;
				DB::Execute('UPDATE utils_commondata_tree SET value=%s,readonly=%b WHERE akey=%s AND parent_id=%d',array($v,$readonly,$k,$id));
			} else {
				DB::Execute('INSERT INTO utils_commondata_tree (parent_id, akey, value, readonly) VALUES (%d,%s,%s,%b)',array($id,$k,$v,$readonly));
			}
		}
	}

	/**
	 * Removes common data array or entry.
	 *
	 * @param string entry name
	 * @return true on success, false otherwise
	 */
	public static function remove($name){
		$id = self::get_id($name, true);
		if ($id===false) return false;
		self::remove_by_id($id);
	}

	/**
	 * Removes common data array or entry using id.
	 *
	 * @param integer entry id
	 * @return true on success, false otherwise
	 */
	public static function remove_by_id($id) {
		$ret = DB::GetCol('SELECT id FROM utils_commondata_tree WHERE parent_id=%d',array($id));
		foreach($ret as $r)
			self::remove_by_id($r);
		DB::Execute('DELETE FROM utils_commondata_tree WHERE id=%d',array($id));
	}

	/**
	 * Returns common data array.
	 *
	 * @param string array name
	 * @param boolean order by key instead of value
	 * @return mixed returns an array if such array exists, false otherwise
	 */
	public static function get_array($name, $order_by_key=false, $readinfo=false, $silent=false){
		static $cache;
		if(isset($cache[$name][$order_by_key][$readinfo]))
			return $cache[$name][$order_by_key][$readinfo];
		$id = self::get_id($name);
		if($id===false)
			if ($silent) return null;
			else trigger_error('Invalid CommonData::get_array() request: '.$name,E_USER_ERROR);
		if($order_by_key)
			$order_by = 'akey ASC';
		else
			$order_by = 'value ASC';
		if($readinfo)
			$ret = DB::GetAssoc('SELECT akey, value, readonly FROM utils_commondata_tree WHERE parent_id=%d ORDER BY '.$order_by,array($id),true);
		else 
			$ret = DB::GetAssoc('SELECT akey, value FROM utils_commondata_tree WHERE parent_id=%d ORDER BY '.$order_by,array($id));
		$cache[$name][$order_by_key][$readinfo] = $ret;
		return $ret;
	}

	public static function get_translated_array($name,$order_by_key=false,$readinfo=false,$silent=false) {
		if ($readinfo) $info = self::get_array($name,$order_by_key,$readinfo,$silent);
		$arr = self::get_array($name,$order_by_key,false,$silent);
		if ($arr===null) return null;
		$arr = self::translate_array($arr);
		if ($order_by_key)
			ksort($arr, SORT_LOCALE_STRING);
		else
			asort($arr, SORT_LOCALE_STRING);
		if ($readinfo) {
			foreach ($arr as $k=>$v) {
				$i = $info[$k];
				$i[0] = $i['value'] = $v;
				$arr[$k] = $i;
			}
		}
		return $arr;
	}

	public static function translate_array(& $arr) {
		foreach($arr as $k=>&$v) {
			if (is_array($v))
				$v = &$v['value'];
			$v = _V($v); // ****** CommonData value translation
		}
		return $arr;
	}

	/**
	 * Counts elements in common data array.
	 *
	 * @param string array name
	 * @return mixed returns an array if such array exists, false otherwise
	 */
	public static function get_array_count($name){
		$id = self::get_id($name);
		if($id===false) return false;
		return DB::GetAssoc('SELECT count(akey) FROM utils_commondata_tree WHERE parent_id=%d',array($id));
	}

	public static function rename_key($parent,$old,$new) {
	    if(strpos($new,'/')!==false)
	        trigger_error('Invalid common data key: '.$new,E_USER_ERROR);


		$id = self::get_id($parent.'/'.$old);
		if($id===false) return false;
		DB::Execute('UPDATE utils_commondata_tree SET akey=%s WHERE id=%d',array($new,$id));
		return true;
	}
	
	public static function get_translated_tree($col, $order_by_key=false, $deep=0) {
		$data = Utils_CommonDataCommon::get_translated_array($col, $order_by_key, false, true);
		if (!$data) return array();
		$output = array();
		foreach ($data as $k=>$v) {
			$output[$k] = $v;
			$sub = self::get_translated_tree($col.'/'.$k, $order_by_key, $deep+1);
			if ($sub) foreach ($sub as $k2=>$v2) {
				$output[$k.'/'.$k2] = '* '.$v2;
			}
		}
		return $output;
	}
}

$GLOBALS['HTML_QUICKFORM_ELEMENT_TYPES']['commondata'] = array('modules/Utils/CommonData/qf.php','HTML_QuickForm_commondata');
$GLOBALS['HTML_QUICKFORM_ELEMENT_TYPES']['commondata_group'] = array('modules/Utils/CommonData/qf_group.php','HTML_QuickForm_commondata_group');

?>
