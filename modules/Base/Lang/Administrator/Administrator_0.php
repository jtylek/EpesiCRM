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
		$ok_b = HTML_QuickForm::createElement('submit', 'submit_button', $this->t('OK'));
		$cancel_b = HTML_QuickForm::createElement('button', 'cancel_button', $this->t('Cancel'), $this->create_back_href());
		$form->addGroup(array($ok_b, $cancel_b));
		*/
		
		Base_ActionBarCommon::add('add','New langpack',$this->create_callback_href(array($this,'new_lang_pack')));
		Base_ActionBarCommon::add('refresh','Refresh languages',$this->create_callback_href(array('Base_LangCommon','refresh_cache')));
		Base_ActionBarCommon::add('back', 'Back', $this->create_back_href());
		Base_ActionBarCommon::add('save', 'Save', $form->get_submit_form_href());

		$form2 = $this->init_module('Libs/QuickForm',null,'translaction_filter');
		$form2->addElement('select','lang_filter',$this->t('Filter'),array('Show all', 'Show with translation', 'Show without translation'), array('onchange'=>$form2->get_submit_form_js()));
		
		if($form->validate()) {
			if($form->process(array($this,'submit_admin'))) {
				$this->parent->reset();
			}
		} else $form->display();
		
		if($form2->validate()) {
			$vals = $form2->exportValues();
			$this->set_module_variable('filter', $vals['lang_filter']);
		}
		$filter = $this->get_module_variable('filter', 0);
		$form2->setDefaults(array('lang_filter'=>$filter));

		$trans_filter = $form2->toHtml();
		
		eval_js('lang_translate = function (module, original, span_id) {'.
					'var ret = prompt("Translate: "+original);'.
					'if (ret === null) return;'.
					'$(span_id).innerHTML = ret;'.
					'$(span_id).style.color = "red";'.
					'new Ajax.Request(\'modules/Base/Lang/Administrator/update_translation.php\', {'.
						'method: \'post\','.
						'parameters:{'.
						'	module: module,'.
						'	original: original,'.
						'	new: ret,'.
						'	cid: Epesi.client_id'.
						'},'.
						'onSuccess:function(t) {'.
							'$(span_id).style.color = "black";'.
						'}'.
					'});'.
				'}');
		
		$data = array();
		foreach($translations as $m=>$v) 
			foreach($v as $o=>$t) {
				if ($filter==1 && !$t) continue;
				if ($filter==2 && $t) continue;
				$span_id = $m.'__'.md5($o);
				$data[] = array($m,'<a href="javascript:void(0);" onclick="lang_translate(\''.$m.'\',\''.$o.'\',\''.$span_id.'\');">'.$o.'</a>','<span id="'.$span_id.'">'.$t.'</span>');
			}
		
		$gb = &$this->init_module('Utils/GenericBrowser',null,'lang_translations');
		$gb->set_custom_label($trans_filter);
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
		$submit = HTML_QuickForm::createElement('submit','submit',$this->t('Create'));
		$cancel = HTML_QuickForm::createElement('button','cancel',$this->t('Cancel'), $this->create_back_href());
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
		if(DEMO_MODE && Variable::get('default_lang')!=$data['lang_code']) {
			print('You cannot change default language in demo.');
			return false;
		}
		return Variable::set('default_lang',$data['lang_code']) && Variable::set('allow_lang_change',(isset($data['allow_lang_change']) && $data['allow_lang_change'])?1:0);	
	}
}
?>