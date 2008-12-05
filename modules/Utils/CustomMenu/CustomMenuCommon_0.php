<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-utils
 * @subpackage CustomMenu
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_CustomMenuCommon extends ModuleCommon {

	/**
	 * Delete all menu entries with specified group id.
	 * @param string identifier of the menu entries group
	 */
	public static function delete($id) {
		$p = md5($id);
		return DB::Execute('DELETE FROM utils_custommenu_entry WHERE page_id=%s',$p) && DB::Execute('DELETE FROM utils_custommenu_page WHERE id=%s',$p);
	}

	/**
	 * private function
	 */
	public static function menu() {
		$ret = DB::Execute('SELECT path,module,function,arguments FROM utils_custommenu_page INNER JOIN utils_custommenu_entry ON page_id=id');
		$menu = array();
		while($row=$ret->FetchRow()) {
			$path = explode('/',$row['path']);
			//print_r($path);
			$curr = & $menu;
			for($i=0, $max=count($path)-1; $i<$max; $i++) {
				if(!isset($curr[$path[$i]])) {
					$curr[$path[$i]] = array('__submenu__'=>1);
				}
				//if(is_array($curr[$path[$i]])) {
					$curr = &$curr[$path[$i]];
				//} else {
					//pass 
				//}
			}
			$args = unserialize($row['arguments']);
			if(!is_array($args)) $args = array($args);
			$curr[$path[count($path)-1]] = array('__module__'=>$row['module'],'__function__'=>$row['function'],'__function_arguments__'=>$args); 
		}
		//print_r($menu);
		return $menu;
	}
	
}

?>