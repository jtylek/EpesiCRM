<?php
/**
 * Flash clock
 * (clock taken from http://www.kirupa.com/developer/actionscript/clock.htm)
 *
 * @author pbukowski@telaxus.com
 * @copyright 2008 Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-applets
 * @subpackage clock
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_Clock extends Module {
	
	public function body($skin, $size=200, $conf = []) {
		print('<center'.($skin=='chunkySwissOnBlack'?' style="background-color:black; color:white;"':'').'>');
		$browser = stripos($_SERVER['HTTP_USER_AGENT'],'msie');
		if($browser!==false || $skin=='flash') {
			$size *= 2;
			//clock taken from http://www.kirupa.com/developer/actionscript/clock.htm
			$clock = $this->get_module_dir().'clock.swf';
			print('<center><object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=6,0,29,0" height="'.$size.'" width="'.$size.'">'.
				'<param name="movie" value="'.$clock.'">'.
				'<param name="quality" value="high">'.
				'<param name="wmode" value="transparent">'.
				'<param name="menu" value="false">'.
				'<embed src="'.$clock.'" quality="high" pluginspage="http://www.macromedia.com/go/getflashplayer" type="application/x-shockwave-flash" wmode="transparent" height="'.$size.'" width="'.$size.'">'.
				'</object></center>');
		} else {
			load_js($this->get_module_dir().'coolclock.js');
			eval_js('CoolClock.findAndCreateClocks()');
			
			if ($conf && $conf['type'] == 'double') {
				$timezone = $conf['second_clock_timezone'] ?: '0.0';
				$label = $conf['second_clock_label'] ?: Applets_ClockCommon::get_timezones()[$timezone];
				$offset = $timezone * 60 * 60;
				
				print('<table style="width: 100%"><tr><td style="width: 100px;text-align:center;"><canvas id="' . $this->get_path() . '1_canvas" class="CoolClock:' . $skin . ':' . $size . '"></canvas>');
				print('<br>Local Time<br><span class="local_time">' . Base_RegionalSettingsCommon::time2reg(null, false) . '</span></td>');
				print('<td style="width: 100px;text-align:center;"><canvas id="' . $this->get_path() . '2_canvas" class="CoolClock:' . $skin . ':' . $size . ':noSeconds:' . $timezone . '"></canvas>');
				print('<br>' . $label . '<br>' . gmdate('d F Y', time() + $offset) . '</td></tr></table>');
				eval_js('jq(".local_time").html(function() {return jq.datepicker.formatDate("d MM yy", new Date());});');
				
				print('</center>');
				return;
			}
			else 
				print('<canvas id="' . $this->get_path() . 'canvas" class="CoolClock:' . $skin . ':' . $size . '"></canvas>');
		}
		print('<BR>'.Base_RegionalSettingsCommon::time2reg(null,false).'</center>');
	}
	
	public function applet($conf, & $opts) { //available applet options: toggle,href,title,go,go_function,go_arguments,go_contruct_arguments
		$opts['toggle'] = false;
		$opts['go'] = true;
		$skin = isset($conf['skin'])?$conf['skin']:null;
		$opts['go_arguments'] = array($skin);
		
		$size = $conf['type'] == 'double'? 60: 100;
		
		$this->body($skin, $size, $conf);
	}

}

?>