<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_GenericBrowserCommon {
	public static function user_settings(){
		return array('Browsing tables'=>array(
			array('name'=>'per_page','label'=>'Records per page','values'=>array(5,10,25,50,100),'default'=>10),
			array('name'=>'actions_position','label'=>'Position of \'Actions\' column','values'=>array('Left','Right'),'default'=>'Left'),
			array('name'=>'adv_search','label'=>'Advanced search by default','bool'=>1,'default'=>0),
			array('name'=>'adv_history','label'=>'Advanced order history','bool'=>1,'default'=>0)
			));
	}
}

?>
