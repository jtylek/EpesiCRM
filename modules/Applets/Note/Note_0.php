<?php
/**
 * 
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package applets-note
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_Note extends Module {

	public function body() {
		
	}

	public function applet($values, $opts) { //available applet options: toggle,href,title,go,go_function,go_arguments,go_contruct_arguments
		$opts['title'] = $values['title'];

		print ('<div style="color: black; background: '.$values['bcolor'].'; padding: .5em; position: relative;">');
 		print (str_replace("\n",'<br>',$values['text']));
		print ('</div>');
	}

}

?>