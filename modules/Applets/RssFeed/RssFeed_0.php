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

require_once("rsslib.php");

class Applets_rssfeed extends Module {

	public function body(&$x) {
	}

	private function get_page_title($url){
		$html = @file_get_contents($url);
		if(!$html)
			return null;
		$matches = array();
		preg_match('/<title>([^<]*)</i', $html, $matches);

		$title = $matches[1];
		if(!$title)
		return "No title was found on this page.";

		return $title;
	}
	
	public function applet($values, & $title) {
		if(isset($values['rssfeed'])) {
			$new_title = $this->get_page_title($values['rssfeed']);
			if($new_title!==null)
				$title = $new_title;
			echo RSS_Display(($values['rssfeed']), ($values['rssnumber']));
		} else print('RSS feed not defined');
	}
}

?>