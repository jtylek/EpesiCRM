<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_QuickSearch extends Module{
	
	public function body(){
	
	}
	
	public function applet($conf, & $opts){
		$opts['go' ] = false;
		$theme = $this->init_module('Base/Theme');
		$form = $this->init_module('Libs/QuickForm');
		
		$txtQuery = 'query_text';
		$txtLabel = 'query_label';
		$btnQuery = 'query_button';
		
		$txt = $form->addElement('text', $txtQuery, $this->t('Search'), array('size' => 100));		
		$txt->setAttribute('id', 'txtQuery');
		
		
		$btn = $form->addElement('button', $btnQuery, 'Find', null);
		$btn->setAttribute('id', 'btnQuery');
		$btn->setAttribute('onClick', 'javascript: var txtVal = document.getElementById(\'txtQuery\').value; 
										new Ajax.Updater(\'tableID\', \'modules/Applets/QuickSearch/getresult.php\',
										{ method: \'get\', parameters: {q:txtVal}} )');

		//$form->applyFilter($txtQuery , 'trim');
		//$form->addRule($txtQuery, 'Please enter your name', 'required');
		//if($form->validate()){
		//}	
		$theme->assign($txtLabel, __('Search'));
		$theme->assign($txtQuery, $txt->toHtml());
		$theme->assign($btnQuery, $btn->toHtml());
		$theme->display('quick_form');				
		return true;		
	
	}
	public function caption() {
		return __("QuickSearch");
	}	

}

?>