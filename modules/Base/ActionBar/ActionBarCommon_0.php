<?php
/**
 * ActionBar
 *
 * This class provides action bar component.
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage actionbar
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_ActionBarCommon extends ModuleCommon {
	private static $icons = array();

	public static $available_icons = array(
			'home'		=> 0,
			'back'		=> 1,
			'report'	=> 2,
			'history'	=> 3,
			'all'		=> 4,
			'favorites'	=> 5,
			'calendar'	=> 6,
			'search'	=> 7,
			'folder'	=> 8,
			'edit'		=> 9,
			'view'		=> 10,
			'add'		=> 11,
			'delete'	=> 12,
			'save'		=> 13,
			'print'		=> 14,
			'clone'		=> 15,
			'settings'	=> 16,
			'scan'		=> 17,
			'filter'	=> 18,
			'retry'		=> 19,
			'send'		=> 20,
			'new-mail'	=> 21,
			'attach'	=> 22,
			'reply'		=> 23,
			'forward'	=> 24);

	public static function user_settings(){
		return array(__('Misc')=>array(
			array('name'=>'display','label'=>__('Action bar displays'),'type'=>'select','values'=>array('icons only'=>__('Icons only'),'text only'=>__('Text only'),'both'=>__('Both')),'default'=>'both','reload'=>true)
			));
	}

	public static function add($type, $text, $action, $description=null, $position = 0) {
//		if(!array_key_exists($type,self::$available_icons)) trigger_error('Invalid action '.$type,E_USER_ERROR);
		foreach (self::$icons as $k=>$v) {
			if ($v['icon']==$type && $v['label']==$text) unset(self::$icons[$k]);
		}
		self::$icons[] = array('icon'=>$type,'label'=>$text,'action'=>$action,'description'=>$description,'position'=>$position);
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
