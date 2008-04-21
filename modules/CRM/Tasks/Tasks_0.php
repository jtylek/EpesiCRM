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
		$opts['title'] = 'Tasks'.($conf['term']=='s'?' - short term':($conf['term']=='l'?' - long term':''));
		$me = CRM_ContactsCommon::get_contact_by_user_id(Acl::get_user());
		if(!$me || !isset($me['id']) || !is_numeric($me['id'])) {
			$l = $this->init_module('Base/Lang');
			printf($l->t('Your user doesn\'t have contact, please assign one'));
			return;
		}
		$this->pack_module('Utils/Tasks',array('crm_tasks',($conf['term']=='s' || $conf['term']=='b'),($conf['term']=='l' || $conf['term']=='b'),(isset($conf['closed']) && $conf['closed'])),'applet');
	}

	public function caption() {
		return "Tasks";
	}

}

?>