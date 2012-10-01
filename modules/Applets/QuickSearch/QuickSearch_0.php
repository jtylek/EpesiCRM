<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_QuickSearch extends Module{
	
	public function body(){
	
	}
	
	public function applet($conf, & $opts){		
		$opts['go' ] = false;	
		$opts['title'] = $opts['title'].' '.__('by').' '.$conf['criteria'];
		$theme = $this->init_module('Base/Theme');
		$form = $this->init_module('Libs/QuickForm');
		
		$txtQuery = 'query_text';
		$txtLabel = 'query_label';
		$btnQuery = 'query_button';
		$placeholder = "";
		switch(strtoupper($conf['criteria'])){
			case "PHONE":
				$placeholder = "Enter a Work/Mobile/Fax phone number here...";	
				break;
			case "EMAIL":
				$placeholder = "Enter an Email address here...";			
				break;
			case "CITY":
				$placeholder = "Enter a City name here...";			
				break;
			case "NAMES":
				$placeholder = "Enter a First/Last/Company/Short name here...";		
				break;
			default:	
				$placeholder = "Enter a First/Last/Company/Short name here...";		
				break;		
		}
		
		load_css('modules/Applets/QuickSearch/theme/quick_form.css');
		load_js('modules/Applets/QuickSearch/js/quicksearch.js');	
		
		$js ='setDelayOnSearch()';
		eval_js($js);
		$txt = $form->addElement('text', $txtQuery, __('Search'));		
		$txt->setAttribute('id', $txtQuery);
		$txt->setAttribute('class', 'QuickSearch_text');
		$txt->setAttribute('onkeypress', 'setDelayOnSearch(\''.trim($conf['criteria']).'\')');				
		$txt->setAttribute('placeholder', _V($placeholder));
		
		$theme->assign($txtLabel, __('Search'));
		$theme->assign($txtQuery, $txt->toHtml());
		$theme->display('quick_form');					
	
	}
	public function caption() {
		return __('QuickSearch');
	}	

}

?>