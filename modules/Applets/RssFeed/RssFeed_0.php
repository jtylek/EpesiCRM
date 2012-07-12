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
			'if(!$(\'rssfeed_\'+name)) return;'.
			'if(cache && typeof rssfeedcache[name] != \'undefined\')'.
				'$(\'rssfeed_\'+name).innerHTML = rssfeedcache[name];'.
			'else '.
				'new Ajax.Updater(\'rssfeed_\'+name,\'modules/Applets/RssFeed/refresh.php\',{'.
					'method:\'post\','.
					'onComplete:function(r){rssfeedcache[name]=r.responseText},'.
					'parameters:{feed:fee, number:num, cid: Epesi.client_id}});'.
			'}');
		eval_js_once('setInterval(\'rssfeedfunc(\\\''.$name.'\\\',\\\''.Epesi::escapeJS($values['rssfeed'],false).'\\\' ,'.$values['rssnumber'].' , 0)\',1799993)'); //29 minutes and 53 seconds

		//get rss now!
		eval_js('rssfeedfunc(\''.$name.'\',\''.Epesi::escapeJS($values['rssfeed'],false).'\' ,'.$values['rssnumber'].' , 1)');
	}
}

?>
