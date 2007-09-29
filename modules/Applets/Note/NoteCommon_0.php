<?php
/**
 * 
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package applets-note
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_NoteCommon extends ModuleCommon {
	public static function applet_caption() {
		return "Note";
	}

	public static function applet_info() {
		return "Simple note applet"; //here can be associative array
	}
	
	public static function applet_settings() {
		return array(
			array('name'=>'title','label'=>'Title','type'=>'text','default'=>'Note','rule'=>array(array('message'=>'Field required', 'type'=>'required'))),
			array('name'=>'text','label'=>'Text to display','type'=>'fckeditor','default'=>'','rule'=>array(array('message'=>'Field required', 'type'=>'required')))
			);
	}	
}

?>