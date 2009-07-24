<?php
/**
 * Software Development - Bug Tracking
 *
 * @author Janusz Tylek <jtylek@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-tests
 * @subpackage bugtrack
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_BugtrackCommon extends ModuleCommon {
/*
    public static function get_bugtrack($id) {
		return Utils_RecordBrowserCommon::get_record('bugtrack', $id);
    }
*/
    
    public static function display_bugtrack($v) {
		return Utils_RecordBrowserCommon::create_linked_label('bugtrack', 'Project Name', $v['id']);
	}
	

    public static function access_bugtrack($action, $param){
		$i = self::Instance();
		switch ($action) {
			case 'browse_crits':	if (!$i->acl_check('browse bugtrack')) return false;
									return true;		
			case 'browse':	return true;
			case 'view':	return true;
			case 'add':
			case 'edit':	return $i->acl_check('edit bugtrack');
			case 'delete':	return $i->acl_check('delete bugtrack');
		}
		return false;
    }
    
    public static function menu() {
		return array('Projects'=>array('__submenu__'=>1,'Bugtrack'=>array()));
	}
    
    public static function caption() {
		return 'Bugtrack';
	}

	public static function search_format($id) {
		if(!self::Instance()->acl_check('browse bugtrack')) return false;
		$row = Utils_RecordBrowserCommon::get_records('bugtrack',array('id'=>$id));
		if(!$row) return false;
		$row = array_pop($row);
		return Utils_RecordBrowserCommon::record_link_open_tag('bugtrack', $row['id']).Base_LangCommon::ts('Tests_Bugtrack', 'Bug (attachment) #%d, %s', array($row['id'], $row['project_name'])).Utils_RecordBrowserCommon::record_link_close_tag();
	}

/*
	public function admin_caption() {
		return 'Bugtrack';
	}
*/
}

?>