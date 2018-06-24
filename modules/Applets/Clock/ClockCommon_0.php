<?php
/**
 * Flash clock
 * (clock taken from http://www.kirupa.com/developer/actionscript/clock.htm)
 *
 * @author pbukowski@telaxus.com
 * @copyright 2008 Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-applets
 * @subpackage clock
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_ClockCommon extends ModuleCommon {
	public static function get_timezones() {
		return array(
				'-12.0' => __('(GMT-12:00) Eniwetok, Kwajalein'),
				'-11.0' => __('(GMT-11:00) Midway Island, Samoa'),
				'-10.0' => __('(GMT-10:00) Hawaii'),
				'-9.0' => __('(GMT-9:00) Alaska'),
				'-8.0' => __('(GMT-8:00) Pacific Time (US &amp; Canada)'),
				'-7.0' => __('(GMT-7:00) Mountain Time (US &amp; Canada)'),
				'-6.0' => __('(GMT-6:00) Central Time (US &amp; Canada), Mexico City'),
				'-5.0' => __('(GMT-5:00) Eastern Time (US &amp; Canada), Bogota, Lima'),
				'-4.0' => __('(GMT-4:00) Atlantic Time (Canada), Caracas, La Paz'),
				'-3.5' => __('(GMT-3:30) Newfoundland'),
				'-3.0' => __('(GMT-3:00) Brazil, Buenos Aires, Georgetown'),
				'-2.0' => __('(GMT-2:00) Mid-Atlantic'),
				'-1.0' => __('(GMT-1:00 hour) Azores, Cape Verde Islands'),
				'0.0' => __('(GMT) Western Europe Time, London, Lisbon, Casablanca'),
				'1.0' => __('(GMT+1:00 hour) Hamburg, Berlin, Brussels, Madrid, Paris'),
				'2.0' => __('(GMT+2:00) Kaliningrad, South Africa'),
				'3.0' => __('(GMT+3:00) Baghdad, Riyadh, Moscow, St. Petersburg'),
				'3.5' => __('(GMT+3:30) Tehran'),
				'4.0' => __('(GMT+4:00) Abu Dhabi, Muscat, Baku, Tbilisi'),
				'4.5' => __('(GMT+4:30) Kabul'),
				'5.0' => __('(GMT+5:00) Ekaterinburg, Islamabad, Karachi, Tashkent'),
				'5.5' => __('(GMT+5:30) Bombay, Calcutta, Madras, New Delhi'),
				'5.75' => __('(GMT+5:45) Kathmandu'),
				'6.0' => __('(GMT+6:00) Almaty, Dhaka, Colombo'),
				'7.0' => __('(GMT+7:00) Bangkok, Hanoi, Jakarta'),
				'8.0' => __('(GMT+8:00) Beijing, Perth, Singapore, Hong Kong'),
				'9.0' => __('(GMT+9:00) Tokyo, Seoul, Osaka, Sapporo, Yakutsk'),
				'9.5' => __('(GMT+9:30) Adelaide, Darwin'),
				'10.0' => __('(GMT+10:00) Eastern Australia, Guam, Vladivostok'),
				'11.0' => __('(GMT+11:00) Magadan, Solomon Islands, New Caledonia'),
				'12.0' => __('(GMT+12:00) Auckland, Wellington, Fiji, Kamchatka')
		);
	}
	
	public static function applet_caption() {
		return __('Clock');
	}

	public static function applet_info() {
		return __('Analog JS clock'); //here can be associative array
	}

	public static function applet_settings() {
		$browser = stripos($_SERVER['HTTP_USER_AGENT'], 'msie');
		if ($browser !== false) return array(
				array(
						'name' => 'skin',
						'label' => __('Clock configurable on non-IE browsers only.'),
						'type' => 'static',
						'values' => ''
				)
		);
		else {
			$hide_mapping = array(
					array(
							'values' => 'single',
							'mode' => 'hide',
							'fields' => array(
									'second_clock_timezone',
									'second_clock_label'
							)
					)
			);
			
			Libs_QuickFormCommon::autohide_fields('type', $hide_mapping);
			
			return array(					
					array(
							'name' => 'skin',
							'label' => __('Clock skin'),
							'type' => 'select',
							'default' => 'swissRail',
							'rule' => array(
									array(
											'message' => __('Field required'),
											'type' => 'required'
									)
							),
							'values' => array(
									'swissRail' => 'swissRail',
									'chunkySwiss' => 'chunkySwiss',
									'chunkySwissOnBlack' => 'chunkySwissOnBlack',
									'fancy' => 'fancy',
									'machine' => 'machine',
									'classic' => 'classic',
									'modern' => 'modern',
									'simple' => 'simple',
									'securephp' => 'securephp',
									'Tes2' => 'Tes2',
									'Lev' => 'Lev',
									'Sand' => 'Sand',
									'Sun' => 'Sun',
									'Tor' => 'Tor',
									'Babosa' => 'Babosa',
									'Tumb' => 'Tumb',
									'Stone' => 'Stone',
									'Disc' => 'Disc',
									'flash' => 'flash'
							)
					),
					array(
							'name' => 'type',
							'label' => __('Type'),
							'type' => 'select',
							'default' => 'single',
							'values' => array(
									'single' => __('Single Clock'),
									'double' => __('Double Clock')
							),
							'param' => array(
									'id' => 'type'
							)
					),
					array(
							'name' => 'second_clock_timezone',
							'label' => __('Second clock timezone'),
							'type' => 'select',
							'default' => '8.0',
							'values' => self::get_timezones(),
							'param' => array(
									'id' => 'second_clock_timezone'
							)
					),
					array(
							'name' => 'second_clock_label',
							'label' => __('Second clock label'),
							'type' => 'text',
							'default' => __('Singapore / China'),
							'param' => array(
									'id' => 'second_clock_label'
							)
					)
			);
		}
	}	
}

?>