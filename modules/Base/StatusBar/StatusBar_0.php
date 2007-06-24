<?php
/**
 * Fancy statusbar.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * @package epesi-base-extra
 * @subpackage statusbar
 */
class Base_StatusBar extends Module {
	
	public function body() {
		$this->load_js();
		$theme = & $this->pack_module("Base/Theme");
		$theme->assign('statusbar_id','Base_StatusBar');
		$theme->assign('text_id','statusbar_text');
		$theme->display();
		on_exit(array($this, 'messages'),null,false);
	}
	
	public function messages() {
		eval_js("wait_while_null('statusbar_message','statusbar_message(\'".addslashes(escapeJS(implode('<br>',Base_StatusBarCommon::$messages)))."\')')");
	}
	
	private function load_js() {
		eval_js_once('var statusbar_message_t=\'\';' .
				'statusbar_message=function(text){statusbar_message_t=text;};' .
				'statusbar_fade=function(){'.
					'wait_while_null(\'$(\\\'Base_StatusBar\\\')\',\'$(\\\'Base_StatusBar\\\').style.display=\\\'none\\\';\');'.
				'};' .				
				'updateSajaIndicatorFunction=function(){' .
					'saja.indicator=\'statusbar_text\';' .
					'$(\'sajaStatus\').style.visibility=\'hidden\';' .
					'statbar = $(\'Base_StatusBar\');' .
					'statbar.onclick = Function("if(!saja.procOn)statusbar_fade();");' .
					'statbar.style.display=\'none\';' .
					'saja.updateIndicator=function(){' .
						'statbar = $(\'Base_StatusBar\');' .
						'if(saja.procOn){' .
							'statbar.style.display=\'block\';' .
						'}else{' .
							'if(statusbar_message_t!=\'\') {' .
								't=$(\'statusbar_text\');' .
								'if(t)t.innerHTML=statusbar_message_t;' .
								'statusbar_message(\'\');' .
								'setTimeout(\'statusbar_fade()\',3000);' .
							'}else{' .
								'statusbar_fade();' .
							'};' .
						'};' .
					'};' .
				'};' .
				'wait_while_null(\'Effect\',\'updateSajaIndicatorFunction()\')');
	}
}
?>
