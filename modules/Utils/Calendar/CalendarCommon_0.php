<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_CalendarCommon extends ModuleCommon {
	public function show($name,$function = '',$fullscreen=true,$top=0,$left=0) {
		Base_ThemeCommon::load_css('Utils_Calendar');
		load_js('modules/Utils/Calendar/js/main.js');

		$label = 'Select date';
		$calendar = '<div id="Utils_Calendar">'.
			'<table><tr><td id="datepicker_'.$name.'_header">error</td></tr>'.
			'<tr><td id="datepicker_'.$name.'_view">calendar not loaded</td></tr></table></div>';
		$ret = '';
		if($fullscreen) {
			$entry = 'datepicker_'.$name.'_calendar';
			$ret = '<a rel="'.$entry.'" class="button lbOn">'.$label.'</a>';

			$ret .= '<div id="'.$entry.'" class="leightbox">'.
				$calendar.
				'<a class="lbAction" rel="deactivate" id="close_leightbox">Close</a>'.
				'</div>';

			$function .= ';leightbox_deactivate(\''.$entry.'\');';
		} else {
			load_css('modules/Utils/Calendar/popup.css');
			$entry = 'datepicker_'.$name.'_calendar';
			$ret = '<a onClick="$(\''.$entry.'\').toggle()" href="javascript:void(0)">'.$label.'</a>';
			$ret .= '<div id="'.$entry.'" class="utils_calendar_popup" style="top: '.Epesi::escapeJS($top,true,false).';left:'.Epesi::escapeJS($left,true,false).';display:none;z-index:1;position:absolute;">'.
				$calendar.
				'</div>';
			$function .= ';$(\''.$entry.'\').hide()';
		}

		eval_js(
			'var datepicker_'.$name.' = new Utils_Calendar("'.Epesi::escapeJS($function,true,false).'", \''.$name.'\');'.
			'datepicker_'.$name.'.show_month()'
		);
		return $ret;
	}
}

$GLOBALS['HTML_QUICKFORM_ELEMENT_TYPES']['datepicker'] = array('modules/Utils/Calendar/datepicker.php','HTML_QuickForm_datepicker');

?>
