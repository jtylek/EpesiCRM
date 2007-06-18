<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>, Kuba Slawinski <kslawinski@telaxus.com> and Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @licence SPL
 * @package epesi-utils
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_GenericBrowserCommon {
	public static function user_settings(){
		return array('Browsing tables'=>array(
			array('name'=>'per_page','label'=>'Records per page','select'=>array(5=>5,10=>10,25=>25,50=>50,100=>100),'default'=>10),
			array('name'=>'actions_position','label'=>'Position of \'Actions\' column','radio'=>array(0=>'Left',1=>'Right'),'default'=>1),
			array('name'=>'adv_search','label'=>'Advanced search by default','bool'=>1,'default'=>0),
			array('name'=>'adv_history','label'=>'Advanced order history','bool'=>1,'default'=>0)
			));
	}
}

?>
