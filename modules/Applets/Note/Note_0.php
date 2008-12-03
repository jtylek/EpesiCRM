<?php
/**
 * @author pbukowski@telaxus.com
 * @copyright 2008 Telaxus LLC
 * @license MIT
 * @version 1.1
 * @package epesi-applets
 * @subpackage note
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_Note extends Module {

	public function body() {

	}

	public function applet($values, $opts) { //available applet options: toggle,href,title,go,go_function,go_arguments,go_contruct_arguments
		Base_ThemeCommon::load_css($this->get_type());
		$opts['title'] = $values['title'];
		print ('<div id="Applets_Note"><div class="note-' . $values['bcolor'] . '">');
 		print ($values['text']);
		//print (str_replace("\n",'<br>',$values['text']));
		print ('</div></div>');
	}

}

?>
