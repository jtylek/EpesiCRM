<?php
/**
 * Fancy statusbar.
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage statusbar
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_StatusBar extends Module {

	public function body() {
		$theme = & $this->init_module("Base/Theme");
		$theme->assign('statusbar_id','Base_StatusBar');
		$theme->assign('text_id','statusbar_text');
		$theme->display();
		$this->load_js();
		on_exit(array($this, 'messages'),null,false);
	}

	public function messages() {
		eval_js("statusbar_message('".Epesi::escapeJS(implode('<br>',Base_StatusBarCommon::$messages),false)."')");
	}

	private function load_js() {
		eval_js_once('var statusbar_message_t=\'\';' .
				'statusbar_message=function(text){statusbar_message_t=text;};' .
				'statusbar_fade=function(){'.
					'wait_while_null(\'$(\\\'Base_StatusBar\\\')\',\'$(\\\'Base_StatusBar\\\').style.display=\\\'none\\\';\');'.
					'statusbar_hide_selects(\'visible\');'.
				'};'.
				'statusbar_hide_selects=function(visibility){'.
					'if(navigator.userAgent.toLowerCase().indexOf(\'msie\')>=0){'.
					'selects = document.getElementsByTagName(\'select\');'.
					'for(i = 0; i < selects.length; i++) {'.
						'selects[i].style.visibility = visibility;'.
					'}}'.
				'};' .
				'updateEpesiIndicatorFunction=function(){' .
					'Epesi.indicator_text=\'statusbar_text\';' .
					'Epesi.indicator=\'Base_StatusBar\';' .
					'$(\'epesiStatus\').style.visibility=\'hidden\';' .
					'statbar = $(\'Base_StatusBar\');' .
					'statbar.onclick = Function("if(!Epesi.procOn)statusbar_fade();");' .
					'statbar.style.display=\'none\';' .
					'Epesi.updateIndicator=function(){' .
						'statbar = $(\'Base_StatusBar\');' .
						'if(Epesi.procOn){' .
							'statbar.style.display=\'block\';'.
							'cache_pause=true;'.
							'statusbar_hide_selects(\'hidden\');'.
						'}else{' .
							'if(statusbar_message_t!=\'\') {' .
								't=$(\'statusbar_text\');' .
								'if(t)t.innerHTML=statusbar_message_t;' .
								'statusbar_message(\'\');' .
								'setTimeout(\'statusbar_fade()\',1000);' .
							'}else{' .
								'statusbar_fade();' .
							'};'.
							'cache_pause=false;' .
						'};' .
					'};' .
				'};' .
				'updateEpesiIndicatorFunction()');
	}
}
?>
