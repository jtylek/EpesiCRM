<?php
/**
 * Lang_Administrator class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage lang-administrator
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Lang_Administrator extends Module implements Base_AdminInterface {
	
	public function body() {
	}
	
	public function admin() {
		global $translations;

		if($this->is_back()) {
			if($this->isset_module_variable('module') && $this->isset_module_variable('original')) {
				$this->unset_module_variable('module');
				$this->unset_module_variable('original');
			} else
				$this->parent->reset();
		}
		
		$module = $this->get_module_variable_or_unique_href_variable('module');
		$original = $this->get_module_variable_or_unique_href_variable('original');
		if(isset($module) && isset($original)) 
			return $this->translate($module, $original);
		
		$form = & $this->init_module('Libs/QuickForm',null,'language_setup');
		
		$ls_langs = explode(',',@file_get_contents(DATA_DIR.'/Base_Lang/cache'));
		$langs = array_combine($ls_langs,$ls_langs);
		$form->addElement('header', 'module_header', 'Languages Administration');
		$form->addElement('select','lang_code',$this->t('Default language'), $langs);
		
		$form->addElement('checkbox','allow_lang_change',$this->t('Allow users to change language'));
		
		$form->setDefaults(array('lang_code'=>Variable::get('default_lang'),'allow_lang_change'=>Variable::get('allow_lang_change')));
		
		/*
		$ok_b = HTML_QuickForm::createElement('submit', 'submit_button', $this->ht('OK'));
		$cancel_b = HTML_QuickForm::createElement('button', 'cancel_button', $this->ht('Cancel'), $this->create_back_href());
		$form->addGroup(array($ok_b, $cancel_b));
		*/
		
		Base_ActionBarCommon::add('add','New langpack',$this->create_callback_href(array($this,'new_lang_pack')));
		Base_ActionBarCommon::add('refresh','Refresh languages',$this->create_callback_href(array('Base_LangCommon','refresh_cache')));
		Base_ActionBarCommon::add('back', 'Back', $this->create_back_href());
		Base_ActionBarCommon::add('save', 'Save', $form->get_submit_form_href());
		
		if($form->validate()) {
			if($form->process(array($this,'submit_admin'))) {
				$this->parent->reset();
			}
		} else $form->display();
		
		
		$data = array();
		foreach($translations as $m=>$v) 
			foreach($v as $o=>$t)
				$data[] = array($m,'<a '.$this->create_unique_href(array('module'=>$m, 'original'=>$o)).'>'.$o.'</a>',$t);
		
		$gb = &$this->init_module('Utils/GenericBrowser',null,'lang_translations');
		$gb->set_table_columns(array(
				array('name'=>$this->t('Module'),'width'=>30,'search'=>'modules'),
				array('name'=>$this->t('Original'), 'order_preg'=>'/^<[^>]+>([^<]*)<[^>]+>$/i','search'=>'original'),
				array('name'=>$this->t('Translated'),'search'=>'translated')));
		//$limit = $gb->get_limit(count($data));
		$id = 0;
		foreach($data as $v) {
			//if ($id>=$limit['offset'] && $id<$limit['offset']+$limit['numrows'])
				$gb->add_row_array($v);
			$id++;
		}
		$this->display_module($gb,array(true),'automatic_display');
	}
	
		
	
	public function new_lang_pack(){
		if ($this->is_back()) return false;

		$form = & $this->init_module('Libs/QuickForm',$this->t('Creating new langpack...'),'new_langpack');
		$form -> addElement('header',null,$this->t('Create new langpack'));
		$form -> addElement('text','code',$this->t('Language code'),array('maxlength'=>2));
		$form->registerRule('check_if_langpack_exists', 'callback', 'check_if_langpack_exists', $this);
		$form -> addRule('code', $this->t('Specified langpack already exists'), 'check_if_langpack_exists');
		$form -> addRule('code', $this->t('Field required'), 'required');
		$submit = HTML_QuickForm::createElement('submit','submit',$this->ht('Create'));
		$cancel = HTML_QuickForm::createElement('button','cancel',$this->ht('Cancel'), $this->create_back_href());
		$form -> addGroup(array($submit,$cancel));
		if ($form->validate()) {
			Base_LangCommon::new_langpack($form->exportValue('code'));
			$this->unset_module_variable('action');
			return false;
		}
		$form->display();
		return true;
	}
	
	public function check_if_langpack_exists($langpack) {
		return Base_LangCommon::get_langpack($langpack) === false;
	}

	public function submit_admin($data) {
		return Variable::set('default_lang',$data['lang_code']) && Variable::set('allow_lang_change',(isset($data['allow_lang_change']) && $data['allow_lang_change'])?1:0);	
	}
	
	private function translate($module, $original) {
		global $translations;
		
		$form = & $this->init_module('Libs/QuickForm',null,'tr');
		
		$form->addElement('header', null, htmlspecialchars($original));
		$form->addElement('text','trans_text','Translation');
		$form->setDefaults(array('trans_text'=>htmlspecialchars($translations[$module][$original])));
		
		$ok_b = HTML_QuickForm::createElement('submit', 'submit_button', $this->ht('OK'));
		$cancel_b = HTML_QuickForm::createElement('button', 'cancel_button', $this->ht('Cancel'), $this->create_back_href());
		$form->addGroup(array($ok_b, $cancel_b));
		
		if($form->validate()) {
			$form->process(array(&$this, 'submit_translate'));
		} else
			$form->display();
		
	}
	
	public function submit_translate($data) {
		global $translations;
		$module = $this->get_module_variable('module');
		$original = $this->get_module_variable('original');
		$translations[$module][$original] = $data['trans_text'];
		$this->set_back_location();
		Base_LangCommon::save();
	}
	
}
?>