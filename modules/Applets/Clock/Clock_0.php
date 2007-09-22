<?php
/**
 * Flash clock.
 * (clock taken from http://www.kirupa.com/developer/actionscript/clock.htm)
 *
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package applets-clock
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_Clock extends Module {

	public function body() {
	
	}
	
	public function applet() {
		//clock taken from http://www.kirupa.com/developer/actionscript/clock.htm
		$clock = $this->get_module_dir().'clock.swf';
		print('<center><object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=6,0,29,0" height="200" width="200">'.
			'<param name="movie" value="'.$clock.'">'.
			'<param name="quality" value="high">'.
			'<param name="wmode" value="transparent">'.
			'<param name="menu" value="false">'.
			'<embed src="'.$clock.'" quality="high" pluginspage="http://www.macromedia.com/go/getflashplayer" type="application/x-shockwave-flash" wmode="transparent" height="200" width="200">'.
			'</object></center>');
	}

}

?>