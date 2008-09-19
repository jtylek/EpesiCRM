<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>, Kuba Slawinski <kslawinski@telaxus.com> and Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @license SPL
 * @package epesi-utils
 * @subpackage generic-browser
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_GenericBrowserCommon extends ModuleCommon {
	public static function user_settings(){
		return array('Browsing tables'=>array(
			array('name'=>'per_page','label'=>'Records per page','type'=>'select','values'=>array(5=>5,10=>10,20=>20,50=>50,100=>100),'default'=>20),
			array('name'=>'actions_position','label'=>'Position of \'Actions\' column','type'=>'radio','values'=>array(0=>'Left',1=>'Right'),'default'=>0),
			array('name'=>'adv_search','label'=>'Advanced search by default','type'=>'bool','default'=>0),
			array('name'=>'adv_history','label'=>'Advanced order history','type'=>'bool','default'=>0),
			array('name'=>'display_no_records_message','label'=>'Hide \'No records found\' message','type'=>'bool','default'=>0)
			));
	}
	
	public static function mobile_table($cols,$data) {
		$th = Base_ThemeCommon::init_smarty();
		$th->assign('cols',$cols);
		$th->assign('data',$data);
		Base_ThemeCommon::display_smarty($th,'Utils/GenericBrowser','mobile');
	
	}
}

?>
