<?php
/**
 * Lang_Administrator class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * @package tcms-base-extra
 * @subpackage lang-administrator
 */
class Base_Lang_Administrator extends Module implements Base_AdminInterface {
	
	public function body() {
	}
	
	public function admin() {
		global $translations;
		$this->lang = $this->pack_module('Base/Lang');

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
		
		$form = & $this->init_module('Libs/QuickForm');
		
		$ls_langs = scandir('data/Base/Lang');
		$langs = array();
		foreach ($ls_langs as $entry)
			if (ereg('.\.php$', $entry)) {
				$lang = substr($entry,0,-4);
				$langs[$lang] = $lang;
			}
		$form->addElement('select','lang_code',$this->lang->t('Default language'), $langs);
		
		$form->addElement('select','allow_lang_change',$this->lang->t('Allow users to change language'), array(1=>$this->lang->ht('Yes'), 0=>$this->lang->ht('No')));
		
		$form->setDefaults(array('lang_code'=>Variable::get('default_lang'),'allow_lang_change'=>Variable::get('allow_lang_change')));
		
		$ok_b = HTML_QuickForm::createElement('submit', 'submit_button', $this->lang->ht('OK'));
		$cancel_b = HTML_QuickForm::createElement('button', 'cancel_button', $this->lang->ht('Cancel'), $this->create_back_href());
		$form->addGroup(array($ok_b, $cancel_b));
		
		if($form->validate()) {
			if($form->process(array($this,'submit_admin'))) {
				$this->parent->reset();
			}
		} else $form->display();
		
		
		$data = array();
		foreach($translations as $m=>$v) 
			foreach($v as $o=>$t)
				$data[] = array($m,'<a '.$this->create_unique_href(array('module'=>$m, 'original'=>$o)).'>'.$o.'</a>',$t);
		
		$gb = &$this->init_module('Utils/GenericBrowser','lang_translations');
		$gb->set_table_columns(array(
				array('name'=>$this->lang->t('Module'),'width'=>30,'search'=>'modules'),
				array('name'=>$this->lang->t('Original'),'width'=>35, 'order_eregi'=>'^<[^>]+>([^<]*)<[^>]+>$','search'=>'original'),
				array('name'=>$this->lang->t('Translated'),'search'=>'translated')));
		//$limit = $gb->get_limit(count($data));
		$id = 0;
		foreach($data as $v) {
			//if ($id>=$limit['offset'] && $id<$limit['offset']+$limit['numrows'])
				$gb->add_row_array($v);
			$id++;
		}
		$this->display_module($gb,array(null,true));
	}
	
	public function submit_admin($data) {
		return Variable::set('default_lang',$data['lang_code']) && Variable::set('allow_lang_change',$data['allow_lang_change']);	
	}
	
	private function translate($module, $original) {
		global $translations;
		
		$form = & $this->init_module('Libs/QuickForm',null,'tr');
		
		$form->addElement('header', null, htmlspecialchars($original));
		$form->addElement('text','trans_text','Translation');
		$form->setDefaults(array('trans_text'=>htmlspecialchars($translations[$module][$original])));
		
		$ok_b = HTML_QuickForm::createElement('submit', 'submit_button', $this->lang->ht('OK'));
		$cancel_b = HTML_QuickForm::createElement('button', 'cancel_button', $this->lang->ht('Cancel'), $this->create_back_href());
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
		Base_Lang::save();
	}
	
}
?>