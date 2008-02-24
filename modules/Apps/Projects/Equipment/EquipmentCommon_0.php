<?php
/**
 * Projects Manager - Equipment
 * @author jtylek@telaxus.com
 * @copyright jtylek@telaxus.com
 * @license SPL
 * @version 0.1
 * @package apps-projects
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_Projects_EquipmentCommon extends ModuleCommon {
    public static $paste_or_new = 'new';
    

// create_linked_label ('table','field_name',$i)
// $i - id of the record of the current table (equipment)
// field_name format 'Field Name' (not 'field_name')
public static function equipment_callback($v, $i) {
		return Utils_RecordBrowserCommon::create_linked_label('equipment', 'Lift Eq No', $i);
	}

// display project callback
// $record - whole record from master table as array
// $i - id of the record
// field_name has to be 'Field Name'
// $ nolink - paramater - see example
// $records[$desc['id']] - id of the record in linked table
// $records[$desc['id']] = $record['project_name']
// $records[$desc['id']] - more flexible, substitutes field name
public static function proj_name_callback($record, $i, $nolink, $desc) {
//print( print_r($record, true));
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
		return array('Projects'=>array('__submenu__'=>1,'Equipment'=>array()));
	}
    
    public static function caption() {
		return 'Equipment';
	}
}

?>