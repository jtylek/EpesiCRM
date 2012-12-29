<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_QuickSearch extends Module{
	
	private $rb = null;
	
	public function body(){
	
	}
	
	public function applet($conf, & $opts){
		$recordset = "quick_search";

		$theme = $this->init_module('Base/Theme');
		$form = $this->init_module('Libs/QuickForm');
		
		$txtQuery = 'query_text';
		$txtLabel = 'query_label';
		$btnQuery = 'query_button';
		$id = $conf['criteria'];
		$searchPrompt = Applets_QuickSearchCommon::getSearchPromptById($id);
		$placeholder = ($searchPrompt == "") ? "" : $searchPrompt;
		
		$opts['title'] = ($conf['a_title'] == "") ? $opts['title'] : $conf['a_title'];
		$opts['go' ] = false;		
		
		load_css('modules/Applets/QuickSearch/theme/quick_form.css');
		load_js('modules/Applets/QuickSearch/js/quicksearch.js');	
		
		//$js ='setDelayOnSearch()';
		//eval_js($js);
		$txt = $form->addElement('text', $txtQuery, __('Search'));		
		$txt->setAttribute('id', $txtQuery."_".$id);
		$txt->setAttribute('class', 'QuickSearch_text');
		$txt->setAttribute('onkeypress', 'setDelayOnSearch(\''.$id.'\')');				
		$txt->setAttribute('placeholder', _V($placeholder));
		
		$theme->assign($txtLabel, __('Search'));
		$theme->assign($txtQuery, $txt->toHtml());
		$theme->assign('search_id', $conf['criteria']);
		$theme->display('quick_form');						
		return true;
	
	}
	public function caption() {
		return __('Quick Search');
	}	

    public function admin() {
		if($this->is_back()) {
			if($this->parent->get_type()=='Base_Admin')
				$this->parent->reset();
			else
				location(array());
			return;
		}
		Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
		$this->rb = $this->init_module('Utils/RecordBrowser','quick_search','quick_searach');
		$this->display_module($this->rb);
		return true;
    }
	
}

?>