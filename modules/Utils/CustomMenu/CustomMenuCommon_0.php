<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_CustomMenuCommon {
	public static function add_entry($id,$path,$module,$function,$arguments) {
		return DB::Execute('INSERT INTO utils_custommenu_entry(id,path,module,function,arguments) VALUES(%s, %s, %s, %s, %s)',array(md5($id),$path,$module,$function,serialize($arguments)));
	}

	public static function del_entry($path) {
		return DB::Execute('DELETE FROM utils_custommenu_entry WHERE path=%s',$path);
	}

	public static function del_entries_by_id($id) {
		return DB::Execute('DELETE FROM utils_custommenu_entry WHERE id=%s',md5($id));
	}
	
	public static function menu() {
		$ret = DB::Execute('SELECT path,module,function,arguments FROM utils_custommenu_entry');
		$menu = array();
		while($row=$ret->FetchRow()) {
			$path = explode('/',$row['path']);
			//print_r($path);
			$curr = & $menu;
			for($i=0; $i<count($path)-1; $i++) {
				if(!isset($curr[$path[$i]])) {
					$curr[$path[$i]] = array('__submenu__'=>1);
				}
				//if(is_array($curr[$path[$i]])) {
					$curr = &$curr[$path[$i]];
				//} else {
					//pass 
				//}
			}
			$curr[$path[count($path)-1]] = array('__module__'=>$row['module'],'__function__'=>$row['function'],'__function_arguments__'=>unserialize($row['arguments'])); 
		}
		//print_r($menu);
		return $menu;
	}
	
}

?>