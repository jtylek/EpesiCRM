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

	public function body() {
	}

	public function applet($values) {
		echo RSS_Display(($values['rssfeed']), ($values['rssnumber']));
	}
}

?>