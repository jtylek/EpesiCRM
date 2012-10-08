<?php
/**
 * Regional settings like currency, time...
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage regionalsettings
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
				'tr'=>'turkish',
				'gr'=>'greek');
	private static $encodings = array(
				'chinese'=>'CP936',
				'czech'=>'CP1250',
				'danish'=>'CP1252',
				'dutch'=>'CP1252',
				'belgian'=>'CP1252',
				'english'=>'CP1252',
				'finnish'=>'CP1252',
				'french'=>'CP437',
				'german'=>'CP1252',
				'hungarian'=>'CP1250',
				'italian'=>'CP1252',
				'japanese'=>'CP932',
				'korean'=>'CP949',
				'norwegian'=>'CP1252',
				'polish'=>'CP1250',
				'portuguese'=>'CP1252',
				'russian'=>'CP1251',
				'slovak'=>'CP1250',
				'spanish'=>'CP1252',
				'swedish'=>'CP1252',
				'turkish'=>'CP1254',
				'greek'=>'CP1253');


	public static function user_settings() {
		$now = strtotime('2008-02-15');
		$date_formats_proto = array('%Y-%m-%d','%m/%d/%Y','%d %B %Y','%d %b %Y','%b %d, %Y');
		$date_formats = array();
		foreach($date_formats_proto as $f)
			$date_formats[$f] = self::strftime($f,$now);
		if(!function_exists('timezone_identifiers_list'))
			require_once('modules/Base/RegionalSettings/tz_list.php');
		$tz = timezone_identifiers_list();
		return array(__('Regional Settings')=>array(
				array('type'=>'header','label'=>__('Date & Time'),'name'=>null),
				array('type'=>'select','name'=>'date','label'=>__('Date format'),
					'default'=>'%m/%d/%Y','values'=>$date_formats),//strftime
				array('type'=>'select','name'=>'time','label'=>__('Time format'),
					'default'=>'%H:%M:%S','values'=>array('%I:%M:%S %p'=>'12h am/pm', '%H:%M:%S'=>'24h'),
					'rule'=>array('type'=>'callback',
						'func'=>array('Base_RegionalSettingsCommon','check_12h'),
						'message'=>'This language does not support 12h clock',
						'param'=>'__form__')
				),
				array('type'=>'select','name'=>'tz','label'=>__('Timezone'), 'default'=>SYSTEM_TIMEZONE, 'values'=>array_combine($tz,$tz)),
				array('type'=>'header','label'=>__('Your location'),'name'=>null),
				array('name'=>'default_country', 'type'=>'callback','func'=>array('Base_RegionalSettingsCommon','default_country_elem'),'default'=>'US'),
				array('name'=>'default_state', 'type'=>'callback','func'=>array('Base_RegionalSettingsCommon','default_state_elem'),'default'=>'')
			));
	}

	private static $country_elem_name;
	public static function default_country_elem($name, $args, & $def_js) {
		self::$country_elem_name = $name;
		return HTML_QuickForm::createElement('commondata',$name,__('Country'),'Countries');
	}

	public static function default_state_elem($name, $args, & $def_js) {
		return HTML_QuickForm::createElement('commondata',$name,__('State'),array('Countries',self::$country_elem_name),array('empty_option'=>true));
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
		if (!is_string($lang_code)) $lang_code = Base_User_SettingsCommon::get('Base_Lang_Administrator','language');
		array(LC_TIME,$lang_code.'_'.strtoupper($lang_code).'.utf8', //unixes
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
	 * @param mixed {0,false,null,''}-no time (you probably don't want to set it!),{1,true,'with_seconds'}-time with seconds,{2,'without_seconds'}-time without seconds
	 * @param mixed {0,false,null,''}-no date,{1,true}-with date,{2,'without_year'}-date without year, {3,'with_weekday'}-date with weekday
	 * @param boolean convert to client time
	 * @param boolean use regional user format
	 * @return string
	 */
	public static function time2reg($t=null,$time=true,$date=true,$tz=true,$reg_format=true) {
		if(!isset($t)) $t = time();
		elseif(!is_numeric($t) && is_string($t)) $t = strtotime($t);
		if($reg_format) {
			$format = array();
			if($date) {
				$d = Base_User_SettingsCommon::get('Base_RegionalSettings','date');
				if($date===2 || strcasecmp($date,'without_year')==0)
					$d = str_replace(array(', %Y','/%Y','%Y-',' %Y'),'',$d);
				elseif($date===3 || strcasecmp($date,'with_weekday')==0)
					$d = '%a - '.$d;
				$format[] = $d;
			}

			if($time) {
				$sec = Base_User_SettingsCommon::get('Base_RegionalSettings','time');
				if($time===2 || strcasecmp($time,'without_seconds')==0)
					$sec = str_replace(':%S','',$sec);
				$format[] = $sec;
			}
			$format = implode(' ',$format);
		} else {
			if($time && $date)
				$format = '%Y-%m-%d %H:%M:%S';
			elseif($time)
				$format = '%H:%M:%S';
			elseif($date)
				$format = '%Y-%m-%d';
			else
				$format = '';
			if($time===2 || strcasecmp($time,'without_seconds')==0)
				$format = str_replace(':%S','',$format);
			if($date===2 || strcasecmp($date,'without_year')==0)
				$format = str_replace('%Y-','',$format);
			elseif($date===3 || strcasecmp($date,'with_weekday')==0)
				$format = '%a - '.$format;
		}

//		self::set_locale();
		if($tz)
			self::set_tz();
		$ret = self::strftime($format,$t);
//		self::restore_locale();
		if($tz)
			self::restore_tz();

		return $ret;
	}

	public static function strftime($format,$timestamp) {
		$ret = strftime($format,$timestamp);
		if ( strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ) {
		    $locale=setlocale(LC_ALL,"0");
			static $loc = null;
			if ($loc==null) {
				$loc=strtolower($locale);
				$loc=explode('_',$loc,2);
				$loc=$loc[0];
			}
		    if(isset(self::$encodings[$loc]))
    		 	return iconv(self::$encodings[$loc],'UTF-8',$ret);
    		return iconv('','UTF-8',$ret);
		}
		return $ret;
	}
	
	public static function set_locale() {
		self::$curr_locale = setlocale(LC_ALL,0);
		if (ModuleManager::is_installed('Base_Lang')!==-1) $lang_code = strtolower(Base_LangCommon::get_lang_code());
		else $lang_code = 'en';
		setlocale(LC_ALL,$lang_code.'_'.strtoupper($lang_code).'.utf8', //unixes
				$lang_code.'_'.strtoupper($lang_code).'.UTF-8',
				$lang_code.'.utf8',
				$lang_code.'.UTF-8',
				isset(self::$countries[$lang_code])?self::$countries[$lang_code]:null);//win32
		setlocale(LC_NUMERIC,'en_EN.utf8','en_EN.UTF-8','en_US.utf8','en_US.UTF-8','C','POSIX','en_EN','en_US','en','en.utf8','en.UTF-8','english');
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
		@setlocale(LC_ALL,self::$curr_locale);
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
	 * @param string date format
	 * @return int
	 */
	public static function reg2time($t,$tz=true,$datef = null) {
		if(!isset($datef))
			$datef = Base_User_SettingsCommon::get('Base_RegionalSettings','date');

		if(is_numeric($t)) $t = date('Y-m-d H:i:s',$t);

//		self::set_locale();
		if($tz)
			self::set_tz();
		static $dt_B;
		if(!isset($dt_B)) $dt_B = (strpos($datef,'%B')>=0);
		if($dt_B) {
			static $months_B;
			if(!isset($months_B)) {
				$months_B = array();
				for($i=1; $i<=12; $i++)
					$months_B[] = self::strftime('%B',strtotime($i.'/'.$i));
			}
			$t = str_replace($months_B,self::$months_en,$t);
		}

		static $dt_sb;
		if(!isset($dt_sb)) $dt_sb = (strpos($datef,'%b')>=0);
		if($dt_sb) {
			static $months_sb;
			if(!isset($months_sb)) {
				$months_sb = array();
				for($i=1; $i<=12; $i++)
					$months_sb[] = self::strftime('%b',strtotime($i.'/'.$i));
			}
			$t = str_replace($months_sb,self::$months_en_short,$t);
		}

		$tt = strtotime($t);
		if($tz)
			self::restore_tz();
//		self::restore_locale();
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
		return Base_User_SettingsCommon::get('Base_RegionalSettings','time');
	}

	/**
	 * Is user clock 12h?
	 *
	 * @return boolean
	 */
	public static function time_12h() {
		return '%I:%M:%S %p'==Base_User_SettingsCommon::get('Base_RegionalSettings','time');
	}

	 /**
     *
     * @convert seconds to hours and minutes
     * @param int $seconds The number of seconds
     * @return string for example 2 hour(s) 15 minutes (translated)
     *
     */
    public static function seconds_to_words($seconds,$days_h=true,$seconds_h=false)
    {
        /*** return value ***/
        $ret = "";

	if($days_h) {
	        /*** get the days and hours***/
	        $days = intval(intval($seconds) / (3600*24));
    		if($days > 0) {
            		$ret .= __('%s day(s)',array($days)).' ';
		}
		$hours = (intval($seconds) / 3600)%24;
	} else {
    		/*** get the hours without days***/
	        $hours = intval(intval($seconds) / 3600);
		$days = 0;
	}
        if($hours > 0) {
            $ret .= __('%s hour(s)',array($hours)).' ';
        }
        /*** get the minutes ***/
        $minutes = (intval($seconds) / 60)%60;
        if($minutes > 0)
        {
            $ret .= __('%s minutes',array($minutes)).' ';
        }
	if($seconds_h) {
	    $seconds = intval($seconds)%60;
            $ret .= __('%s seconds',array($seconds));
	}	
		return $ret;
        }


}

Base_RegionalSettingsCommon::set_locale();

?>
