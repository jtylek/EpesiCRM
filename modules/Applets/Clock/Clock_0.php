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

	public function body($skin) {
		print('x<canvas id="'.$this->get_path().'canvas" class="CoolClock:'.$skin.':200"></canvas>x');
		eval_js('CoolClock.findAndCreateClocks()');
	}
	
	public function applet($conf, $opts) { //available applet options: toggle,href,title,go,go_function,go_arguments,go_contruct_arguments
		$opts['toggle'] = false;
		$opts['go'] = true;
		$opts['go_arguments'] = array($conf['skin']);
		load_js($this->get_module_dir().'excanvas.js');
		load_js($this->get_module_dir().'coolclock.js');
		print('<canvas id="'.$this->get_path().'canvas" class="CoolClock:'.$conf['skin'].'"></canvas>');
		eval_js('G_vmlCanvasManager_._init(document);');
		eval_js('CoolClock.findAndCreateClocks()');
	}

}

?>