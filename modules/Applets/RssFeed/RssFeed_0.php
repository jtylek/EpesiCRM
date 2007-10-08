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

	private function get_page_title($url) {
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

	public function applet($values, $opts) { //available applet options: toggle,href,title,go,go_function,go_arguments,go_contruct_arguments
		if(isset($values['rssfeed'])) {
			$new_title = $this->get_page_title($values['rssfeed']);
			if($new_title!==null)
				$opts['title'] = substr($new_title,0,15).'...';
			
		// Check if RSS can be read
		$rss = curl_init();
		curl_setopt($rss, CURLOPT_URL, $values['rssfeed']);
		curl_setopt($rss, CURLOPT_RETURNTRANSFER, true);
		$output = curl_exec($rss);
		$response_code = curl_getinfo($rss, CURLINFO_HTTP_CODE);
				
		if ($response_code == '404') {
    		echo "Error: ".$response_code."<BR>";
			echo $values['rssfeed'];
			echo ("<BR>Invalid RSS feed.<BR>Please check your configuration.<BR>");
		} else {
        	echo RSS_Display(($values['rssfeed']), ($values['rssnumber']));
		}
		
		} else print('RSS feed not defined');
	}
}

?>
