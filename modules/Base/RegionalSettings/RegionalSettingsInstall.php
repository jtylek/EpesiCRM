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

class Base_RegionalSettingsInstall extends ModuleInstall {

	public function install() {
		Base_LangCommon::install_translations($this->get_type());
		Base_ThemeCommon::install_default_theme($this->get_type());
		return true;
	}

	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme($this->get_type());
		return true;
	}

	public function version() {
		return array("1.0");
	}

	public function requires($v) {
		return array(
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Base/Theme','version'=>0),
			array('name'=>'Data/Countries','version'=>0),
			array('name'=>'Base/Lang/Administrator','version'=>0),
			array('name'=>'Base/User/Settings','version'=>0));
	}

	public static function info() {
		return array(
			'Description'=>'Regional settings like currency, time...',
			'Author'=>'pbukowski@telaxus.com',
			'License'=>'MIT');
	}

	public static function simple_setup() {
		return false;
	}

	/////////////////////////////////////////////////////////////////////
	//post install

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


	public static function post_install() {
		$now = time();
		$date_formats_proto = array('%Y-%m-%d','%m/%d/%Y','%d %B %Y','%d %b %Y','%b %d, %Y');
		$date_formats = array();
		foreach($date_formats_proto as $f)
			$date_formats[$f] = strftime($f,$now);
		if(!function_exists('timezone_identifiers_list'))
			require_once('tz_list.php');
		$tz = timezone_identifiers_list();

		return array(
				array('type'=>'select','name'=>'date','label'=>'Date format',
					'default'=>'%m/%d/%Y','values'=>$date_formats),//strftime
				array('type'=>'select','name'=>'time','label'=>'Time format',
					'default'=>'%I:%M:%S %p','values'=>array('%I:%M:%S %p'=>'12h am/pm', '%H:%M:%S'=>'24h'),
					'rule'=>array('type'=>'callback',
						'func'=>array('Base_RegionalSettingsInstall','check_12h'),
						'message'=>'This language does not support 12h clock',
						'param'=>'__form__')
				),
				array('type'=>'select','name'=>'tz','label'=>'Timezone', 'default'=>SYSTEM_TIMEZONE, 'values'=>array_combine($tz,$tz)),
				array('type'=>'header','label'=>'Your location','name'=>null),
				array('name'=>'default_country', 'type'=>'callback','func'=>array('Base_RegionalSettingsCommon','default_country_elem'),'default'=>'US'),
				array('name'=>'default_state', 'type'=>'callback','func'=>array('Base_RegionalSettingsCommon','default_state_elem'),'default'=>'')
			);
	}

	public static function check_12h($v,$form) {
		$t = strtotime('2010-01-01 20:00');

		$curr_locale = setlocale(LC_TIME,0);
		$lang_code = Base_LangCommon::get_lang_code();
		setlocale(LC_TIME,$lang_code.'_'.strtoupper($lang_code).'.utf8', //unixes
				$lang_code.'_'.strtoupper($lang_code).'.UTF-8',
				$lang_code.'.utf8',
				$lang_code.'.UTF-8',
				isset(self::$countries[$lang_code])?self::$countries[$lang_code]:null);//win32

		$ret = ($t == strtotime('2010-01-01 '.strftime($v,$t)));
		setlocale(LC_TIME,$curr_locale);
		return $ret;
	}

	public static function post_install_process($val) {
		Base_User_SettingsCommon::save_admin('Base_RegionalSettings','date',$val['date']);
		Base_User_SettingsCommon::save_admin('Base_RegionalSettings','time',$val['time']);
		Base_User_SettingsCommon::save_admin('Base_RegionalSettings','tz',$val['tz']);
		Base_User_SettingsCommon::save_admin('Base_RegionalSettings','default_country',$val['default_country']);
		Base_User_SettingsCommon::save_admin('Base_RegionalSettings','default_state',isset($val['default_state'])?$val['default_state']:'');
	}
}

?>
