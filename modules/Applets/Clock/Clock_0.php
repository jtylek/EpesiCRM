<?php
/**
 * Clock.
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
	
	public function applet($conf) {
		load_js($this->get_module_dir().'excanvas.js');
		load_js($this->get_module_dir().'coolclock.js');
		eval_js('CoolClock.findAndCreateClocks()');
		print('<canvas class="CoolClock:'.$conf['skin'].'"></canvas>');
	}

}

?>