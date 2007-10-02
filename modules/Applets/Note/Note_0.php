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

	print ('
	<style type="text/css">
	.postit {
	border: 1px solid #FFFF00;
	}
	.postit div {
	color: black;
	background: #FFFF00;
	padding: .5em;
	position: relative;
	}
	</style>');
	
	print ('<div class="postit">');
 	print ('<div>');
 	print (str_replace("\n",'<br>',$values['text']));
 	print ('</div>');
	}

}

?>