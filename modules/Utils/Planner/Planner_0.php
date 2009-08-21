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
	private $start_time='00:00';
	private $end_time='23:59';
	private $grid_size='01:00';
	private $date=null;
	private $form;
	
	public function construct() {
		$_SESSION['client']['utils_planner'] = array();
		$_SESSION['client']['utils_planner']['resources'] = array();
		$this->form = $this->init_module('Libs/QuickForm');
		$this->form->addElement('hidden', 'grid_selected_frames', '', array('id'=>'grid_selected_frames'));
		Base_ActionBarCommon::add('save','Save',$this->form->get_submit_form_href());
	}
	
	public function set_resource_availability_check_callback($callback) {
		$_SESSION['client']['utils_planner']['resource_availability_check_callback'] = $callback;
	}
	
	public function set_processing_callback($callback) {
		$_SESSION['client']['utils_planner']['processing_callback'] = $callback;
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
		
		/* GRID */
		foreach (array('start_time','end_time','grid_size') as $v)
			if ($this->$v!=(string)intval($this->$v)) {
				$e = explode(':',$this->$v);
				$this->$v = $e[0]*60+$e[1];
			}
		$grid_legend = array();
		$grid = array();
		$grid_attrs = array();
		$time = $this->start_time;
		$last_time = $this->start_time;
		while ($time!=$this->end_time) {
			$grid_legend[$time] = Utils_PlannerCommon::format_time($time*60);
			$grid[] = $time;
			$last_time = $time;
			$time += $this->grid_size;
			if ($time>$this->end_time) $time=$this->end_time;
			$grid_legend[$last_time] .= ' - '.Utils_PlannerCommon::format_time($time*60);
			$grid_attrs[$last_time] = array(); 
			foreach ($headers as $k=>$v) $grid_attrs[$last_time][$k] = 'class="noconflict unused" id="'.$k.'__'.$last_time.'" onmousedown="time_grid_mouse_down('.$last_time.','.$k.')" onmousemove="time_grid_mouse_move('.$last_time.','.$k.')"';
		}
		$grid[] = $this->end_time;
		$theme->assign('grid_legend',$grid_legend);
		$theme->assign('grid_attrs',$grid_attrs);
		$theme->assign('time_frames',array('label'=>$this->t('Time frames'), 'html'=>'<div id="Utils_Planner__time_frames" />'));
		$_SESSION['client']['utils_planner']['grid']=array(
			'timetable'=>$grid,
			'days'=>$headers,
			);

		$this->form->assign_theme('form', $theme);
		$theme->display();
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
