<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package apps-forum
 * @licence SPL
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_ForumCommon extends ModuleCommon {
	public static function menu(){
		$ret = DB::Execute('SELECT id, name FROM apps_forum_board ORDER BY name');
		$boards = array();
		while($row = $ret->FetchRow())
			$boards[$row['name']] = array('view_board'=>$row['id']);
		return array('Forum'=>array_merge(array('__submenu__'=>1,'Forum Boards'=>array('__weight__'=>-10,'action'=>null),'__split__'=>1),$boards));
	}

}

?>