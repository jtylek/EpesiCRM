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
    
    public static function get_project($id) {
		return Utils_RecordBrowserCommon::get_record('projects', $id);
    }
    
    public static function display_proj_name($v, $i) {
		return Utils_RecordBrowserCommon::create_linked_label('projects', 'Project Name', $i);
	}

	public static function display_projmanager($v, $i) {
		return;
		//return Utils_RecordBrowserCommon::create_linked_label('contacts', 'Last Name', $i);
	}

	public static function display_estimator($v, $i) {
		return;
		//return Utils_RecordBrowserCommon::create_linked_label('contacts', 'Last Name', $i);
	}
	
	public static function qfield_projmanager(&$form, $field, $label, $mode, $default) {
				
		if ($mode=='add' || $mode=='edit') {
			$emp = array();
			$ret = CRM_ContactsCommon::get_contacts(array('company_name'=>array(CRM_ContactsCommon::get_main_company())));
			foreach($ret as $c_id=>$data)
				$emp[$c_id] = $data['last_name'].' '.$data['first_name'];
		
			/*
			$cus = array();
			$ret = CRM_ContactsCommon::get_contacts(array('!company_name'=>array(CRM_ContactsCommon::get_main_company())));
			foreach($ret as $c_id=>$data)
			$cus[$c_id] = $data['last_name'].' '.$data['first_name'];
			*/
			
			$form->addElement('select', $field, $label, $emp);
			$form->setDefaults(array($field=>$default));
		} else {
			$projman = CRM_ContactsCommon::get_contact('1');
			//return $projman['last_name']." ".$projman['first_name'];
			$form->addElement('select', $field, $label, $projman);
		}
	} // end of function

	public static function qfield_estimator(&$form, $field, $label, $mode, $default) {
				
		if ($mode=='add' || $mode=='edit') {
			$emp = array();
			$ret = CRM_ContactsCommon::get_contacts(array('company_name'=>array(CRM_ContactsCommon::get_main_company())));
			foreach($ret as $c_id=>$data)
				$emp[$c_id] = $data['last_name'].' '.$data['first_name'];
					
			$form->addElement('select', $field, $label, $emp);
			$form->setDefaults(array($field=>$default));
		} else {
			$estimator = CRM_ContactsCommon::get_contact('1');
			//return $projman['last_name']." ".$projman['first_name'];
			$form->addElement('select', $field, $label, $estimator);
		}
	}
        
    public static function menu() {
		return array('Projects'=>array('__submenu__'=>1,'Change Orders'=>array()));
	}
    
    public static function caption() {
		return 'Change Orders';
	}
}

?>