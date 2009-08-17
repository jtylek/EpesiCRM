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
	}
	
	public function set_resource_availability_check_callback($callback) {
		$_SESSION['client']['utils_planner']['resource_availability_check_callback'] = $callback;
	}
	
	public function add_resource($def, $prop=array()) {
		list($type, $name, $label, $param1) = $def;
		if (isset($def[4])) $param2 = $def[4];
		if (isset($def[5])) $param3 = $def[5];
		$_SESSION['client']['utils_planner']['resources'][$name]=array('type'=>$type,'in_use'=>array(),'value'=>false);
		$on_change = 'resource_changed(\''.$name.'\')';
//		if (isset($prop['chained'])) foreach ($prop['chained'] as $v) $on_change .= ';resource_changed(\''.$v.'\')';
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
		print('<div id="xxx"></div>');
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
		$base_unix_time = strtotime('1970-01-01 00:00');
		$last_time = $this->start_time;
		while ($time<$this->end_time) {
			$grid_legend[$time] = Base_RegionalSettingsCommon::time2reg($base_unix_time+$time*60,'without_seconds',false,false);
			$grid[] = $time;
			$last_time = $time;
			$time += $this->grid_size;
			$grid_legend[$last_time] .= ' - '.Base_RegionalSettingsCommon::time2reg($base_unix_time+$time*60,'without_seconds',false,false);
			$grid_attrs[$last_time] = array(); 
			foreach ($headers as $k=>$v) $grid_attrs[$last_time][$k] = 'class="noconflict unused" id="'.$k.'__'.$last_time.'" onmousedown="time_grid_mouse_down('.$last_time.','.$k.')" onmousemove="time_grid_mouse_move('.$last_time.','.$k.')"';
		}
		$theme->assign('grid_legend',$grid_legend);
		$theme->assign('grid_attrs',$grid_attrs);
		$_SESSION['client']['utils_planner']['grid']=$grid;

		$theme->display();
		$this->form->display();
	}
}

?>
