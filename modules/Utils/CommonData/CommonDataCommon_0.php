<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-utils
 * @subpackage common-data
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_CommonDataCommon implements Base_AdminModuleCommonInterface {

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
		return Base_AclCommon::i_am_sa();
	}
	
	/**
	 * Creates new array for common use.
	 * 
	 * @param string array name
	 * @param array initialization value
	 * @param bool whether method should overwrite if array already exists, otherwise the data will be appended
	 */
	public static function new_array($name,$array,$overwrite=false){
		$id = DB::GetOne('SELECT id FROM utils_commondata_arrays WHERE name=%s',$name);
		if ($id){
			if (!$overwrite) {
				self::extend_array($name,$array);
				return;
			} else {
				self::remove_array($name);
			}
		}
		DB::Execute('INSERT INTO utils_commondata_arrays (name) VALUES (%s)',$name);
		$id = DB::GetOne('SELECT id FROM utils_commondata_arrays WHERE name=%s',$name);
		foreach($array as $k=>$v){
			DB::Execute('INSERT INTO utils_commondata_data (array_id, akey, value) VALUES (%d,%s,%s)',array($id,$k,$v));
		}
	}

	/**
	 * Extends common data array.
	 * 
	 * @param string array name
	 * @param array values to insert
	 * @param bool whether method should overwrite data if array key already exists, otherwise the data will be preserved
	 */
	public static function extend_array($name,$array,$overwrite=false){
		$id = DB::GetOne('SELECT id FROM utils_commondata_arrays WHERE name=%s',$name);
		if (!$id){
			self::new_array($name,$array);
			return;
		}
		foreach($array as $k=>$v){
			$akey = DB::GetOne('SELECT akey FROM utils_commondata_data WHERE akey=%s AND array_id=%s',array($k,$id));
			if ($akey) {
				if (!$overwrite) continue;
				DB::Execute('UPDATE utils_commondata_data SET value=%s WHERE akey=%s',array($v,$k));
			} else {
				DB::Execute('INSERT INTO utils_commondata_data (array_id, akey, value) VALUES (%d,%s,%s)',array($id,$k,$v));
			}
		}
	}
	
	/**
	 * Removes common data array.
	 * 
	 * @param string array name
	 * @return true on success, false otherwise
	 */
	public static function remove_array($name){
		$id = DB::GetOne('SELECT id FROM utils_commondata_arrays WHERE name=%s',$name);
		if (!$id) return false;
		DB::Execute('DELETE FROM utils_commondata_data WHERE array_id=%d',$id);
		DB::Execute('DELETE FROM utils_commondata_arrays WHERE id=%d',$id);
		//$this->unset_module_variable($name);
		return true;
	}
	
	/**
	 * Removes entry from common data array.
	 * 
	 * @param string array name
	 * @param string array key
	 * @return true on success, false otherwise
	 */
	public static function remove_field($array,$key){
		$id = DB::GetOne('SELECT id FROM utils_commondata_arrays WHERE name=%s',$array);
		if (!$id) return false;
		DB::Execute('DELETE FROM utils_commondata_data WHERE array_id=%s AND akey=%s',array($id,$key));
	} 
	
	/**
	 * Returns common data array.
	 * 
	 * @param string array name
	 * @return mixed returns an array if such array exists, false otherwise 
	 */
	public static function get_array($name){
		$array = array();
		$id = DB::GetOne('SELECT id FROM utils_commondata_arrays WHERE name=%s',$name);
		if (!$id) return false;
		$ret = DB::Execute('SELECT akey, value FROM utils_commondata_data WHERE array_id=%d',$id);
		while ($row=$ret->FetchRow()){
			$array[$row['akey']] = $row['value'];
		}
		return $array;
	}
}

?>