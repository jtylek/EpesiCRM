<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_CalendarCommon extends ModuleCommon {
	private static $counter = 0;
	
	public function show($function = '') {
		self::$counter++;
		
		Base_ThemeCommon::load_css('Utils_Calendar');
		load_js('modules/Utils/Calendar/js/main.js');
		
		$curr = 'Select date';
		$entry = 'datepicker_'.self::$counter.'_calendar';
		$info = '<a rel="'.$entry.'" class="lbOn">'.$curr.'</a>';
		$iii = '<div id="'.$entry.'" class="leightbox">';
		$iii .= '<table><tr><td id="datepicker_'.self::$counter.'_header">error</td></tr>'.
				'<tr><td id="datepicker_'.self::$counter.'_view">calendar not loaded</td></tr></table>';
		$iii .= '<a class="lbAction" rel="deactivate">Close</a></div>';
		eval_js(
			'datepicker_'.self::$counter.' = new Utils_Calendar("'.$function.'", '.self::$counter.');'.
			'datepicker_'.self::$counter.'.show_month();'
		);
		return $info.$iii;
	}
}

$GLOBALS['HTML_QUICKFORM_ELEMENT_TYPES']['datepicker'] = array('modules/Utils/Calendar/datepicker.php','HTML_QuickForm_datepicker');

?>
