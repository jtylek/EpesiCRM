<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_ActionBarCommon {
	private static $icons = array();

	public static function user_settings(){
		return array('Action bar settings'=>array(
			array('name'=>'display','label'=>'Display','values'=>array('icons only','text only','both'),'default'=>'both')
			));
	}
	
	public static function add_icon($type, $text, $action) {
		if($type!='home' &&
		   $type!='calendar' &&
		   $type!='search' &&
		   $type!='folder' &&
		   $type!='new' &&
		   $type!='edit' &&
		   $type!='view' &&
		   $type!='add' &&
		   $type!='delete' &&
		   $type!='save' &&
		   $type!='settings' &&
		   $type!='print' &&
		   $type!='back') trigger_error('Invalid action '.$type,E_USER_ERROR);

		self::$icons[] = array('icon'=>$type,'label'=>$text,'action_open'=>'<a '.$action.'>','action_close'=>'</a>');
	}
	
	public static function get_icons() {
		return self::$icons;
	}
}

?>