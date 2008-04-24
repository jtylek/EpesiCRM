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

class CRM_TasksCommon extends ModuleCommon {
	public static function applet_caption() {
		return "Tasks";
	}

	public static function applet_info() {
		return "To do list";
	}

	public static function menu() {
		if(self::Instance()->acl_check('access'))
			return array('CRM'=>array('__submenu__'=>1,'Tasks'=>array()));
		else
			return array();
	}

	public static function get_tasks($crits = array(), $cols = array(), $order = array()) {
		$crits['page_id'] = md5('crm_tasks');
		return Utils_RecordBrowserCommon::get_records('task', $crits, $cols, $order);
	}

	public static function get_task($id) {
		$r = Utils_RecordBrowserCommon::get_record('task', $id);
		if ($r['page_id']!=md5('crm_tasks')) return null;
		else return $r;
	}

	public static function applet_settings() {
		return array(
			array('label'=>'Display tasks marked as','name'=>'term','type'=>'select','values'=>array('s'=>'Short term','l'=>'Long term','b'=>'Both'),'default'=>'s','rule'=>array(array('message'=>'Field required', 'type'=>'required'))),
			array('label'=>'Display closed tasks','name'=>'closed','type'=>'checkbox','default'=>false)
//			array('label'=>'Display closed tasks','name'=>'closed','type'=>'select','values'=>array('No','Yes'),'default'=>'0','rule'=>array(array('message'=>'Field required', 'type'=>'required')))
			);
	}
	
	public static function body_access() {
		return self::Instance()->acl_check('access');
	}
	

}

?>