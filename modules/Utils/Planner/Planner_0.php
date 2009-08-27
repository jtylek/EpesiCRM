<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-utils
 * @subpackage planner
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_Planner extends Module {
	private $date=null;
	private $grid = array();
	private $form;
	
	public function construct() {
		$_SESSION['client']['utils_planner'] = array();
		$_SESSION['client']['utils_planner']['resources'] = array();
		$this->form = $this->init_module('Libs/QuickForm');
		$this->form->addElement('hidden', 'grid_selected_frames', '', array('id'=>'grid_selected_frames'));
	}
	
	public function set_resource_availability_check_callback($callback) {
		$_SESSION['client']['utils_planner']['resource_availability_check_callback'] = $callback;
	}
	
	public function set_processing_callback($callback) {
		$_SESSION['client']['utils_planner']['processing_callback'] = $callback;
	}
	
	public function set_regular_grid($start_time='00:00', $end_time='23:59', $grid_size='01:00') {
		foreach (array('start_time','end_time','grid_size') as $v)
			if ($$v!=(string)intval($$v)) {
				$e = explode(':',$$v);
				$$v = $e[0]*60+$e[1];
			}
		$this->grid = array();
		$time = $start_time;
		$last_time = $start_time;
		while ($time!=$end_time) {
			$this->grid[] = $time;
			$time += $grid_size;
			if ($time>$end_time) $time=$end_time;
		}
		$this->grid[] = $end_time;
	}

	public function set_custom_grid($grid) {
		$this->grid = $grid;
	}
	
	public function add_resource($def, $prop=array()) {
		list($type, $name, $label, $param1) = $def;
		if (isset($def[4])) $param2 = $def[4];
		if (isset($def[5])) $param3 = $def[5];
		$_SESSION['client']['utils_planner']['resources'][$name]=array('type'=>$type,'in_use'=>array(),'value'=>false);
		$on_change = 'resource_changed(\''.$name.'\')';
		if (isset($prop['chained'])) $_SESSION['client']['utils_planner']['resources'][$name]['chained'] = $prop['chained'];
		if ($type=='automulti'){
			$el = $this->form->addElement($type, $name, $label, $param1, $param2, $param3);
			$el->on_add_js($on_change);
			$el->on_remove_js($on_change);
			return;
		}
		if ($type=='select'){
			$this->form->addElement($type, $name, $label, $param1, array('id'=>$name, 'onchange'=>$on_change));
			return;
		}
	}

	public function body(){
		if (empty($this->grid)) {
			print('Time grid not defined, aborting');
			return;
		}
		Base_ThemeCommon::install_default_theme('Utils/Planner');
		load_js('modules/Utils/Planner/planner.js');
		eval_js('disableSelection($("Utils_Planner__grid"))');
		eval_js('Event.observe(window,"mouseup",time_grid_mouse_up)');
		$theme = $this->init_module('Base/Theme');

		/* HEADERS */
		$fdow = Utils_PopupCalendarCommon::get_first_day_of_week();
		$headers = array();
		if ($this->date===null) {
			$days_of_week = array(0=>'Sunday', 1=>'Monday', 2=>'Tuesday', 3=>'Wednesday', 4=>'Thursday', 5=>'Friday', 6=>'Saturday');
			while (count($headers)<7) {
				$headers[$fdow] = $days_of_week[$fdow];
				$fdow++;
				if ($fdow>6) $fdow -= 7;
			}
		} else {
			if (!is_numeric($this->date)) $this->date = strtotime($this->date);
			while (date('w',$this->date)!=$fdow) $this->date = strtotime('-1 day', $this->date);
			while (count($headers)<7) {
				$headers[$this->date] = date('Y-m-d, w', $this->date);
				$this->date = strtotime('+1 day', $this->date);
			}
		}
		$theme->assign('headers',$headers);
		
		/* GRID LEGEND */
		$grid_legend = array();
		$grid_attrs = array();
		foreach ($this->grid as $k=>$v) {
			if (!isset($this->grid[$k+1])) break;
			$grid_legend[$v] = Utils_PlannerCommon::format_time($v*60);
			$grid_legend[$v] .= ' - '.Utils_PlannerCommon::format_time($this->grid[$k+1]*60);
			$grid_attrs[$v] = array(); 
			foreach ($headers as $k2=>$v2) $grid_attrs[$v][$k2] = 'class="noconflict unused" id="'.$k2.'__'.$v.'" onmousedown="time_grid_mouse_down('.$v.','.$k2.')" onmousemove="time_grid_mouse_move('.$v.','.$k2.')"';
		}
		/* GRID LEGEND END */
		
		$theme->assign('grid_legend',$grid_legend);
		$theme->assign('grid_attrs',$grid_attrs);
		$theme->assign('time_frames',array('label'=>$this->t('Time frames'), 'html'=>'<div id="Utils_Planner__time_frames" />'));
		$_SESSION['client']['utils_planner']['grid']=array(
			'timetable'=>$this->grid,
			'days'=>$headers,
			);

		$this->form->assign_theme('form', $theme);
		$theme->display();
		Base_ActionBarCommon::add('save','Save',$this->form->get_submit_form_href());
		if ($this->form->validate()) {
			$values = $this->form->exportValues();
			$time_frames = explode(';',$values['grid_selected_frames']);
			unset($values['grid_selected_frames']);
			foreach ($time_frames as $k=>$v) {
				list($day, $s, $e) = explode('::',$v);
				$time_frames[$k] = array('day'=>$day, 'start'=>$s, 'end'=>$e);
			}
			call_user_func($_SESSION['client']['utils_planner']['processing_callback'], $values, $time_frames);
			foreach ($values as $k=>$v) {
				$_SESSION['client']['utils_planner']['resources'][$k]['value'] = $v;
				$_SESSION['client']['utils_planner']['resources'][$k]['in_use'] = array();
				eval_js(Utils_PlannerCommon::utils_planner_resource_changed($k, $v));
			}
		}
	}
}

?>
