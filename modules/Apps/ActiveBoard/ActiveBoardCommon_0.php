<?php
/** 
 * Something like igoogle
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @license SPL
 * @version 0.1
 * @package apps-activeboard
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_ActiveBoardCommon extends ModuleCommon {
	public static function tool_menu() {
		return array('Active board'=>array());
	}
	
	public static function user_settings(){
		$ret = array();
		foreach(ModuleManager::$modules as $name=>$obj) {
			if(method_exists($obj['name'].'Common', 'applet_caption')) {
				$ret[] = array('name'=>$obj['name'], 'type'=>'checkbox', 'label'=>call_user_func(array($obj['name'].'Common', 'applet_caption')),'default'=>false);
			}
		}
		if($ret)
			return array('Active board applets'=>$ret);
		return array();
	}
}

?>