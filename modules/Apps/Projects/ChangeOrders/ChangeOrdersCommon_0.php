<?php
/**
 * Projects Manager
 * @author jtylek@telaxus.com
 * @copyright jtylek@telaxus.com
 * @license SPL
 * @version 0.1
 * @package apps-projects
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_Projects_ChangeOrdersCommon extends ModuleCommon {
    public static $paste_or_new = 'new';
    

public static function changeorder_callback($v, $i) {
		return Utils_RecordBrowserCommon::create_linked_label('changeorders', 'co_number', $i);
	}

// display project callback
public static function proj_name_callback($v, $i) {
		return Utils_RecordBrowserCommon::create_linked_label('projects', 'project_name', $i);
	}
		
    public static function menu() {
		return array('Projects'=>array('__submenu__'=>1,'Change Orders'=>array()));
	}
    
    public static function caption() {
		return 'Change Orders';
	}
}

?>