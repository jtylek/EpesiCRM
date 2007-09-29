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

	public function applet($values, & $title) {
		$title = $values['title'];
		print(str_replace("\n",'<br>',strip_tags($values['text'])));
	}

}

?>