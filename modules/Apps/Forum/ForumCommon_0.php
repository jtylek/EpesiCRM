<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_ForumCommon {
	public static function menu(){
		$ret = DB::Execute('SELECT id, name FROM apps_forum_board ORDER BY name');
		$boards = array();
		while($row = $ret->FetchRow())
			$boards[$row['name']] = array('action'=>'view_board','board'=>$row['id']);
		return array('Forum'=>array_merge(array('__submenu__'=>1,'Forum Boards'=>array('__weight__'=>-10,'action'=>'__NONE__'),'__split__'=>1),$boards));
	}

}

?>