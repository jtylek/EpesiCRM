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

		$image = '';

		if($values['bcolor'] == 'gradient') {
			// 							do poprawy     v
			$image = 'background-image: url(data/Base_Theme/templates/default/images/note-background.png); background-repeat: repeat-x;';
			$values['bcolor'] = 'white';
		}

		if($values['bcolor'] == 'gradient2') {
			// 							do poprawy     v
			$image = 'background-image: url(data/Base_Theme/templates/default/images/note-background-2.png); background-repeat: repeat-x;';
			$values['bcolor'] = '#e5e562';
		}

		print ('<div style="color: black; background: '.$values['bcolor'].'; ' . $image . ' padding: .5em;">');
 		print (str_replace("\n",'<br>',$values['text']));
		print ('</div>');
	}

}

?>
