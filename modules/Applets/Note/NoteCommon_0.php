<?php
/**
 * @author pbukowski@telaxus.com
 * @copyright 2008 Telaxus LLC
 * @license MIT
 * @version 1.1
 * @package epesi-applets
 * @subpackage note
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_NoteCommon extends ModuleCommon {
	public static function applet_caption() {
		return "Note";
	}

	public static function applet_info() {
		return "Simple note applet"; //here can be associative array
	}
	
//	public static function applet_icon() {
//	}

	public static function applet_settings() {
		return array(
			array('name'=>'title','label'=>'Title','type'=>'text','default'=>'Note','rule'=>array(array('message'=>'Field required', 'type'=>'required'))),
//			array('name'=>'text','type'=>'callback','func'=>array('Applets_NoteCommon','text_elem'),'default'=>'','rule'=>array(array('message'=>'Field required', 'type'=>'required'))),
			array('name'=>'text','type'=>'callback','func'=>array('Applets_NoteCommon','text_elem'),'default'=>'','rule'=>array(array('message'=>'Field required', 'type'=>'required')),'filter'=>array(array('Applets_NoteCommon','filter_text'))),
			array('name'=>'bcolor','label'=>'Background color','type'=>'select','default'=>'nice yellow','rule'=>array(array('message'=>'Field required', 'type'=>'required')), 'values'=>array('nice-yellow' => 'nice yellow', 'blue'=>'blue', 'red'=>'red', 'yellow'=>'yellow', 'green' => 'green', 'white'=>'white', 'gradient' => 'gradient', 'gradient2' => 'gradient2', 'gray' => 'gray', 'dark-blue' => 'dark blue', 'dark-red' => 'dark red', 'dark-yellow' => 'dark yellow', 'dark-green' => 'dark green'))
			);
	}
	
	public static function filter_text($val) {
		return EpesiHTML::parse($val,true);
	}
	
	public static function text_elem($name, $args, & $def_js) {
		$obj = HTML_QuickForm::createElement('fckeditor',$name,'Text to display');
		$obj->setFCKProps('400','300',false);
	//	$def_js .= '$(\''.$this->getAttribute('name').'\').'.$v['name'].'.value = \''.$v['default'].'\';';
		return $obj;
	}
}

?>
