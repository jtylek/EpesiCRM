<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-tests
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_Lightbox extends Module{
	public function body(){
		print('This is an example Lightbox page.<hr>');
		print('<a href="images/loader.gif" rel="lightbox">lightbox image</a><br>');
		print('<a rel="leightbox1" class="lbOn">leightbox container</a>
		<div id="leightbox1" class="leightbox">
			<h1>Leightbox</h1>
			ble ble ble
			<a href="#" class="lbAction" rel="deactivate">Close</a>
			</div>');

		//------------------------------ print out src
		print('<hr><b>Install</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/Lightbox/LightboxInstall.php');
		print('<hr><b>Init</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/Lightbox/LightboxInit_0.php');
		print('<hr><b>Main</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/Lightbox/Lightbox_0.php');
		print('<hr><b>Common</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/Lightbox/LightboxCommon_0.php');
		
	}
}

?>
