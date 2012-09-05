<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_QuickSearch extends Module{
	
	public function body(){
	
	}
	
	public function applet($values, & $opts){		
		$opts['go' ] = false;		
		$theme = $this->init_module('Base/Theme');
		$form = $this->init_module('Libs/QuickForm');
		
		$txtQuery = 'query_text';
		$txtLabel = 'query_label';
		$btnQuery = 'query_button';
		
		load_css('modules/Applets/QuickSearch/theme/quick_form.css');
		load_js('modules/Applets/QuickSearch/js/quicksearch.js');	
		
		$js ='setDelayOnSearch()';
		eval_js($js);
		$txt = $form->addElement('text', $txtQuery, __('Search'), array('size' => '62px'));		
		$txt->setAttribute('id', $txtQuery);
		$txt->setAttribute('class', 'QuickSearch_text');
		$txt->setAttribute('onkeypress', 'setDelayOnSearch()');				
		$txt->setAttribute('placeholder', __('Enter you search here...'));
		
		
		$btn = $form->addElement('button', $btnQuery, __('Find'), null);
		$btn->setAttribute('id', 'btnQuery');
		$btn->setAttribute('onClick', $js);
		$theme->assign($txtLabel, __('Search'));
		$theme->assign($txtQuery, $txt->toHtml());
		$theme->assign($btnQuery, $btn->toHtml());
		$theme->display('quick_form');					
	
	}
	public function caption() {
		return __("QuickSearch");
	}	

}

?>