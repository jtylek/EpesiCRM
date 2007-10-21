<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Calendar_Utils_MiniCalendar extends Module {
	private static $counter = 0;
	private $link_text;
	
	public function construct($link_text = '') {
		$this->link_text = $link_text;
	}
	
	public function body() {
		self::$counter++;
		$theme = & $this->init_module('Base/Theme');
		$theme->display();
		load_js('modules/CRM/Calendar/Utils/MiniCalendar/js/main.js');
		$post = array(
			'<table><tr><td id="datepicker_'.self::$counter.'_header">a</td></tr>'.
			'<tr><td id="datepicker_'.self::$counter.'_view">aa</td></tr></table>'
		);
		$curr = 'Select date';
		//$dr = & $this->init_module('CRM/Calendar/Utils/Dropdown');
		//$dr->set_current($curr);
		//$dr->set_values($post);
		//$this->display_module($dr);
		$entry = 'datepicker_'.self::$counter.'_calendar';
		$info = '<a rel="'.$entry.'" class="lbOn">'.$curr.'</a>';
		$iii = '<div id="'.$entry.'" class="leightbox" style="width: 400px; height 400px">';
		$iii .= 
			'<table><tr><td id="datepicker_'.self::$counter.'_header">a</td></tr>'.
			'<tr><td id="datepicker_'.self::$counter.'_view">aa</td></tr></table>';
		$iii .= '<a class="lbAction" rel="deactivate">Close</a></div>';
		print $info.$iii;
		eval_js('
			make_cal = function() {
				datepicker_'.self::$counter.' = new CRM_Calendar_Utils_MiniCalendar("'.$this->link_text.'", '.self::$counter.');
				datepicker_'.self::$counter.'.show_month();
			}
		');
		eval_js('wait_while_null("CRM_Calendar_Utils_MiniCalendar", "make_cal()");');
	}

}

?>