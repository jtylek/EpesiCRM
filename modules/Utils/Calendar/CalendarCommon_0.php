<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_CalendarCommon extends ModuleCommon {
	public function show($name,$function = '') {
		Base_ThemeCommon::load_css('Utils_Calendar');
		load_js('modules/Utils/Calendar/js/main.js');
		
		$curr = 'Select date';
		$entry = 'datepicker_'.$name.'_calendar';
		$info = '<a rel="'.$entry.'" class="lbOn">'.$curr.'</a>';

		$iii = '<div id="'.$entry.'" class="leightbox"><div id="Utils_Calendar">';
		$iii .= '<table><tr><td id="datepicker_'.$name.'_header">error</td></tr>'.
				'<tr><td id="datepicker_'.$name.'_view">calendar not loaded</td></tr></table>';
		$iii .= '<a class="lbAction" rel="deactivate" id="close_leightbox">Close</a></div></div>';

		$function .= ';leightbox_deactivate(\''.$entry.'\');';

		eval_js(
			'var datepicker_'.$name.' = new Utils_Calendar("'.Epesi::escapeJS($function,true,false).'", \''.$name.'\');'.
			'datepicker_'.$name.'.show_month()'
		);
		return $info.$iii;
	}
}

$GLOBALS['HTML_QUICKFORM_ELEMENT_TYPES']['datepicker'] = array('modules/Utils/Calendar/datepicker.php','HTML_QuickForm_datepicker');

?>
