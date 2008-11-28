<?php
/**
 * 
 * @author jtylek@telaxus.com
 * @copyright jtylek@telaxus.com
 * @license SPL
 * @version 0.1
 * @package applets-birthdays
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_BirthdaysCommon extends ModuleCommon {
	public static function applet_caption() {
		return "Birthdays";
	}

	public static function applet_info() {
		return "Displays upcoming Birthdays of your favorite contacts";
	}

	// Returns array of parameters back to the applet as $conf
	public static function applet_settings() {
		return array(
			array(
				'name'=>'no_of_days','label'=>'Number of days','type'=>'text','default'=>'30',
				'rule'=>array(
						array('message'=>'Field must be numeric', 'type'=>'numeric'),
						array('message'=>'Field required', 'type'=>'required')
							)
			),
			array(
				'name'=>'title','label'=>'Title','type'=>'text','default'=>'Upcoming Birthdays',
				'rule'=>array(
						array('message'=>'Field required', 'type'=>'required')
							)
				)
			);
	} // Eof applet settings

}

?>