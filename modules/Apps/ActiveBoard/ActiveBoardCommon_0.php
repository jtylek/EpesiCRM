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
		if(Acl::is_user())
			return array('Active board'=>array());
		return array();
	}
	
	public static function body_access() {
		return Acl::is_user();
	}
	
	public static function delete($module) {
		$module = str_replace('/','_',$module);
		$ret = DB::GetAll('SELECT id FROM apps_activeboard_applets WHERE module_name=%s',array($module));
		foreach($ret as $row)
			DB::Execute('DELETE FROM apps_activeboard_settings WHERE applet_id=%d',array($row['id']));
		DB::Execute('DELETE FROM apps_activeboard_applets WHERE module_name=%s',array($module));
	}

}

?>