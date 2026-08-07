<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com> and Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.0
 * @package epesi-Utils
 * @subpackage PopupCalendar
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_PopupCalendarCommon extends ModuleCommon {
	// AdminLTE-only: Base_AdminlteIcons::resolve() looks this up for this
	// module's icon (sidebar menu, ActionBar launcher, admin panels, module
	// indicator, etc.) instead of a central map - see
	// modules/Base/Theme/adminlte_icons.php.
	public static function adminlte_icon() { return 'bi-calendar3'; }

	public static function show($name,$function = '',$mode=null,$first_day_of_week=null,$pos_js=null,$label=null,$default=null) {
        // label seems to be unused and always null.
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
		// Same Utils_PopupCalendar class/API either way (constructor args, show(),
		// show_month()/show_year()/show_decade()/show_century()) - the adminlte
		// variant only swaps the header/grid markup it builds for Bootstrap
		// classes; positioning/show-hide below and datepicker.js's validation
		// are jQuery-based for both themes.
		if (Base_ThemeCommon::is_adminlte_family())
			load_js('modules/Utils/PopupCalendar/theme_adminltedark/main2.js');
		else
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
			'<div id="datepicker_'.$name.'_header">error</div>'.
			'<div id="datepicker_'.$name.'_view">calendar not loaded</div></div>';

		$entry = 'datepicker_'.$name.'_calendar';
		$butt = $id ?? 'datepicker_' . $name . '_button';

		$smarty = Base_ThemeCommon::init_smarty();
		$smarty->assign('calendar',$calendar);
		ob_start();
		Base_ThemeCommon::display_smarty($smarty,'Utils_PopupCalendar');
		$cal_out = ob_get_clean();


		print('<div id="'.$entry.'" class="utils_popupcalendar_popup" style="display:none;z-index:2050;width:1px;">'.
			$cal_out.
			'</div>');

		if(!isset($pos_js)) $pos_js = 'jQuery(popup).clonePosition(document.getElementById(\''.$butt.'\'),{setWidth:false,setHeight:false,offsetTop:document.getElementById(\''.$butt.'\').offsetHeight});';
		// Prototype's absolutize() also preserved the element's current
		// rendered top/left/width/height when switching it to position:absolute
		// - moot here, since this div stays display:none until the onClick
		// handler above both repositions it via clonePosition() and reveals it
		// via toggle() in the same synchronous call, so nothing is ever visible
		// mid-transition.
		eval_js('if(Epesi.ie)document.getElementById(\''.$entry.'\').style.position="fixed";else document.getElementById(\''.$entry.'\').style.position="absolute";');

		// clonePosition() above only clones the trigger's own position, with no
		// awareness of the viewport's edges - clampToViewport() (main2.js, both
		// themes) nudges the now-visible popup back on-screen if it renders past
		// the right edge of the window (e.g. a field anchored in a narrow/right-
		// hand column). Only run once toggle() has actually shown the popup, not
		// when the same click is hiding an already-open one.
		$ret = 'onClick="var popup=document.getElementById(\''.$entry.'\');'.$pos_js.';jQuery(popup).toggle();if(jQuery(popup).is(\':visible\'))Utils_PopupCalendar.clampToViewport(popup);" href="javascript:void(0)" id="'.$butt.'"';
		$function .= ';jQuery(document.getElementById(\''.$entry.'\')).hide()';

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
