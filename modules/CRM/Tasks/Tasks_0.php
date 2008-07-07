<?php
/**
 * 
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package crm-tasks
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Tasks extends Module {

	public function body() {
		$this->pack_module('Utils/Tasks', 'crm_tasks');
	}
	
	public function applet($conf,$opts) {
		$opts['go'] = true;
		$opts['title'] = 'Tasks'.($conf['related']==0?' - Todo':'').($conf['related']==1?' - Related':'').($conf['term']=='s'?' - short term':($conf['term']=='l'?' - long term':''));
		$me = CRM_ContactsCommon::get_my_record();
		if ($me['id']==-1) {
			CRM_ContactsCommon::no_contact_message();
			return;
		}
		$this->pack_module('Utils/Tasks',array('crm_tasks',($conf['term']=='s' || $conf['term']=='b'),($conf['term']=='l' || $conf['term']=='b'),(isset($conf['closed']) && $conf['closed']),$conf['related']),'applet');
	}

	public function caption() {
		return "Tasks";
	}

}

?>