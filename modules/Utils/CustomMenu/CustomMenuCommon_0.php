<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-utils
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_CustomMenuCommon {
	public static function delete($id) {
		$p = md5($id);
		return DB::Execute('DELETE FROM utils_custommenu_entry WHERE page_id=%s',$p) && DB::Execute('DELETE FROM utils_custommenu_page WHERE id=%s',$p);
	}

	public static function menu() {
		$ret = DB::Execute('SELECT path,module,function,arguments FROM utils_custommenu_page LEFT JOIN utils_custommenu_entry ON page_id=id');
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