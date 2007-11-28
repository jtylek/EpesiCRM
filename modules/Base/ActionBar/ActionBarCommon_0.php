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
			'history'=>3,
			'favorites'=>4,
			'calendar'=>5,
			'search'=>6,
			'folder'=>7,
//			'new'=>6,
			'edit'=>8,
			'view'=>9,
			'add'=>10,
			'delete'=>11,
			'save'=>12,
			'settings'=>13,
			'print'=>14);

	public static function user_settings(){
		return array('Misc'=>array(
			array('name'=>'display','label'=>'Action bar displays','type'=>'select','values'=>array('icons only'=>'icons only','text only'=>'text only','both'=>'both'),'default'=>'both','reload'=>true)
			));
	}
	
	public static function add($type, $text, $action, $description=null) {
		if(!array_key_exists($type,self::$available_icons)) trigger_error('Invalid action '.$type,E_USER_ERROR);

		self::$icons[] = array('icon'=>$type,'label'=>$text,'action'=>$action,'description'=>$description);
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