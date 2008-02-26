<?php
/**
 * Regional settings like currency, time...
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package base-regionalsettings
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_RegionalSettingsCommon extends ModuleCommon {
	private static $curr_locale;
	private static $curr_tz=null;
	private static $months_en_short = array('Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec');
	private static $months_en = array('January','February','March','April','May','June','July','August','September','October','November','December');
	private static $countries = array(
				'cn'=>'chinese',
				'cz'=>'czech',
				'dk'=>'danish',
				'nl'=>'dutch',
				'be'=>'belgian',
				'en'=>'english',
				'fi'=>'finnish',
				'fr'=>'french',
				'de'=>'german',
				'hu'=>'hungarian',
				'it'=>'italian',
				'jp'=>'japanese',
				'kp'=>'korean',
				'no'=>'norwegian',
				'pl'=>'polish',
				'pt'=>'portuguese',
				'ru'=>'russian',
				'sk'=>'slovak',
				'es'=>'spanish',
				'se'=>'swedish',
				'tr'=>'turkish');


	public static function user_settings() {
		$now = time();
		$date_formats_proto = array('%Y-%m-%d','%y-%m-%d','%m/%d/%y','%d %B %Y','%d %B %y','%d %b %Y','%d %b %y','%b %d, %Y');
		$date_formats = array();
		self::set_locale();
		foreach($date_formats_proto as $f)
			$date_formats[$f] = self::strftime($f,$now);
		self::restore_locale();
		if(!function_exists('timezone_identifiers_list'))
			require_once('tz_list.php');
		$tz = timezone_identifiers_list();
		return array('Regional settings'=>array(
				//array('type'=>'select','name'=>'currency','label'=>'Currency') //google X pln in usd????
				array('type'=>'select','name'=>'date','label'=>'Date format',
					'default'=>'%m/%d/%y','values'=>$date_formats),//strftime
				array('type'=>'select','name'=>'time','label'=>'Time format',
					'default'=>'%H:%M:%S','values'=>array('%I:%M:%S %p'=>'12h am/pm', '%H:%M:%S'=>'24h'),
					'rule'=>array('type'=>'callback',
						'func'=>array('Base_RegionalSettingsCommon','check_12h'),
						'message'=>'This language does not support 12h clock',
						'param'=>'__form__')
				),
				array('type'=>'select','name'=>'tz','label'=>'Timezone', 'default'=>SYSTEM_TIMEZONE, 'values'=>array_combine($tz,$tz)),
				array('type'=>'header','label'=>'Your location','name'=>null),
				array('name'=>'default_country', 'type'=>'callback','func'=>array('Base_RegionalSettingsCommon','default_country_elem'),'default'=>'US'),
				array('name'=>'default_state', 'type'=>'callback','func'=>array('Base_RegionalSettingsCommon','default_state_elem'),'default'=>'')
			));
	}

	private static $country_elem_name;
	public static function default_country_elem($name, $args, & $def_js) {
		self::$country_elem_name = $name;
		return HTML_QuickForm::createElement('commondata',$name,'Country','Countries');
	}

	public static function default_state_elem($name, $args, & $def_js) {
		return HTML_QuickForm::createElement('commondata',$name,'State',array('Countries',self::$country_elem_name),array('empty_option'=>true));
	}

	public static function get_default_location() {
		$country = Base_User_SettingsCommon::get('Base_RegionalSettings','default_country');
		$state = Base_User_SettingsCommon::get('Base_RegionalSettings','default_state');
		return array(0=>$country,'country'=>$country,'state'=>$state,1=>$state);
	}

	//method used by user settings
	public static function check_12h($v,$form) {
		$t = strtotime('2010-01-01 20:00');

		$curr_locale = setlocale(LC_TIME,0);
		$lang_code = $form->exportValue('Base_Lang_Administrator__language');
		setlocale(LC_TIME,$lang_code.'_'.strtoupper($lang_code).'.utf8', //unixes
				$lang_code.'_'.strtoupper($lang_code).'.UTF-8',
				$lang_code.'.utf8',
				$lang_code.'.UTF-8',
				isset(self::$countries[$lang_code])?self::$countries[$lang_code]:null);//win32

		$ret = ($t == strtotime('2010-01-01 '.strftime($v,$t)));
		/*print($v.': '.$t.'<br>');
		print(strftime($v,$t).'<br>');
		print(strtotime('2010-01-01 '.strftime($v,$t)).'<br>');*/

		setlocale(LC_TIME,$curr_locale);

		return $ret;
	}

	/**
	 * Convert local time to client format and timezone(optional)
	 *
	 * @param mixed string-strtotime recognizable string, null-current time, int-unix time
	 * @param mixed {0,false,null,''}-no time,{1,true,'with_seconds'}-time with seconds,{2,'without_seconds'}-time without seconds
	 * @param mixed {0,false,null,''}-no date,{1,true}-with date
	 * @param boolean convert to client time
	 * @param boolean use regional user format
	 * @return string
	 */
	public static function time2reg($t=null,$time=true,$date=true,$tz=true,$reg_format=true) {
		if(!isset($t)) $t = time();
		elseif(!is_numeric($t) && is_string($t)) $t = strtotime($t);
		if($reg_format) {
			$format = array();
			if($date)
				$format[] = Base_User_SettingsCommon::get('Base_RegionalSettings','date');
			if($time) {
				$sec = Base_User_SettingsCommon::get('Base_RegionalSettings','time');
				if($time==2 || strcasecmp($time,'without_seconds')==0)
					$sec = str_replace(':%S','',$sec);
				$format[] = $sec;
			}
			$format = implode(' ',$format);
		} else {
			$format = '%Y-%m-%d %H:%M:%S';
		}

		self::set_locale();
		if($tz)
			self::set_tz();
		$ret = self::strftime($format,$t);
		self::restore_locale();
		if($tz)
			self::restore_tz();
		return $ret;
	}
	
	private static function strftime($format,$timestamp) {
		$ret = strftime($format,$timestamp);
		if ( strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' )
		 	return iconv('','UTF-8',$ret);
		return $ret;
	}

	public static function set_locale($tz = true) {
		self::$curr_locale = setlocale(LC_TIME,0);
		$lang_code = strtolower(Base_LangCommon::get_lang_code());
		setlocale(LC_TIME,$lang_code.'_'.strtoupper($lang_code).'.utf8', //unixes
				$lang_code.'_'.strtoupper($lang_code).'.UTF-8',
				$lang_code.'.utf8',
				$lang_code.'.UTF-8',
				isset(self::$countries[$lang_code])?self::$countries[$lang_code]:null);//win32
	}
	
	public static function set_tz() {
		if(Acl::is_user()) {
			self::$curr_tz = date_default_timezone_get();
			date_default_timezone_set(Base_User_SettingsCommon::get('Base_RegionalSettings','tz'));
		}
	}

	public static function restore_tz() {
		if(self::$curr_tz!==null) {
			date_default_timezone_set(self::$curr_tz);
			self::$curr_tz=null;
		}
	}

	public static function restore_locale() {
		setlocale(LC_TIME,self::$curr_locale);
	}
	
	public static function set() {
		self::set_tz();
		self::set_locale();
	}

	public static function restore() {
		self::restore_tz();
		self::restore_locale();
	}

	/**
	 * Convert regional time format to unix time (and server timezone)
	 *
	 * @param string
	 * @param boolean convert from local time to server time
	 * @return int
	 */
	public static function reg2time($t,$from_local=true) {
		$datef = Base_User_SettingsCommon::get('Base_RegionalSettings','date');

		self::set_locale($from_local);
		if($from_local)
			self::set_tz();
		if(strpos($datef,'%B')>=0) {
			$months = array();
			for($i=1; $i<=12; $i++)
				$months[] = self::strftime('%B',strtotime($i.'/'.$i));
			$t = str_replace($months,self::$months_en,$t);
		}

		if(strpos($datef,'%b')>=0) {
			$months = array();
			for($i=1; $i<=12; $i++)
				$months[] = self::strftime('%b',strtotime($i.'/'.$i));
			$t = str_replace($months,self::$months_en_short,$t);
		}

		$tt = strtotime($t);
		if($from_local)
			self::restore_tz();
		self::restore_locale();
		return $tt;
	}

	/**
	 * Get date format (strftime).
	 *
	 * @return string
	 */
	public static function date_format() {
		return Base_User_SettingsCommon::get('Base_RegionalSettings','date');
	}

	/**
	 * Get time format (strftime).
	 *
	 * @return string
	 */
	public static function time_format() {
		return Base_User_SettingsCommon::get('Base_RegionalSettings','date').' '.Base_User_SettingsCommon::get('Base_RegionalSettings','time');
	}

	/**
	 * Is user clock 12h?
	 *
	 * @return boolean
	 */
	public static function time_12h() {
		return '%I:%M:%S %p'==Base_User_SettingsCommon::get('Base_RegionalSettings','time');
	}
}
?>
