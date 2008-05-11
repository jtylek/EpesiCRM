<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license SPL
 * @package epesi-utils
 * @subpackage common-data
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_CommonDataCommon extends ModuleCommon implements Base_AdminModuleCommonInterface {

	/**
	 * For internal use only.
	 */
	public static function admin_caption(){
		return "Common data";
	}

	/**
	 * For internal use only.
	 */
	public static function admin_access(){
		return self::Instance()->acl_check('manage');
	}

	public static function get_id($name) {
		$name = trim($name,'/');
		$pcs = explode('/',$name);
		$id = -1;
		foreach($pcs as $v) {
			if($v==='') continue; //ignore emtpy paths
			$id = DB::GetOne('SELECT id FROM utils_commondata_tree WHERE parent_id=%d AND akey=%s',array($id,$v));
			if($id===false)
				return false;
		}
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
			if($id2===false) {
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
	public static function set_value($name,$value,$overwrite=true){
		$id = self::get_id($name);
		if ($id===false){
			$id = self::new_id($name);
			if($id===false) return false;
		} else {
			if (!$overwrite) return false;
		}
		DB::Execute('UPDATE utils_commondata_tree SET value=%s WHERE id=%d',array($value,$id));
		return true;
	}

	/**
	 * Gets node value.
	 * 
	 * @param string array name
	 * @return mixed false on invalid name
	 */
	public static function get_value($name){
		$val = false;
		$id = self::get_id($name);
		if($id===false) return false;
		return DB::GetOne('SELECT value FROM utils_commondata_tree WHERE id=%d',array($id));
	}
	
	/**
	 * Gets nodes by keys.
	 * 
	 * @param string array name
	 * @return mixed false on invalid name
	 */
	public static function get_nodes($root, array $names){
		$val = false;
		$id = self::get_id($root);
		if($id===false) return false;
		return DB::GetAssoc('SELECT id,value FROM utils_commondata_tree WHERE parent_id=%d AND (akey=\''.implode($names,'\' OR akey=\'').'\')',array($id));
	}
	
	/**
	 * Creates new array for common use.
	 * 
	 * @param string array name
	 * @param array initialization value
	 * @param bool whether method should overwrite if array already exists, otherwise the data will be appended
	 */
	public static function new_array($name,$array,$overwrite=false){
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
		foreach($array as $k=>$v)
			DB::Execute('INSERT INTO utils_commondata_tree (parent_id, akey, value) VALUES (%d,%s,%s)',array($id,$k,$v));
		return true;
	}

	/**
	 * Extends common data array.
	 * 
	 * @param string array name
	 * @param array values to insert
	 * @param bool whether method should overwrite data if array key already exists, otherwise the data will be preserved
	 */
	public static function extend_array($name,$array,$overwrite=false){
		$id = self::get_id($name);
		if ($id===false){
			self::new_array($name,$array);
			return;
		}
		$in_db = DB::GetCol('SELECT akey FROM utils_commondata_tree WHERE parent_id=%s',array($id));
		foreach($array as $k=>$v){
			if (in_array($k,$in_db)) {
				if (!$overwrite) continue;
				DB::Execute('UPDATE utils_commondata_tree SET value=%s WHERE akey=%s AND parent_id=%d',array($v,$k,$id));
			} else {
				DB::Execute('INSERT INTO utils_commondata_tree (parent_id, akey, value) VALUES (%d,%s,%s)',array($id,$k,$v));
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
		$id = self::get_id($name);
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
	public static function get_array($name, $order_by_key=false){
		$id = self::get_id($name);
		if($id===false) return false;
		if($order_by_key)
			$order_by = 'akey';
		else
			$order_by = 'value';
		return DB::GetAssoc('SELECT akey, value FROM utils_commondata_tree WHERE parent_id=%d ORDER BY '.$order_by,array($id));
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
		$id = self::get_id($parent.'/'.$old);
		if($id===false) return false;
		DB::Execute('UPDATE utils_commondata_tree SET akey=%s WHERE id=%d',array($new,$id));
		return true;
	}
}

$GLOBALS['HTML_QUICKFORM_ELEMENT_TYPES']['commondata'] = array('modules/Utils/CommonData/qf.php','HTML_QuickForm_commondata');
$GLOBALS['HTML_QUICKFORM_ELEMENT_TYPES']['commondata_group'] = array('modules/Utils/CommonData/qf_group.php','HTML_QuickForm_commondata_group');

?>