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
    

// create_linked_label ('table','field_name',$i)
// $i - id of the record of the current table (changeorders)
// field_name can be either 'Field Name' or 'filed_name' - it is evaluated
public static function changeorder_callback($v, $i) {
		return Utils_RecordBrowserCommon::create_linked_label('changeorders', 'CO Number', $i);
	}

// display project callback
// $record - whole record from master table as array
// $i - id of the record
// $ nolink - paramater - see example
// $records[$desc['id']] - id of the record in linked table
// $records[$desc['id']] = $record['project_name']
// $records[$desc['id']] - more flexible, substitutes field name
public static function proj_name_callback($record, $i, $nolink, $desc) {
return Utils_RecordBrowserCommon::create_linked_label('projects', 'Project Name', $record[$desc['id']], $nolink);
}

/* usage example of $nolink

public static function contact_format_no_company($record, $nolink){
$ret = '';
if (!$nolink) $ret .= '<a '.Utils_RecordBrowserCommon::create_record_href('contact', $record['id']).'>';
$ret .= $record['last_name'].(($record['first_name']!=='')?' '.$record['first_name']:'');
if (!$nolink) $ret .= '</a>';
return $ret;
}

*/
		
    public static function menu() {
		return array('Projects'=>array('__submenu__'=>1,'Change Orders'=>array()));
	}
    
    public static function caption() {
		return 'Change Orders';
	}
}

?>