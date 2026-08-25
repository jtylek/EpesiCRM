<?php
/**
 * Simple RSS Feed applet
 * @author jtylek@telaxus.com
 * @copyright 2008 Janusz Tylek
 * @license MIT
 * @version 1.0
 * @package epesi-applets
 * @subpackage rssfeed
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_RssFeed extends Module {

	public function body(&$x) {
	}

	public function applet($values, & $opts) { //available applet options: toggle,href,title,go,go_function,go_arguments,go_contruct_arguments
		if (!$values['title']) {
			$values['title'] = __('RSS Feed');
		}
		$opts['title'] = $values['title'];

		$name = md5($this->get_path().$values['rssfeed']);

		//div for updating
		print('<div id="rssfeed_'.$name.'" style="width: 270px; padding: 5px 5px 5px 20px;">'.__('Loading RSS...').'</div>');

		//interval execution
		eval_js_once('var rssfeedcache = Array();'.
			'rssfeedfunc = function(name,fee,num,cache){'.
			'if(!document.getElementById(\'rssfeed_\'+name)) return;'.
			'if(cache && typeof rssfeedcache[name] != \'undefined\')'.
				'document.getElementById(\'rssfeed_\'+name).innerHTML = rssfeedcache[name];'.
			'else '.
				'jQuery(\'#rssfeed_\'+name).load(\'modules/Applets/RssFeed/refresh.php\','.
					'{feed:fee, number:num, cid: Epesi.client_id},'.
					'function(r){rssfeedcache[name]=r});'.
			'}');
		eval_js_once('setInterval(\'rssfeedfunc(\\\''.$name.'\\\',\\\''.Epesi::escapeJS($values['rssfeed'],false).'\\\' ,'.$values['rssnumber'].' , 0)\',1799993)'); //29 minutes and 53 seconds

		//get rss now!
		eval_js('rssfeedfunc(\''.$name.'\',\''.Epesi::escapeJS($values['rssfeed'],false).'\' ,'.$values['rssnumber'].' , 1)');
	}
}

?>
