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

class Applets_RssFeed extends Module {
	private $lang;
	
	public function construct() {
		$this->lang = $this->init_module('Base/Lang');
	}
	
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
			return null;

		return $title;
	}

	public function applet($values, $opts) { //available applet options: toggle,href,title,go,go_function,go_arguments,go_contruct_arguments
		$new_title = $this->get_page_title($values['rssfeed']);
		if($new_title!==null)
			$opts['title'] = substr($new_title,0,15).'...';
		
		$name = md5($this->get_path().$values['rssfeed']);

		//div for updating
		print('<div id="rssfeed_'.$name.'" style="padding-left: 20px">'.$this->lang->t('Loading RSS...').'</div>');
		
		//interval execution
		eval_js_once('rssfeedfunc_'.$name.' = function(){if(!$(\'rssfeed_'.$name.'\')) return;'.
			'new Ajax.Updater(\'rssfeed_'.$name.'\',\'modules/Applets/RssFeed/refresh.php\',{method:\'post\', parameters:{feed:\''.Epesi::escapeJS($values['rssfeed'],false).'\', number:\''.$values['rssnumber'].'\'}});'.
			'};'.
			'setInterval(\'rssfeedfunc_'.$name.'()\',1799993);'); //29 minutes and 53 seconds
		//get rss now!
		eval_js('rssfeedfunc_'.$name.'()');
	}
}

?>
