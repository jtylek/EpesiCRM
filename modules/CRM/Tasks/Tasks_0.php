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
		$this->pack_module('Utils/Tasks',null,null,array('crm_tasks'));
	}
	
	public function applet($conf,$opts) {
		$opts['go'] = true;
		$this->pack_module('Utils/Tasks',null,'body',array('crm_tasks',false,true,false));
	}

	public function caption() {
		return "Tasks";
	}

}

?>