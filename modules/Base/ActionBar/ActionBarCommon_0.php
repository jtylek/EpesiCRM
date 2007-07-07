<?php
/**
 * ActionBar
 * 
 * This class provides action bar component.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @package epesi-base-extra
 * @licence SPL
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_ActionBarCommon {
	private static $icons = array();
	
	public static $available_icons = array(
			'home'=>0,
			'calendar'=>1,
			'search'=>2,
			'folder'=>3,
			'new'=>4,
			'edit'=>5,
			'view'=>6,
			'add'=>7,
			'delete'=>8,
			'save'=>9,
			'settings'=>10,
			'print'=>11,
			'back'=>12);

	public static function user_settings(){
		return array('Action bar settings'=>array(
			array('name'=>'display','label'=>'Display','select'=>array('icons only'=>'icons only','text only'=>'text only','both'=>'both'),'default'=>'both','reload'=>true)
			));
	}
	
	public static function add_icon($type, $text, $action) {
		if(!array_key_exists($type,self::$available_icons)) trigger_error('Invalid action '.$type,E_USER_ERROR);

		self::$icons[] = array('icon'=>$type,'label'=>$text,'action_open'=>'<a '.$action.'>','action_close'=>'</a>');
	}
	
	public static function get_icons() {
		return self::$icons;
	}
	
	public static function clean_icons() {
		self::$icons = array();
	}
}
on_exit(array('Base_ActionBarCommon','clean_icons'));
?>