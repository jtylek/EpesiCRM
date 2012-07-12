<?php
/**
 * @author msteczkiewicz@telaxus.com
 * @copyright 2009 Telaxus LLC
 * @license MIT
 * @version 0.1
 * @package epesi-applets
 * @subpackage
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_Weather extends Module {

	public function body() {
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

	public function applet($values, & $opts) { //available applet options: toggle,href,title,go,go_function,go_arguments,go_contruct_arguments
		Base_ThemeCommon::load_css('Applets_Weather');

		$opts['title'] = __('Weather');

		$rssfeed = $values['rssfeed'] . '?p=' . $values['zipcode'] . '&u=' . $values['temperature'];
		$name = md5($this->get_path().$rssfeed);

		//div for updating
		print('<div id="Applets_Weather"><div id="rssfeed_'.$name.'"><span>'.__('Loading Weather...').'</span></div></div>');

		//interval execution
		eval_js_once('var rssfeedcache = Array();'.
			'rssfeedfunc = function(name,fee,num,cache){'.
			'if(!$(\'rssfeed_\'+name)) return;'.
			'if(cache && typeof rssfeedcache[name] != \'undefined\')'.
				'$(\'rssfeed_\'+name).innerHTML = rssfeedcache[name];'.
			'else '.
				'new Ajax.Updater(\'rssfeed_\'+name,\'modules/Applets/Weather/refresh.php\',{'.
					'method:\'post\','.
					'onComplete:function(r){rssfeedcache[name]=r.responseText},'.
					'parameters:{feed:fee, number:num, cid: Epesi.client_id}});'.
			'}');
		eval_js_once('setInterval(\'rssfeedfunc(\\\''.$name.'\\\',\\\''.Epesi::escapeJS($rssfeed, false).'\\\' , 2 , 0)\',1799993)'); //29 minutes and 53 seconds

		//get rss now!
		eval_js('rssfeedfunc(\''.$name.'\',\''.Epesi::escapeJS($rssfeed, false).'\' , 2 , 1)');
	}

}

?>
