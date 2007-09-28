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

class Applets_rssfeedCommon extends ModuleCommon {
	public static function applet_caption() {
		return "RSS Feed";
	}

	public static function applet_info() {
		return "Simple RSS Feed"; //here can be associative array
	}

	public static function applet_settings() {
		return array(
			array('name'=>'rssfeed','label'=>'RSS Feed','type'=>'text','default'=>'http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/technology/rss.xml','rule'=>array(array('message'=>'Field required', 'type'=>'required'))),
			array('name'=>'rssnumber','label'=>'Number of news','type'=>'text','default'=>'5','rule'=>array(array('message'=>'Field required', 'type'=>'required')))
			);
	}

}

?>
