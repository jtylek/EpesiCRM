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
    
    public static function display_proj_name($v, $i) {
		return Utils_RecordBrowserCommon::create_linked_label('projects', 'Project Name', $i);
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
    
    /* Not needed if not processing before writing
	public static function submit_project($values, $mode) {
		if (isset($values['create_project'])) {
			$proj_id = Utils_RecordBrowserCommon::new_record('projects',
				array(	'company_name'=>$values['first_name'].' '.$values['last_name'],
						'address_1'=>$values['address_1'],
						'address_2'=>$values['address_2'],
						'country'=>$values['country'],
						'city'=>$values['city'],
						'zone'=>isset($values['zone'])?$values['zone']:'',
						'postal_code'=>$values['postal_code'],
						'phone'=>$values['phone'],
						'fax'=>$values['fax'],
						'web_address'=>$values['web_address'])
			);
			$values['project_name'] = array($proj_id);
		}
	}
	*/
    
    public static function menu() {
		return array('Projects'=>array('__submenu__'=>1,'Projects'=>array()));
	}
    
    public static function caption() {
		return 'Projects';
	}
	public function admin_caption() {
		return 'Projects';
	}
}

?>