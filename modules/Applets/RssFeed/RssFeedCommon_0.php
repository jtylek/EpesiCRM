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
	private static $feed;
	
	public static function applet_caption() {
		return __('RSS Feed');
	}

	public static function applet_info() {
		return __('Simple RSS Feed'); //here can be associative array
	}

	public static function applet_settings() {
		return array(
			array('name'=>'rssfeed','label'=>__('RSS Feed'),'type'=>'text','default'=>'http://newsrss.bbc.co.uk/rss/newsonline_world_edition/front_page/rss.xml',
				'rule'=>array(
					array('message'=>__('Field required'), 'type'=>'required'),
					array('message'=>__('Invalid RSS Feed or server has disabled url_fopen support'), 'type'=>'callback', 'func'=>array('Applets_RssFeedCommon','check_feed')),
					array('message'=>__('Invalid address'),'type'=>'regex','param'=>'/^http(s)?:\/\//')
					),
				'filter'=>array(array('Applets_RssFeedCommon','set_url'))
			),
			array('name'=>'rssnumber','label'=>__('Number of news'),'type'=>'text','default'=>'5','rule'=>array(array('message'=>'Field required', 'type'=>'required'))),
			array('name'=>'title','label'=>__('Title (leave empty for RSS Feed value)'),'type'=>'text','default'=>'',
				'filter'=>array(
					array('Applets_RssFeedCommon','get_title')
				)
			)
		);
	}
	
	public static function check_feed($feed) {
		return (self::$feed!=false);
	}
	
	public static function set_url($feed) {
		self::$feed = @file_get_contents($feed);
		return $feed;
	}

	public static function get_title($t='') {
		if($t!='') return $t;
		if(self::$feed==false)
			return '';
			
		$matches = array();
		preg_match('/<title>([^<]*)</i', self::$feed, $matches);

		$title = $matches[1];
		if(!$title)
			return 'RSS Feed';

		if (mb_strlen($title,'UTF-8')>15) return mb_substr($title,0,15,'UTF-8').'...';
		return $title;
	}

}

?>
