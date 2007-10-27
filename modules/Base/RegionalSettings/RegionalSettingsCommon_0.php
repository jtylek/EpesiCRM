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
				array('type'=>'select','name'=>'tz','label'=>'Timezone', 'default'=>SYSTEM_TIMEZONE, 'values'=>array_combine($tz,$tz))
			));
	}
	
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
		
		setlocale(LC_TIME,self::$curr_locale);

		return $ret;
	}

	public static function time2reg($t=null,$time=true) {
		if(!isset($t)) $t = time();
		elseif(!is_integer($t)) $t = strtotime($t);
		$datef = Base_User_SettingsCommon::get('Base_RegionalSettings','date');
		$timef = Base_User_SettingsCommon::get('Base_RegionalSettings','time');
		
		self::set_locale();
		$ret = self::strftime($datef.($time?' '.$timef:''),$t);
		self::restore_locale();
		return $ret;
	}
	
	private static function strftime($format,$timestamp) {
		$ret = strftime($format,$timestamp);
		if ( strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' )
		 	return iconv('','UTF-8',$ret);
		return $ret;
	}
	
	private static function set_locale() {
		self::$curr_locale = setlocale(LC_TIME,0);
		$lang_code = strtolower(Base_LangCommon::get_lang_code());
		setlocale(LC_TIME,$lang_code.'_'.strtoupper($lang_code).'.utf8', //unixes
				$lang_code.'_'.strtoupper($lang_code).'.UTF-8',
				$lang_code.'.utf8',
				$lang_code.'.UTF-8',
				isset(self::$countries[$lang_code])?self::$countries[$lang_code]:null);//win32
	}
	
	private static function restore_locale() {
		setlocale(LC_TIME,self::$curr_locale);
	}

	public static function reg2time($t) {
		$datef = Base_User_SettingsCommon::get('Base_RegionalSettings','date');

		if(strpos($datef,'%B')>=0) {
			$months = array();
			self::set_locale();
			for($i=1; $i<=12; $i++)
				$months[] = self::strftime('%B',strtotime($i.'/'.$i));
			self::restore_locale();
			$t = str_replace($months,self::$months_en,$t);
		}

		if(strpos($datef,'%b')>=0) {
			$months = array();
			self::set_locale();
			for($i=1; $i<=12; $i++)
				$months[] = self::strftime('%b',strtotime($i.'/'.$i));
			self::restore_locale();
			$t = str_replace($months,self::$months_en_short,$t);
		}

		return strtotime($t);
	}
	
	public static function server_time($t) {
		$t = strtotime($t);
		$curr_tz = date_default_timezone_get();
		date_default_timezone_set(SYSTEM_TIMEZONE);
		$ret = DB::BindTimeStamp($t);
		date_default_timezone_set($curr_tz);
		return $ret;
	}

	public static function server_date($t) {
		$t = strtotime($t);
		$curr_tz = date_default_timezone_get();
		date_default_timezone_set(SYSTEM_TIMEZONE);
		$ret = DB::BindDate($t);
		date_default_timezone_set($curr_tz);
		return $ret;
	}
}

if(Acl::is_user())
	date_default_timezone_set(Base_User_SettingsCommon::get('Base_RegionalSettings','tz'));

?>
