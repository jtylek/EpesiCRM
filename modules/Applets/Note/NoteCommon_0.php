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
		return __('Note');
	}

	public static function applet_info() {
		return __('Simple note applet'); //here can be associative array
	}
	
//	public static function applet_icon() {
//	}

	public static function applet_settings() {
		return array(
			array('name'=>'title','label'=>__('Title'),'type'=>'text','default'=>__('Note'),'rule'=>array(array('message'=>'Field required', 'type'=>'required'))),
			array('name'=>'text','type'=>'callback','func'=>array('Applets_NoteCommon','text_elem'),'default'=>'','rule'=>array(array('message'=>__('Field required'), 'type'=>'required')),'filter'=>array(array('Applets_NoteCommon','filter_text'))),
			array('name'=>'bcolor','label'=>__('Background color'),'type'=>'select','default'=>'nice yellow','rule'=>array(array('message'=>__('Field required'), 'type'=>'required')), 'values'=>array('nice-yellow' => __('Yellow'), 'blue'=>__('Blue'), 'red'=>__('Red'), 'yellow'=>__('Bleak Yellow'), 'green' => __('Green'), 'white'=>__('White'), 'gradient' => __('Gradient'), 'gradient2' => __('Gradient 2'), 'gray' => __('Gray'), 'dark-blue' => __('Dark blue'), 'dark-red' => __('Dark red'), 'dark-yellow' => __('Dark yellow'), 'dark-green' => __('Dark green')))
			);
	}
	
	public static function filter_text($val) {
		return EpesiHTML::parse($val,true);
	}
	
	public static function text_elem($name, $args, & $def_js) {
		$obj = HTML_QuickForm::createElement('ckeditor',$name,__('Text to display'));
		$obj->setFCKProps('400','300',false);
	//	$def_js .= '$(\''.$this->getAttribute('name').'\').'.$v['name'].'.value = \''.$v['default'].'\';';
		return $obj;
	}
}

?>
