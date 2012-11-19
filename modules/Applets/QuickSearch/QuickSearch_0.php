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
		$placeholder = "";
	
		$qSearchSettings = Applets_QuickSearchCommon::getQuickSearch();
		$placeholder = $qSearchSettings['search_placeholder'];
		$opts['title'] = $opts['title'].' on '.$qSearchSettings['search_alias_name'];
		
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
		$theme->assign('search_id', $qSearchSettings['search_id']);
		$theme->display('quick_form');					
	
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
		//Base_ActionBarCommon::add('save', __('Save'), $this->create_back_href());
			
        $tb = $this->init_module('Utils/TabbedBrowser');
        $form = $this->init_module('Libs/QuickForm');
		
		// field 	Recordset e.g crm_contacts, Fields e.g f_first_name, f_last_name, Status e.g active
		$tb->set_tab('History', array($this,'list_queries'));
        $tb->set_tab('Query', array($this,'create_queries'));
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
		
		$aliasName = $form->addElement('text', $alias_name, __('Alias Name'));
		$aliasName->setAttribute('placeholder', __('Alias Name'));
				
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
		
		$recordset_copy = $form->addElement('button', 'record_btn_copy', '>');
		$recordset_copy->setAttribute('id','btnQuery');
		$recordset_copy->setAttribute('onclick', 'addToList(\'recordsetfrom\', \'recordsetto\', true);');
		$recordset_revert = $form->addElement('button', 'record_btn_revert', '<');
		$recordset_revert->setAttribute('id','btnQuery');
		$recordset_revert->setAttribute('onclick', 'removeFromList(\'recordsetfrom\', \'recordsetto\');');
		
		$fieldsFrom = $form->addElement('select', 'fieldsfrom', __('Select field(s):'), null);
		$fieldsFrom->setAttribute('id','fieldsfrom');
		$fieldsFrom->setAttribute('multiple', 'multiple');
		$fieldsFrom->setAttribute('ondblclick', 'addToList(\'fieldsfrom\', \'fieldsto\', false);');
		$fieldsFrom->setAttribute('style', 'height:150px;width:150px');

		$fields_btn_copy = $form->addElement('button', 'fields_btn_copy', '>');
		$fields_btn_copy->setAttribute('id','btnQuery');
		$fields_btn_copy->setAttribute('onclick', 'addToList(\'fieldsfrom\', \'fieldsto\', false);');
		$fields_btn_revert = $form->addElement('button', 'fields_btn_revert', '<');
		$fields_btn_revert->setAttribute('id','btnQuery');
		$fields_btn_revert->setAttribute('onclick', 'removeFromListFields(\'fieldsfrom\', \'fieldsto\');');
		
		$fieldsTo = $form->addElement('select', $fieldsto, __('Select field(s):'), null);
		$fieldsTo->setAttribute('id','fieldsto');
		$fieldsTo->setAttribute('multiple', 'multiple');
		$fieldsTo->setAttribute('ondblclick', 'removeFromListFields(\'fieldsfrom\', \'fieldsto\');');
		$fieldsTo->setAttribute('style', 'height:150px;width:150px');		

		$format = $form->addElement('textarea','search_format', __('Result Format'));
		$format->setAttribute('id', 'search_format');
		
		$placeholder = $form->addElement('text', $placeholderId, __('Placeholder'));
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
			$search_format = $form->exportValue('search_format');
			
			//print "Alias_name[".$alias_name. "] fieldsto[".$fieldsto."] placeholder[".$placeholder."] status[".$status."] Format[".$search_format."]";
			DB::Execute('insert into quick_search(search_alias_name, search_fields, search_placeholder, search_status, format) 
						values (%s, %s, %s, %s, %s)', array($alias_name, implode(';', $fieldsto), $placeholder, $status, $search_format));
			$form->setDefaults(array($alias_name=>'',$placeholder=>''));		
		}		
		$form->assign_theme('form', $theme);	
		$theme->assign('header', __('Create'));
		$theme->display('quick_search_form');
	}	
	
	
	public function list_queries(){
		$theme = $this->init_module('Base/Theme');
		$gBrowser = & $this->init_module('Utils/GenericBrowser',null,'quick_search');		
		$gBrowser->set_table_columns(array(
						array('name'=>__('Alias Name'),'width'=>20),
						array('name'=>__('Search fields'),'width'=>10),
						array('name'=>__('Placeholder'),'width'=>10),
						array('name'=>__('Format'),'width'=>44),
						array('name'=>__('Status'),'width'=>16)						
						));		
		$query = "select * from quick_search";
		$limit = "select count(search_id) from quick_search";
		$ret = $gBrowser->query_order_limit($query, $limit);
		if($ret){
			while($row = $ret->FetchRow()){
				$alias_name = $row['search_alias_name'];
				$search_fields = $row['search_fields'];
				$placeholder = $row['search_placeholder'];
				$format = $row['format'];
				$status = ($row['search_status'] == '1') ? 'Active' : 'Inactive';
				$gBrowser->add_row($alias_name, $search_fields, $placeholder, $format, $status);
			}
		}
		
		$gBrowser->set_inline_display(true);
		$theme->assign('messages',$this->get_html_of_module($gBrowser));
		$theme->display('list_quiries');
		return true;
		
	}
	
}

?>