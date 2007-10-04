<?php
/**
 * Flash clock
 *
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package applets-clock
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_ClockCommon extends ModuleCommon {
	public static function applet_caption() {
		return "Clock";
	}

	public static function applet_info() {
		return "Analog JS clock - only Firefox and Safari!"; //here can be associative array
	}

	public static function applet_settings() {
		return array(
			array('name'=>'skin','label'=>'Clock skin','type'=>'select','default'=>'swissRail','rule'=>array(array('message'=>'Field required', 'type'=>'required')),'values'=>array('swissRail'=>'swissRail','chunkySwiss'=>'chunkySwiss','fancy'=>'fancy','machine'=>'machine','classic'=>'classic','modern'=>'modern','simple'=>'simple'))
			);
	}	
}

?>