<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Calendar_Utils_Dropdown extends Module {
	public static $id = 0;
	private $_id;
	
	private $pre_values = array('count'=>0);
	private $current = 'empty';
	private $values = array('count'=>0);
	
	public function construct() {
		$this->_id = CRM_Calendar_Utils_Dropdown::$id;
		CRM_Calendar_Utils_Dropdown::$id++;
	}
	
	// SETTINGS ------------------------------------
	public function set_pre_values($list, $cols = 1) {
		$this->pre_values['list'] = $list;
		$this->pre_values['cols'] = $cols;
		$this->pre_values['count'] = count($list);
	}
	public function set_current($list, $cols = 1) {
		$this->current = $list;
	}
	public function set_values($list, $cols = 1) {
		$this->values['list'] = $list;
		$this->values['cols'] = $cols;
		$this->values['count'] = count($list);
	}
	
	// DISPLAY ------------------------------------
	public function toHtml() {
		load_js('modules/CRM/Calendar/Utils/Dropdown/js/main.js');
		$theme = & $this->init_module('Base/Theme');
		$theme->assign('id', $this->_id);
		$theme->assign('pre_values', $this->pre_values);
		$theme->assign('current', $this->current);
		$theme->assign('values', $this->values);
		return $this->get_html_of_module($theme,null,'display');
	}
	
	public function body() {
		print $this->toHtml();
	}
}
?>
