<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_PopupCalendarCommon extends ModuleCommon {
	public static function show($name,$function = '',$fullscreen=true,$mode=null,$first_day_of_week=null,$pos_js=null) {
		if($mode=='month') {
			$label = Base_LangCommon::ts('Utils_PopupCalendar','Select month');
		} elseif($mode=='year') {
			$label = Base_LangCommon::ts('Utils_PopupCalendar','Select year');
		} else {
			$label = Base_LangCommon::ts('Utils_PopupCalendar','Select date');
		}

		return '<a class="button" '.self::create_href($name,$function,$fullscreen,$mode,$first_day_of_week,$pos_js).'>' . $label . '&nbsp;&nbsp;<img style="vertical-align: middle;" border="0" width="10" height="16" src=' . Base_ThemeCommon::get_template_file('Utils_PopupCalendar', 'select.gif').'>' . '</a>';
	}

	public static function create_href($name,$function = '',$fullscreen=true,$mode=null,$first_day_of_week=null,$pos_js=null) {
		Base_ThemeCommon::load_css('Utils_PopupCalendar');
		load_js('modules/Utils/PopupCalendar/js/main.js');

		if(!isset($mode)) $mode='day';

		if(!isset($first_day_of_week)) {
			if(Acl::is_user())
				$first_day_of_week=self::get_first_day_of_week();
			else
				$first_day_of_week=0;
		} elseif(!is_numeric($first_day_of_week))
			trigger_error('Invalid first day of week',E_USER_ERROR);

		$calendar = '<div id="Utils_PopupCalendar">'.
			'<table cellspacing="0" cellpadding="0" border="0"><tr><td id="datepicker_'.$name.'_header">error</td></tr>'.
			'<tr><td id="datepicker_'.$name.'_view">calendar not loaded</td></tr></table></div>';

		if($fullscreen) {
			$entry = 'datepicker_'.$name.'_calendar';
			$ret = 'style="cursor: pointer;" rel="'.$entry.'" class="button lbOn"';

			print(Libs_LeightboxCommon::get($entry,'<br><center>'.$calendar.'</center>',Base_LangCommon::ts('Utils_PopupCalendar','Calendar')));

			$function .= ';leightbox_deactivate(\''.$entry.'\');';
		} else {
			$entry = 'datepicker_'.$name.'_calendar';
			$butt = 'datepicker_'.$name.'_button';

			$smarty = Base_ThemeCommon::init_smarty();
			$smarty->assign('calendar',$calendar);
			ob_start();
			Base_ThemeCommon::display_smarty($smarty,'Utils_PopupCalendar');
			$cal_out = ob_get_clean();


			print('<div id="'.$entry.'" class="utils_popupcalendar_popup" style="display:none;z-index:8;">'.
				$cal_out.
				'</div>');

			if(!isset($pos_js)) $pos_js = 'popup.clonePosition(\''.$butt.'\',{setWidth:false,setHeight:false,offsetTop:$(\''.$butt.'\').getHeight()})';
			eval_js('$(\''.$entry.'\').absolutize();');

			$ret = 'onClick="var popup=$(\''.$entry.'\');'.$pos_js.';$(\''.$entry.'\').toggle()" href="javascript:void(0)" id="'.$butt.'"';
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
