<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_CommonDataCommon implements Base_AdminModuleCommonInterface {

	public static function admin_caption(){
		return "Common data";
	}

	public static function admin_access(){
		return Base_AclCommon::i_am_sa();
	}
	
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
	
	public static function remove_array($name){
		$id = DB::GetOne('SELECT id FROM utils_commondata_arrays WHERE name=%s',$name);
		if (!$id) return false;
		DB::Execute('DELETE FROM utils_commondata_data WHERE array_id=%d',$id);
		DB::Execute('DELETE FROM utils_commondata_arrays WHERE id=%d',$id);
		//$this->unset_module_variable($name);
		return true;
	}
	
	public static function remove_field($arg,$key=false){
		if (is_array($arg)) {
			$array=$arg[0];
			$key=$arg[1];
		} else {
			$array=$arg;
		}
		$id = DB::GetOne('SELECT id FROM utils_commondata_arrays WHERE name=%s',$array);
		if (!$id) return false;
		DB::Execute('DELETE FROM utils_commondata_data WHERE array_id=%s AND akey=%s',array($id,$key));
	} 
	
	public static function get_array($name){
		$array = array();
		$id = DB::GetOne('SELECT id FROM utils_commondata_arrays WHERE name=%s',$name);
		if (!$id) return null;
		$ret = DB::Execute('SELECT akey, value FROM utils_commondata_data WHERE array_id=%d',$id);
		while ($row=$ret->FetchRow()){
			$array[$row['akey']] = $row['value'];
		}
		return $array;
	}
}

?>