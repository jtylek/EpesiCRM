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

    public function admin() {
		if($this->is_back()) {
			if($this->parent->get_type()=='Base_Admin')
				$this->parent->reset();
			else
				location(array());
			return;
		}
		Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
		//Base_ActionBarCommon::add('save', __('Save'), $this->create_back_href());
			
        $tb = $this->init_module('Utils/TabbedBrowser');
        $form = $this->init_module('Libs/QuickForm');
		
		// field 	Recordset e.g crm_contacts, Fields e.g f_first_name, f_last_name, Status e.g active
		$tb->set_tab('Queries', array($this,'list_queries'));
        $tb->set_tab('Create', array($this,'create_queries'));
        $this->display_module($tb);
		return true;
    }
	
	public function create_queries(){
		$form = & $this->init_module('Libs/QuickForm');
		$theme = $this->init_module('Base/Theme');
		
		load_js('modules/Applets/QuickSearch/js/quicksearch.js');
		
		$options = array();
		$rb_tabs = DB::GetAssoc('SELECT tab, tpl FROM recordbrowser_table_properties');
		foreach ($rb_tabs as $key => $value){
			$options[$key] =  Utils_RecordBrowserCommon::get_caption($key);
		}		

		$recordsetIdTo = 'recordsetto';
		$alias_name = 'alias_name';
		$fieldsto = 'fieldsto';
		$placeholderId = 'placeholder';
		
		$aliasName = $form->addElement('text', $alias_name, __('Alias name'));
		$aliasName->setAttribute('placeholder', __('Alias name'));
				
		$recordset_from = $form->addElement('select', 'recordsetfrom', __('Recordset'), $options);
		$recordset_from->setAttribute('id','recordsetfrom');
		$recordset_from->setAttribute('multiple', 'multiple');
		$recordset_from->setAttribute('ondblclick', 'addToList(\'recordsetfrom\', \'recordsetto\', true);');
		$recordset_from->setAttribute('style', 'width: 150px;height:150px;');
		
		$recordset_to = $form->addElement('select', $recordsetIdTo, __('Recordset'), array());
		$recordset_to->setAttribute('id','recordsetto');
		$recordset_to->setAttribute('multiple', 'multiple');
		$recordset_to->setAttribute('ondblclick', 'removeFromList(\'recordsetfrom\', \'recordsetto\');');
		$recordset_to->setAttribute('style', 'width: 150px;height:150px;');		
		
		$recordset_copy = $form->addElement('button', 'record_btn_copy', __('>'));
		$recordset_copy->setAttribute('id','btnQuery');
		$recordset_copy->setAttribute('onclick', 'addToList(\'recordsetfrom\', \'recordsetto\', true);');
		$recordset_revert = $form->addElement('button', 'record_btn_revert', __('<'));
		$recordset_revert->setAttribute('id','btnQuery');
		$recordset_revert->setAttribute('onclick', 'removeFromList(\'recordsetfrom\', \'recordsetto\');');
		
		$fieldsFrom = $form->addElement('select', 'fieldsfrom', __('Select field(s):'), null);
		$fieldsFrom->setAttribute('id','fieldsfrom');
		$fieldsFrom->setAttribute('multiple', 'multiple');
		$fieldsFrom->setAttribute('ondblclick', 'addToList(\'fieldsfrom\', \'fieldsto\', false);');
		$fieldsFrom->setAttribute('style', 'height:150px;width:150px');

		$fields_btn_copy = $form->addElement('button', 'fields_btn_copy', __('>'));
		$fields_btn_copy->setAttribute('id','btnQuery');
		$fields_btn_copy->setAttribute('onclick', 'addToList(\'fieldsfrom\', \'fieldsto\', false);');
		$fields_btn_revert = $form->addElement('button', 'fields_btn_revert', __('<'));
		$fields_btn_revert->setAttribute('id','btnQuery');
		$fields_btn_revert->setAttribute('onclick', 'removeFromListFields(\'fieldsfrom\', \'fieldsto\');');
		
		$fieldsTo = $form->addElement('select', $fieldsto, __('Select field(s):'), null);
		$fieldsTo->setAttribute('id','fieldsto');
		$fieldsTo->setAttribute('multiple', 'multiple');
		$fieldsTo->setAttribute('ondblclick', 'removeFromListFields(\'fieldsfrom\', \'fieldsto\');');
		$fieldsTo->setAttribute('style', 'height:150px;width:150px');		
			
		$placeholder = $form->addElement('text', $placeholderId, __('Place holder'));
		$placeholder->setAttribute('placeholder', __('Query search placeholder'));
		$status = $form->addElement('checkbox', 'status', 'Status');
		$status->setAttribute('checked', 'checked');
		
		//$form->addRule($alias_name, __('error_alias_name'), 'required');
		//$form->addRule($recordsetIdTo, __('error_recordsetto'), 'required');
		//$form->addRule($fieldsto, __('error_fieldsto'), 'required');
		//$form->addRule($placeholderId, __('error_placeholder'), 'required');
		
		Base_ActionBarCommon::add('save', __('Save'), $form->get_submit_form_href());
		if($form->validate()){
			$alias_name = $form->exportValue('alias_name');
			//$recordsetIdTo = $form->exportValue('recordsetto'); 
			$fieldsto = $form->exportValue('fieldsto');
			$placeholder = $form->exportValue('placeholder');
			$status = $form->exportValue('status');
			
			DB::Execute('insert into quick_search(search_alias_name, search_fields, search_placeholder, search_status) 
						values (%s, %s, %s, %s)', array($alias_name, implode(';', $fieldsto), $placeholder, $status));
			$form->setDefaults(array($alias_name=>'',$placeholder=>''));		
		}		
		$form->assign_theme('form', $theme);	
		$theme->assign('header', __('Query'));
		$theme->display('quick_search_form');
	}	
	
	
}

?>