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
	
	private function load_js() {
		load_js($this->get_module_dir().'coolclock.js');
		eval_js('CoolClock.findAndCreateClocks()');
	}

	public function body($skin) {
		$this->load_js();
		print('<canvas id="'.$this->get_path().'canvas" class="CoolClock:'.$skin.':200"></canvas>');
	}
	
	public function applet($conf, $opts) { //available applet options: toggle,href,title,go,go_function,go_arguments,go_contruct_arguments
		$opts['toggle'] = false;
		$opts['go'] = true;
		$opts['go_arguments'] = array($conf['skin']);
		$this->load_js();
		print('<canvas id="'.$this->get_path().'canvas" class="CoolClock:'.$conf['skin'].'"></canvas>');
	}

}

?>