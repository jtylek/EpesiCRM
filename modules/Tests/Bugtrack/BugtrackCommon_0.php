<?php
/**
 * Software Development - Bug Tracking
 *
 * @author Janusz Tylek and Claude Code AI
 * @version 2.0
 * @license MIT
 * @package epesi-tests
 * @subpackage bugtrack
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_BugtrackCommon extends ModuleCommon {
	// AdminLTE-only: Base_BootstrapIcons::resolve() looks this up for this
	// module's icon (sidebar menu, ActionBar launcher, admin panels, module
	// indicator, etc.) instead of a central map - see
	// modules/Base/Theme/bootstrap_icons.php.
	public static function bootstrap_icon() { return 'bi-bug-fill'; }

/*
    public static function get_bugtrack($id) {
		return Utils_RecordBrowserCommon::get_record('bugtrack', $id);
    }
*/
    
    public static function display_bugtrack($v) {
		return Utils_RecordBrowserCommon::create_linked_label('bugtrack', 'Project Name', $v['id']);
	}
	
    public static function menu() {
		return array(_M('Projects')=>array('__submenu__'=>1,_M('Bugtrack')=>array()));
	}
    
    public static function caption() {
		return __('Bugtrack');
	}

	public static function search_format($id) {
		$row = Utils_RecordBrowserCommon::get_records('bugtrack',array('id'=>$id));
		if(!$row) return false;
		$row = array_pop($row);
		return Utils_RecordBrowserCommon::record_link_open_tag('bugtrack', $row['id']).__( 'Bug (attachment) #%d, %s', array($row['id'], $row['project_name'])).Utils_RecordBrowserCommon::record_link_close_tag();
	}

/*
	public static function admin_caption() {
		return __('Bugtrack');
	}
*/
}

?>