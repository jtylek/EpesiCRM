<?php
/**
 * @author j@epe.si
 * @copyright 2009 Janusz Tylek
 * @license MIT
 * @version 0.1
 * @package epesi-applets
 * @subpackage
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_WeatherCommon extends ModuleCommon {
	private static $url;

	public static function applet_caption() {
		return __('Weather');
	}

	public static function applet_info() {
		return __('Simple Weather applet'); //here can be associative array
	}

	public static function applet_settings() {
		return array(
			array('name'=>'rssfeed','label'=>__('Weather service page'),'type'=>'text','default'=>'http://weather.yahooapis.com/forecastrss',
				'rule'=>array(
					array('message'=>__('Field required'), 'type'=>'required'),
					array('message'=>__('Invalid RSS Feed'), 'type'=>'callback', 'func'=>array('Applets_WeatherCommon','check_feed')),
					array('message'=>__('Invalid address'),'type'=>'regex','param'=>'/^http(s)?:\/\//')
					),
				'filter'=>array(array('Applets_WeatherCommon','set_url'))
			),
			array('name'=>'zipcode','label'=>__('Enter City and State or Zip Code'),'type'=>'text','default'=>'19063','rule'=>array(array('message'=>__('Field required'), 'type'=>'required'))),
			array('name'=>'temperature','label'=>__('Fahrenheit / Celsius'),'type'=>'select','default'=>'f','rule'=>array(array('message'=>__('Field required'), 'type'=>'required')), 'values'=>array('f' => '&deg;F', 'c'=>'&deg;C'))
		);
	}

	public static function check_feed($feed) {
		if(!function_exists('curl_init')) {
			//take care about it later...
			return true;
		}
		// Check if RSS can be read
		$rss = curl_init();
		curl_setopt($rss, CURLOPT_URL, $feed);
		curl_setopt($rss, CURLOPT_RETURNTRANSFER, true);
		$output = curl_exec($rss);
		$response_code = curl_getinfo($rss, CURLINFO_HTTP_CODE);

		if ($response_code == '404')
			return false;

		return true;
	}

	public static function set_url($feed) {
		self::$url = $feed;
		return $feed;
	}

	public static function get_title($t) {
		if($t!='') return $t;
		$html = @file_get_contents(self::$url);
		if(!$html) return '';
		$matches = array();
		preg_match('/<title>([^<]*)</i', $html, $matches);

		$title = $matches[1];
		if(!$title)
			return 'Weather';

		return substr($title,0,15).'...';
	}
}

?>
