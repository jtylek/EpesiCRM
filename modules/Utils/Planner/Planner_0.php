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
	private $values = array();
	
	public function clear() {
		unset($_SESSION['client']['utils_planner']);
		$this->unset_module_variable('fixed_date');
	}
	
	public function construct() {
		$_SESSION['client']['utils_planner'] = array();
		$_SESSION['client']['utils_planner']['resources'] = array();
		$this->form = $this->init_module('Libs/QuickForm');
		$this->form->addElement('hidden', 'grid_selected_frames', '', array('id'=>'grid_selected_frames'));
	}
	
	public function set_fixed_week($date) {
		if (!is_numeric($date)) $date = strtotime($date);
		$this->date = $this->get_module_variable('fixed_date', $date);
	}
	
	public function get_form() {
		return $this->form;
	}
	
	public function set_timeframe_availability_check_callback($callback) {
		$_SESSION['client']['utils_planner']['timeframe_availability_check_callback'] = $callback;
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
		if (isset($def[0])) $type = $def[0];
		if (isset($def[1])) $name = $def[1];
		if (isset($def[2])) $label = $def[2];
		if (isset($def[3])) $param1 = $def[3];
		if (isset($def[4])) $param2 = $def[4];
		if (isset($def[5])) $param3 = $def[5];
		$_SESSION['client']['utils_planner']['resources'][$name]=array('type'=>$type,'in_use'=>array(),'value'=>false);
		$on_change = 'resource_changed(\''.$name.'\');';
		if (isset($prop['chained'])) $_SESSION['client']['utils_planner']['resources'][$name]['chained'] = $prop['chained'];
		if ($type=='automulti'){
			$el = $this->form->addElement($type, $name, $label, $param1, $param2, $param3);
			$el->on_add_js($on_change.'update_grid();');
			$el->on_remove_js($on_change);
			return $el;
		}
		if ($type=='checkbox'){
			eval_js('Event.observe("'.$name.'", "change" , function(){'.'resource_changed("'.$name.'","checkbox");'.'});');
			$el = $this->form->addElement($type, $name, $label, null, array('id'=>$name));
			return $el;
		}
		if ($type=='datepicker'){
			eval_js('Event.observe("'.$name.'", "change" , function(){'.'resource_changed("'.$name.'","datepicker");'.'});');
			eval_js('Event.observe("'.$name.'", "native:change" , function(){'.'resource_changed("'.$name.'","datepicker");'.'});');
			$el = $this->form->addElement($type, $name, $label, array('id'=>$name));
			return $el;
		}
		if ($type=='select'){
			eval_js('Event.observe("'.$name.'", "change" , function(){'.$on_change.'$("'.$name.'").className=$("'.$name.'").options[$("'.$name.'").selectedIndex].className;});');
			$el = $this->form->addElement($type, $name, $label, $param1, array('id'=>$name));
			return $el;
		}
		if ($type=='commondata'){
			eval_js('Event.observe("'.$name.'", "change" , function(){'.$on_change.'$("'.$name.'").className=$("'.$name.'").options[$("'.$name.'").selectedIndex].className;});');
			$el = $this->form->addElement($type, $name, $label, $param1, $param2, array('id'=>$name));
			return $el;
		}
		if ($type=='autoselect'){
			$on_change .= '$("'.$name.'").className=$("'.$name.'").options[$("'.$name.'").selectedIndex].className;';
			eval_js('Event.observe("'.$name.'", "change" , function(){'.$on_change.'});');
			$el = $this->form->addElement($type, $name, $label, $param1, $param2, $param3, array('id'=>$name));
			$el->on_hide_js($on_change);
			return $el;
		}
		if ($type=='text'){
			$el = $this->form->addElement($type, $name, $label, array('id'=>$name));
			return $el;
		}
	}
	
	public function set_resource_default($k, $v) {
		$this->form->setDefaults(array($k=>$v));
		$this->values[$k] = $v;
	}
	
	public function set_default_time_frames($day, $start, $end) {
		$mark = false;
		$base_unix_time = strtotime('1970-01-01 00:00');
		$start = (strtotime(Base_RegionalSettingsCommon::time2reg($start,true,true,true,false))-$base_unix_time)/60;
		$end = (strtotime(Base_RegionalSettingsCommon::time2reg($end,true,true,true,false))-$base_unix_time)/60;
		$last = 0;
		foreach ($this->grid as $v) {
			if (!$mark && $v>$start)
				$mark = true;
			if ($mark) {
				eval_js('time_grid_mouse_down('.$last.','.$day.',"used");');
				if ($v>=$end) break;
			}
			$last = $v;
		}
		eval_js('time_grid_mouse_up();');
	}
	
	public function body(){
		if (empty($this->grid)) {
			print('Time grid not defined, aborting');
			return;
		}
		load_js('modules/Utils/Planner/planner.js');
		eval_js('disableSelection($("Utils_Planner__grid"))');
		eval_js('Event.observe(window,"mouseup",time_grid_mouse_up)');
		$theme = $this->init_module('Base/Theme');

		/* HEADERS */
		$fdow = Utils_PopupCalendarCommon::get_first_day_of_week();
		$headers = array();
		$select_all = array();
		if ($this->date===null) {
			$days_of_week = array(0=>__('Sunday'), 1=>__('Monday'), 2=>__('Tuesday'), 3=>__('Wednesday'), 4=>__('Thursday'), 5=>__('Friday'), 6=>__('Saturday'));
			while (count($headers)<7) {
				$headers[$fdow] = $days_of_week[$fdow];
				$fdow++;
				if ($fdow>6) $fdow -= 7;
			}
		} else {
//			while (date('w',$this->date)!=$fdow) $this->date = strtotime('-1 day', $this->date);
			$_SESSION['client']['utils_planner']['date'] = $this->date;
			$days_of_week = array(0=>__('Sun'), 1=>__('Mon'), 2=>__('Tue'), 3=>__('Wed'), 4=>__('Thu'), 5=>__('Fri'), 6=>__('Sat'));
			$curr = $this->date;
			while (count($headers)<7) {
				$headers[$curr] = Base_RegionalSettingsCommon::time2reg($curr, false, true, false).' '.$days_of_week[date('w',$curr)];
				$curr = strtotime('+1 day', $curr);
			}
		}
		$theme->assign('headers',$headers);

		foreach ($headers as $k1=>$v1)
			$select_all[$k1] = '';
		/* GRID LEGEND */
		$grid_legend = array();
		$grid_attrs = array();
		foreach ($this->grid as $k=>$v) {
			if (!isset($this->grid[$k+1])) break;
			foreach ($headers as $k1=>$v1)
				$select_all[$k1] .= ($select_all[$k1]?'time_grid_mouse_move':'time_grid_mouse_down').'('.$v.','.$k1.');';
			$grid_legend[$v] = Utils_PlannerCommon::format_time($v*60);
			$grid_legend[$v] .= ' - '.Utils_PlannerCommon::format_time($this->grid[$k+1]*60);
			$grid_attrs[$v] = array(); 
			foreach ($headers as $k2=>$v2) $grid_attrs[$v][$k2] = 'class="noconflict unused" id="'.$k2.'__'.$v.'" onmousedown="time_grid_mouse_down('.$v.','.$k2.')" onmousemove="if(typeof(time_grid_mouse_move)!=\'undefined\')time_grid_mouse_move('.$v.','.$k2.')"';
		}
		foreach ($headers as $k1=>$v1)
			$select_all[$k1] .= 'time_grid_mouse_up();';
		/* GRID LEGEND END */
		
		$theme->assign('select_all',$select_all);
		$theme->assign('select_all_label',__('Select all'));

		$theme->assign('grid_legend',$grid_legend);
		$theme->assign('grid_attrs',$grid_attrs);
		$theme->assign('time_frames',array('label'=>__('Time frames'), 'html'=>'<div id="Utils_Planner__time_frames" />'));
		$_SESSION['client']['utils_planner']['grid']=array(
			'timetable'=>$this->grid,
			'days'=>$headers,
			);
		if ($this->date!==null) {
			$this->form->addElement('submit', 'next_day', __('Next day'), array('onclick'=>'$("planner_navigation").value="next_day";'));
			$this->form->addElement('submit', 'prev_day', __('Previous day'), array('onclick'=>'$("planner_navigation").value="prev_day";'));
			$this->form->addElement('submit', 'next_week', __('Next week'), array('onclick'=>'$("planner_navigation").value="next_week";'));
			$this->form->addElement('submit', 'prev_week', __('Previous week'), array('onclick'=>'$("planner_navigation").value="prev_week";'));
			$this->form->addElement('submit', 'today', __('Today'), array('onclick'=>'$("planner_navigation").value="today";'));
			$this->form->addElement('hidden', 'navigation', '', array('id'=>'planner_navigation'));
			$link_text = '$(\'planner_navigation\').value=\'__YEAR__-__MONTH__-__DAY__\';'.$this->form->get_submit_form_js().';';
			$theme->assign('popup_calendar', Utils_PopupCalendarCommon::show('week_selector', $link_text,'day',$fdow,null,null,$this->date));
			eval_js('$("planner_navigation").value="";');
		}

		$values = $this->get_module_variable('preserve_values', null);
		if ($values===null)
			$values = $this->form->exportValues();
		else
			$this->unset_module_variable('preserve_values');
		$this->form->setDefaults($values);

		$validate = false;
		if ($this->form->validate()) {
			$validate = true;
			foreach ($values as $k=>$v)
				$this->values[$k] = $v;
		}
			
		$this->form->assign_theme('form', $theme);
		$theme->display();
		Base_ActionBarCommon::add('save',__('Save'),$this->form->get_submit_form_href());
		Base_ActionBarCommon::add('back',__('Back'),$this->create_back_href());
		
		$time_frames = explode(';',$values['grid_selected_frames']);
		if (!empty($time_frames) && $time_frames[0]) {
			foreach ($time_frames as $k=>$v) {
				$x = explode('::',$v);
				if (!isset($x[2])) trigger_error(print_r($time_frames, true));
				list($day, $s, $e) = explode('::',$v);
				foreach ($this->grid as $v) {
					if ($v>=$s && $v<$e) {
						eval_js('time_grid_mouse_down('.$v.','.$day.',"used");');
					}
				}
			}
			eval_js('time_grid_mouse_up();');
		}
		if (isset($values['navigation']) && $values['navigation']) {
			switch ($values['navigation']) {
				case 'next_day': $ch = strtotime('+1 day', $this->date); break;
				case 'prev_day': $ch = strtotime('-1 day', $this->date); break;
				case 'next_week': $ch = strtotime('+7 days', $this->date); break;
				case 'prev_week': $ch = strtotime('-7 days', $this->date); break;
				case 'today': $ch = strtotime(date('Y-m-d')); break;
				default: $ch = strtotime($values['navigation']);
			}
			if ($ch) {
				$values['navigation'] = '';
				$this->set_module_variable('fixed_date', $ch);
				$this->set_module_variable('preserve_values', $values);
				location(array());
				return;
			}
		}
		if ($validate) {
			unset($values['grid_selected_frames']);
			foreach ($time_frames as $k=>$v) {
				if (!$v) {
					unset($time_frames[$k]);
					break;
				}
				list($day, $s, $e) = explode('::',$v);
				$time_frames[$k] = array('day'=>$day, 'start'=>$s, 'end'=>$e);
			}
			call_user_func($_SESSION['client']['utils_planner']['processing_callback'], $values, $time_frames);
			$this->set_back_location();
			location(array());
		}
		foreach ($this->values as $k=>$v) {
			$_SESSION['client']['utils_planner']['resources'][$k]['value'] = $v;
			$_SESSION['client']['utils_planner']['resources'][$k]['in_use'] = array();
		}
		foreach ($this->values as $k=>$v) {
			eval_js(Utils_PlannerCommon::resource_changed($k, $v));
		}
	}
}

?>
