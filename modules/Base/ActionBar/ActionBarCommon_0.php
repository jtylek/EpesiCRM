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
 * @subpackage actionbar
 * @license SPL
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_ActionBarCommon extends ModuleCommon {
	private static $icons = array();
	
	public static $available_icons = array(
			'home'=>0,
			'back'=>1,
			'report'=>2,
			'calendar'=>3,
			'search'=>4,
			'folder'=>5,
			'new'=>6,
			'edit'=>7,
			'view'=>8,
			'add'=>9,
			'delete'=>10,
			'save'=>11,
			'settings'=>12,
			'print'=>13);

	public static function user_settings(){
		return array('Action bar settings'=>array(
			array('name'=>'display','label'=>'Display','type'=>'select','values'=>array('icons only'=>'icons only','text only'=>'text only','both'=>'both'),'default'=>'both','reload'=>true)
			));
	}
	
	public static function add($type, $text, $action) {
		if(!array_key_exists($type,self::$available_icons)) trigger_error('Invalid action '.$type,E_USER_ERROR);

		self::$icons[] = array('icon'=>$type,'label'=>$text,'action_open'=>'<a '.$action.'>','action_close'=>'</a>');
	}
	
	public static function get() {
		return self::$icons;
	}
	
	public static function clean() {
		self::$icons = array();
	}
}
on_exit(array('Base_ActionBarCommon','clean'));
?>