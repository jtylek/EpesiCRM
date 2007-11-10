<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Calendar_View_WeekCommon extends ModuleCommon {
	public static function menu() {
		return array('CRM'=>array('__submenu__'=>1,'Calendar'=>array()));
	}
	public static function calendar_user_settings() {
		if(Base_AclCommon::i_am_user()) 
			return array('Week'=>array(
				array('name'=>'defautl_today','label'=>'Start by default with today\'s date', 'type'=>'select', 'values'=>array(0=>'No', 1=>'Yes'), 'default'=>0),
			));
	} 
		
	public static function caption() {
		return 'Calendar';
	}
}
?><?php

?>