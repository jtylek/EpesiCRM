<?php
/**
 * Simple RSS Feed applet
 * @author jtylek@telaxus.com
 * @copyright 2008 Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-applets
 * @subpackage rssfeed
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_RssFeedCommon extends ModuleCommon {
	private static $url;
	
	public static function applet_caption() {
		return "RSS Feed";
	}

	public static function applet_info() {
		return "Simple RSS Feed"; //here can be associative array
	}

	public static function applet_settings() {
		return array(
			array('name'=>'rssfeed','label'=>'RSS Feed','type'=>'text','default'=>'http://newsrss.bbc.co.uk/rss/newsonline_world_edition/front_page/rss.xml',
				'rule'=>array(
					array('message'=>'Field required', 'type'=>'required'),
					array('message'=>'Invalid RSS feed', 'type'=>'callback', 'func'=>array('Applets_RssFeedCommon','check_feed')),
					array('message'=>'Invalid address','type'=>'regex','param'=>'/^http(s)?:\/\//')
					),
				'filter'=>array(array('Applets_RssFeedCommon','set_url'))
			),
			array('name'=>'rssnumber','label'=>'Number of news','type'=>'text','default'=>'5','rule'=>array(array('message'=>'Field required', 'type'=>'required'))),
			array('name'=>'title','label'=>'Title (leave empty for RSS feed value)','type'=>'text','default'=>'',
				'filter'=>array(
					array('Applets_RssFeedCommon','get_title')
				)
			)
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
			return 'RSS Feed';

		return substr($title,0,15).'...';
	}

}

?>
