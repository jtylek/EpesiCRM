<?php
/**
 * @author jtylek@telaxus.com
 * @copyright 2008 Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-applets
 * @subpackage birthdays
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_BirthdaysCommon extends ModuleCommon {
	public static function applet_caption() {
		return __('Birthdays');
	}

	public static function applet_info() {
		return __('Displays upcoming Birthdays of your favorite contacts.');
	}

	// Returns array of parameters back to the applet as $conf
	public static function applet_settings() {
		return array(
			array(
				'name'=>'no_of_days','label'=>__('Number of days'),'type'=>'text','default'=>'30',
				'rule'=>array(
						array('message'=>__('Field must be numeric'), 'type'=>'numeric'),
						array('message'=>__('Field required'), 'type'=>'required')
							)
			),
			array(
				'name'=>'title','label'=>__('Title'),'type'=>'text','default'=>__('Upcoming Birthdays'),
				'rule'=>array(
						array('message'=>__('Field required'), 'type'=>'required')
							)
			),
			array(
				'name'=>'cont_type',
				'label'=>__('Contact Type'),
				'type'=>'select','values'=>array('a'=>__('All'),'f'=>__('Favorites')),'default'=>'f'
				)
			);
	} // Eof applet settings

}

?>