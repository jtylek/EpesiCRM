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

class Apps_ProjectsCommon extends ModuleCommon {
    public static $paste_or_new = 'new';
    
    public static function get_project($id) {
		return Utils_RecordBrowserCommon::get_record('projects', $id);
    }
    
	public static function get_projects($crits=array(),$cols=array()) {
    		return Utils_RecordBrowserCommon::get_records('projects', $crits, $cols);
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

    public static function access_projects($action, $param){
		$i = self::Instance();
		switch ($action) {
			case 'browse':	return $i->acl_check('browse projects');
			case 'view':	static $me;
					if($i->acl_check('view projects')) return true;
					if(!isset($me)) {
						$me = Utils_RecordBrowserCommon::get_records('projects', array('login'=>Acl::get_user()));
						if (is_array($me) && !empty($me)) $me = array_shift($me);
					}
					if ($me) return array('Project Name'=>$me['Project Name']);
					return false;
			case 'edit':	return $i->acl_check('edit projects');
			case 'delete':	return $i->acl_check('delete projects');
		}
		return false;
    }
        
    public static function menu() {
		return array('Projects'=>array('__submenu__'=>1,'Projects'=>array()));
	}

	public function admin_caption() {
		return 'Projects';
	}
    	
// Filter criteria for Company Name
	public static function projects_company_crits(){
//  	   return array(':Fav'=>1);
// gc= GC (General Contractor), res=Residential
		return array('Group'=>array('gc','res'));
   }

// Filter criteria for Epmloyees
// Used in ZSI Estimator, ZSI Project Manager
public static function projects_employees_crits(){
		// Utils_ChainedSelectCommon::create('zsi_estimator',array('company_name'),'modules/CRM/Contacts/update_contact.php');
		// return array();
		return array('company_name'=>array(CRM_ContactsCommon::get_main_company()));
   }

public static function projects_contacts_crits($default){
		Utils_ChainedSelectCommon::create('gc_estimator',array('company_name'),'modules/CRM/Contacts/update_contact.php', array('no_company'=>true), $default);
		return array();
		//return array('Group'=>array('gc','res'));
	}

public static function projects_contacts_pm_crits($default){
		Utils_ChainedSelectCommon::create('gc_project_manager',array('company_name'),'modules/CRM/Contacts/update_contact.php', array('no_company'=>true), $default);
		return array();
	}

public static function projects_contacts_supt_crits($default){
		Utils_ChainedSelectCommon::create('gc_supt.',array('company_name'),'modules/CRM/Contacts/update_contact.php', array('no_company'=>true), $default);
		return array();
	}

}

?>