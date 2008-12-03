<?php
/**
 * @author jtylek@telaxus.com
 * @copyright 2008 Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-applets
 * @subpackage gtalk
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_GTalk extends Module {
	
	public function body() {
	}

	public function applet($conf, $opts) { //available applet options: toggle,href,title,go,go_function,go_arguments,go_contruct_arguments
		$opts['toggle'] = false;
		$opts['go'] = false;
		$gtalk='<iframe src="http://talkgadget.google.com/talkgadget/client?frameborder="0" style="overflow:hidden; width: 300px; height: 300px;">';
		print($gtalk);
	}
}

?>
