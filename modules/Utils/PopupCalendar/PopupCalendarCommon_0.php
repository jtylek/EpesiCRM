<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_PopupCalendarCommon extends ModuleCommon {
	public static function show($name,$function = '',$fullscreen=true,$mode=null,$first_day_of_week=null,$top=null,$left=null) {
		Base_ThemeCommon::load_css('Utils_PopupCalendar');
		load_js('modules/Utils/PopupCalendar/js/main.js');
		
		if(!isset($first_day_of_week)) {
			if(Acl::is_user())
				$first_day_of_week=self::get_first_day_of_week();
			else
				$first_day_of_week=0;
		} elseif(!is_numeric($first_day_of_week))
			trigger_error('Invalid first day of week',E_USER_ERROR);
		
		if($mode=='month') {
			$label = Base_LangCommon::ts('Utils_PopupCalendarCommon','Select month');
		} elseif($mode=='year') {
			$label = Base_LangCommon::ts('Utils_PopupCalendarCommon','Select year');
		} else {
			$label = Base_LangCommon::ts('Utils_PopupCalendarCommon','Select date');
		}
		
		$calendar = '<div id="Utils_PopupCalendar">'.
			'<table cellspacing="0" cellpadding="0" border="0"><tr><td id="datepicker_'.$name.'_header">error</td></tr>'.
			'<tr><td id="datepicker_'.$name.'_view">calendar not loaded</td></tr></table></div>';
		$ret = '';
		if($fullscreen) {
			$entry = 'datepicker_'.$name.'_calendar';
			$ret = '<a style="cursor: pointer;" rel="'.$entry.'" class="button lbOn">' . $label . '&nbsp;&nbsp;<img style="padding-bottom: 2px;" border="0" width="10" height="8" src=' . Base_ThemeCommon::get_template_file('Utils_PopupCalendar', 'select.png').'>' . '</a>';

			$ret .= '<div id="'.$entry.'" class="leightbox">'.
				$calendar .
				'<br><a class="button lbAction" rel="deactivate" id="close_leightbox">Close&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img style="vertical-align: top; padding-top: 3px;" src="' . Base_ThemeCommon::get_template_file('Utils/PopupCalendar','close.png') . '"> width="14" height="14" alt="x" border="0"></a>'.
				'</div>';

			$function .= ';leightbox_deactivate(\''.$entry.'\');';
		} else {
			$entry = 'datepicker_'.$name.'_calendar';
			$butt = 'datepicker_'.$name.'_button';
			$ret = '<a onClick="$(\''.$entry.'\').toggle()" href="javascript:void(0)" class="button" id="'.$butt.'">'.$label.'</a>';
			if(!isset($left)) $left = 'expression( ($(\''.$butt.'\').getStyle(\'left\') )+\'px\')';
			if(!isset($top)) $top = 'expression( ($(\''.$butt.'\').getStyle(\'top\')+$(\''.$butt.'\').getStyle(\'height\') )+\'px\')';
			$ret .= '<div id="'.$entry.'" class="utils_popupcalendar_popup" style="top: '.Epesi::escapeJS($top,true,false).';left:'.Epesi::escapeJS($left,true,false).';display:none;z-index:8;position:absolute;">'.
				$calendar.
				'</div>';
			$function .= ';$(\''.$entry.'\').hide()';
		}

		eval_js(
			'var datepicker_'.$name.' = new Utils_PopupCalendar("'.Epesi::escapeJS($function,true,false).'", \''.$name.'\',\''.$mode.'\',\''.$first_day_of_week.'\');'.
			'datepicker_'.$name.'.show()'
		);
		return $ret;
	}

	public static function user_settings() {
		if(Acl::is_user()) {
			return array(
				'Calendar'=>array(
					array('name'=>'first_day_of_week','label'=>'First day of week', 'type'=>'select', 'values'=>array(0=>'Sunday', 1=>'Monday', 2=>'Tuesday', 3=>'Wednestday', 4=>'Thursday', 5=>'Friday', 6=>'Saturday'), 'default'=>0),
				)
			);
		}
		return array();
	}
	
	public static function get_first_day_of_week() {
		return Base_User_SettingsCommon::get('Utils_PopupCalendar','first_day_of_week');
	}

}

$GLOBALS['HTML_QUICKFORM_ELEMENT_TYPES']['datepicker'] = array('modules/Utils/PopupCalendar/datepicker.php','HTML_QuickForm_datepicker');

?>
