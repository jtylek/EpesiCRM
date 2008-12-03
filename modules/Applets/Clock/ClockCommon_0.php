<?php
/**
 * Flash clock
 * (clock taken from http://www.kirupa.com/developer/actionscript/clock.htm)
 *
 * @author pbukowski@telaxus.com
 * @copyright 2008 Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-applets
 * @subpackage clock
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_ClockCommon extends ModuleCommon {
	public static function applet_caption() {
		return "Clock";
	}

	public static function applet_info() {
		return "Analog JS clock"; //here can be associative array
	}

	public static function applet_settings() {
		$browser = stripos($_SERVER['HTTP_USER_AGENT'],'msie');
		if($browser!==false)
			return array(
				array('name'=>'skin','label'=>'Clock configurable only on non-IE browsers only.','type'=>'static','values'=>'')
			);
		else
			return array(
				array('name'=>'skin','label'=>'Clock skin','type'=>'select','default'=>'swissRail','rule'=>array(array('message'=>'Field required', 'type'=>'required')),'values'=>array('swissRail'=>'swissRail','chunkySwiss'=>'chunkySwiss','chunkySwissOnBlack'=>'chunkySwissOnBlack','fancy'=>'fancy','machine'=>'machine','classic'=>'classic','modern'=>'modern','simple'=>'simple','securephp'=>'securephp','Tes2'=>'Tes2','Lev'=>'Lev','Sand'=>'Sand','Sun'=>'Sun','Tor'=>'Tor','Babosa'=>'Babosa','Tumb'=>'Tumb','Stone'=>'Stone','Disc'=>'Disc','flash'=>'flash'))
			);
	}	
}

?>