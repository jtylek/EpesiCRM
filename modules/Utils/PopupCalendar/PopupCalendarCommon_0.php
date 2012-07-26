<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com> and Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-Utils
 * @subpackage PopupCalendar
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_PopupCalendarCommon extends ModuleCommon {
	public static function show($name,$function = '',$mode=null,$first_day_of_week=null,$pos_js=null,$label=null,$default=null) {
		if ($label===null) {
			if($mode=='month') {
				$label = __('Select month');
			} elseif($mode=='year') {
				$label = __('Select year');
			} else {
				$label = __('Select date');
			}
		}

		return '<a class="button" '.self::create_href($name,$function,$mode,$first_day_of_week,$pos_js,$default).'>' . $label . '&nbsp;&nbsp;<img style="vertical-align: middle;" src=' . Base_ThemeCommon::get_template_file('Utils_PopupCalendar', 'select.png').'>' . '</a>';
	}

	public static function create_href($name,$function = '',$mode=null,$first_day_of_week=null,$pos_js=null,$default=null,$id=null) {
		Base_ThemeCommon::load_css('Utils_PopupCalendar');
		load_js('modules/Utils/PopupCalendar/js/main2.js');
		load_js('modules/Utils/PopupCalendar/datepicker.js');

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

		$entry = 'datepicker_'.$name.'_calendar';
		$butt = $id===null?'datepicker_'.$name.'_button':$id;

		$smarty = Base_ThemeCommon::init_smarty();
		$smarty->assign('calendar',$calendar);
		ob_start();
		Base_ThemeCommon::display_smarty($smarty,'Utils_PopupCalendar');
		$cal_out = ob_get_clean();


		print('<div id="'.$entry.'" class="utils_popupcalendar_popup" style="display:none;z-index:2050;width:1px;">'.
			$cal_out.
			'</div>');

		if(!isset($pos_js)) $pos_js = 'popup.clonePosition(\''.$butt.'\',{setWidth:false,setHeight:false,offsetTop:$(\''.$butt.'\').getHeight()});';
		eval_js('if(Epesi.ie)$(\''.$entry.'\').style.position="fixed";else $(\''.$entry.'\').absolutize();');

		$ret = 'onClick="var popup=$(\''.$entry.'\');'.$pos_js.';$(\''.$entry.'\').toggle()" href="javascript:void(0)" id="'.$butt.'"';
		$function .= ';$(\''.$entry.'\').hide()';

		if ($default) {
			if (!is_numeric($default)) $default = strtotime($default);
			$args = date('Y',$default).','.(date('n',$default)-1).','.(date('d',$default));
		} else $args = '';
		$js = 'var datepicker_'.$name.' = new Utils_PopupCalendar("'.Epesi::escapeJS($function,true,false).'", \''.$name.'\',\''.$mode.'\',\''.$first_day_of_week.'\',';
		$months = array(__('January'),__('February'),__('March'),__('April'),__('May'),__('June'),__('July'),__('August'),__('September'),__('October'),__('November'),__('December'));
		$days = array(__('Sun'),__('Mon'),__('Tue'),__('Wed'),__('Thu'),__('Fri'),__('Sat'));
		$js .= 'new Array(\''.implode('\',\'', $months).'\'),';
		$js .= 'new Array(\''.implode('\',\'', $days).'\')';
		$js .= ');'.
			'datepicker_'.$name.'.show('.$args.')';
		eval_js($js);
//		eval_js('$(\''.$entry.'\').absolutize();');
		return $ret;
	}


	public static function user_settings() {
		if(Acl::is_user()) {
			return array(
				__('Calendar')=>array(
					array('name'=>'first_day_of_week','label'=>__('First day of week'), 'type'=>'select', 'values'=>array(0=>__('Sunday'), 1=>__('Monday'), 2=>__('Tuesday'), 3=>__('Wednesday'), 4=>__('Thursday'), 5=>__('Friday'), 6=>__('Saturday')), 'default'=>0),
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
$GLOBALS['HTML_QUICKFORM_ELEMENT_TYPES']['timestamp'] = array('modules/Utils/PopupCalendar/timestamp.php','HTML_QuickForm_timestamp');

?>
