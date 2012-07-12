<?php
/**
 * CRM County class.
 *
 * This class provides aditional fields for Contact and Comapny RecordSets.
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage contacts
 */
defined("_VALID_ACCESS") || die();

class CRM_Contacts_CountyInstall extends ModuleInstall {
	public function install() {
		$fields = array('name' => _M('County'),	'type'=>'commondata', 'required'=>false, 'param'=>array('Countries','Country','Zone'), 'extra'=>false, 'QFfield_callback'=>array('CRM_Contacts_CountyCommon', 'QFfield_county'), 'position'=>'Zone');
		Utils_RecordBrowserCommon::new_record_field('company', $fields);
		$fields = array('name' => _M('County'),	'type'=>'commondata', 'required'=>false, 'param'=>array('Countries','Country','Zone'), 'extra'=>false, 'QFfield_callback'=>array('CRM_Contacts_CountyCommon', 'QFfield_county'), 'position'=>'Zone');
		Utils_RecordBrowserCommon::new_record_field('contact', $fields);
		$fields = array('name' => _M('Home County'),	'type'=>'commondata', 'required'=>false, 'param'=>array('Countries','Home Country','Home Zone'), 'extra'=>false, 'QFfield_callback'=>array('CRM_Contacts_CountyCommon', 'QFfield_county'), 'position'=>'Home Zone');
		Utils_RecordBrowserCommon::new_record_field('contact', $fields);
		$hc_pos= DB::GetOne('SELECT position FROM contact_field WHERE field="Home City"');
		$bd_pos= DB::GetOne('SELECT position FROM contact_field WHERE field="Birth Date"');
		if ($hc_pos+1<$bd_pos) {
			DB::Execute('UPDATE contact_field SET position = position+1 WHERE position>%d AND position<%d', array($hc_pos, $bd_pos));
			DB::Execute('UPDATE contact_field SET position = %d WHERE field="Birth Date"', array($hc_pos));
		}
		return true;
	}

	public function uninstall() {
		Utils_RecordBrowserCommon::delete_record_field('company', 'County');
		Utils_RecordBrowserCommon::delete_record_field('contact', 'County');
		Utils_RecordBrowserCommon::delete_record_field('contact', 'Home County');
		return true;
	}

	public function requires($v) {
		return array(
			array('name'=>'CRM/Contacts', 'version'=>0)
		);
	}

	public static function info() {
		return array('Author'=>'<a href="mailto:abisaga@telaxus.com">Arkadiusz Bisaga</a> (<a href="http://www.telaxus.com">Telaxus LLC</a>)', 'License'=>'TL', 'Description'=>'Module enabling "County" fields for contacts and companies.');
	}

	public static function simple_setup() {
        return array('package'=>__('CRM'), 'option'=>__('County'));
	}

	public function version() {
		return array('1.0');
	}
}

?>
