<?php
/**
 * Simple RSS Feed applet
 * @author jtylek@gmail.com
 * @copyright jtylek@gmail.com
 * @license SPL
 * @version 0.2
 * @package applets-RSS_Feed
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_RssFeedCommon extends ModuleCommon {
	public static function applet_caption() {
		return "RSS Feed";
	}

	public static function applet_info() {
		return "Simple RSS Feed"; //here can be associative array
	}

	public static function applet_settings() {
		return array(
//			array('name'=>'title','label'=>'Title','type'=>'text','default'=>'RSS Feed','rule'=>array(array('message'=>'Field required', 'type'=>'required'))),
			array('name'=>'rssfeed','label'=>'RSS Feed','type'=>'text','default'=>'http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/technology/rss.xml',
				'rule'=>array(
					array('message'=>'Field required', 'type'=>'required'),
					array('message'=>'Invalid RSS feed', 'type'=>'callback', 'func'=>array('Applets_RssFeedCommon','check_feed'),'param'=>'__form__'),
					array('message'=>'Invalid address','type'=>'regex','param'=>'/^http(s)?:\/\//')
					)
				),
			array('name'=>'rssnumber','label'=>'Number of news','type'=>'text','default'=>'5','rule'=>array(array('message'=>'Field required', 'type'=>'required')))
			);
	}
	
	public static function check_feed($feed) {
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

}

?>
