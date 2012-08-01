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
		global $custom_translations;

		if($this->is_back()) {
			$this->parent->reset();
		}
		
		$lp = $this->init_module('Utils/LeightboxPrompt');
		$form = $this->init_module('Libs/QuickForm',null,'translations_sending');
		$desc = '<div id="trans_sett_info" style="line-height:17px;">';
		$desc .= __('You have now option to contribute with your translations to help us deliver EPESI in various languages. You can opt in to send your translations to EPESI central database, allowing to deliver EPESI in your language to other users.').'<br>';
		$desc .= __('Please note that the translations you submit aren\'t subject to copyright. EPESI Team will distribute the translations free of charge to the end users.').'<br>';
		$desc .= __('The only data being sent is the values of the fields presented below and the translated strings, we do not receive any other information contained in EPESI.').'<br>';
		$desc .= __('You can also change your Translations Contribution settings at later time.').'<br>';
		$desc .= '</div>';
		eval_js('$("trans_sett_info").up("td").setAttribute("colspan",2);');
		eval_js('$("trans_sett_info").up("td").style.borderRadius="0";'); // Not really nice, but will have to do for now
		eval_js('$("decription_label").up("td").hide();');
		$ip = gethostbyname($_SERVER['SERVER_NAME']);
		$me = CRM_ContactsCommon::get_my_record();
		$form->addElement('static', 'header', '<div id="decription_label" />', $desc);
		$form->addElement('checkbox', 'allow', __('Enable sending translations'), null, array('id'=>'allow', 'onchange'=>'$("send_current").disabled=$("first_name").disabled=$("last_name").disabled=!this.checked;'));
		$form->addElement('checkbox', 'send_current', __('Send your current translations'), null, array('id'=>'send_current'));
		$form->addElement('text', 'first_name', __('First Name'), array('id'=>'first_name'));
		$form->addElement('text', 'last_name', __('Last Name'), array('id'=>'last_name'));
		$form->addElement('static', 'IP', __('IP'), $ip);
		$lp->add_option(null, null, null, $form);
		eval_js('$("send_current").disabled=$("first_name").disabled=$("last_name").disabled=!$("allow").checked;');
		$vals = $lp->export_values();
		if ($vals) {
			$values = $vals['form'];
			if (!isset($values['allow'])) $values['allow'] = 0;
			if (!isset($values['first_name'])) $values['first_name'] = '';
			if (!isset($values['last_name'])) $values['last_name'] = '';
			DB::Execute('DELETE FROM base_lang_trans_contrib WHERE user_id=%d', array(Acl::get_user()));
			DB::Execute('INSERT INTO base_lang_trans_contrib (user_id, allow, first_name, last_name) VALUES (%d, %d, %s, %s)', array(Acl::get_user(), $values['allow'], $values['first_name'], $values['last_name']));
			if (isset($values['send_current'])) eval_js('new Ajax.Request("modules/Base/Lang/Administrator/send_current.php",{method:"post",parameters:{cid:Epesi.client_id}});');
		}

		$allow_sending = Base_Lang_AdministratorCommon::allow_sending(true);
		if ($allow_sending===null || $allow_sending===false) {
			$form->setDefaults(array('allow'=>1, 'send_current'=>1, 'first_name'=>$me['first_name'], 'last_name'=>$me['last_name']));
			$lp->open();
		} else {
			$r = DB::GetRow('SELECT * FROM base_lang_trans_contrib WHERE user_id=%d', array(Acl::get_user()));
			if (!$r['first_name']) $r['first_name'] = $me['first_name'];
			if (!$r['last_name']) $r['last_name'] = $me['last_name'];
			$form->setDefaults(array('allow'=>$r['allow'], 'send_current'=>0, 'first_name'=>$r['first_name'], 'last_name'=>$r['last_name']));
		}
		Base_ActionBarCommon::add('settings', __('Translations Contributions'), $lp->get_href());
		$this->display_module($lp, array(__('Translations Contributions settings')));

		$form = & $this->init_module('Libs/QuickForm',null,'language_setup');

		$ls_langs = explode(',',@file_get_contents(DATA_DIR.'/Base_Lang/cache'));
		$langs = array_combine($ls_langs,$ls_langs);
		$form->addElement('select','lang_code',__('Default language'), $langs, array('onchange'=>$form->get_submit_form_js()));
		if (!Base_AdminCommon::get_access('Base_Lang_Administrator', 'select_language'))
			$form->freeze('lang_code');
		
		$form->addElement('checkbox','allow_lang_change',__('Allow users to change language'), null, array('onchange'=>$form->get_submit_form_js()));
		if (!Base_AdminCommon::get_access('Base_Lang_Administrator', 'enable_users_to_select'))
			$form->freeze('allow_lang_change');
		
		$form->setDefaults(array('lang_code'=>Variable::get('default_lang'),'allow_lang_change'=>Variable::get('allow_lang_change')));
		
		if (Base_AdminCommon::get_access('Base_Lang_Administrator', 'new_langpack'))
			Base_ActionBarCommon::add('add',__('New langpack'),$this->create_callback_href(array($this,'new_lang_pack')));
		if (Base_AdminCommon::get_access('Base_Lang_Administrator', 'select_language'))
			Base_ActionBarCommon::add('refresh',__('Refresh languages'),$this->create_callback_href(array('Base_LangCommon','refresh_cache')));
		Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());

		$form2 = $this->init_module('Libs/QuickForm',null,'translaction_filter');
		$form2->addElement('select','lang_filter',__('Filter'),array(__('Show all'), __('Show with custom translation'), __('Show with translation'), __('Show without translation')), array('onchange'=>$form2->get_submit_form_js()));
		
		if($form->validate()) {
			$form->process(array($this,'submit_admin'));
		}
		$form->display_as_row();
		
		if($form2->validate()) {
			$vals = $form2->exportValues();
			$this->set_module_variable('filter', $vals['lang_filter']);
		}
		$filter = $this->get_module_variable('filter', 0);
		$form2->setDefaults(array('lang_filter'=>$filter));
	
		ob_start();
		$form2->display_as_row();
		$trans_filter = ob_get_clean();
		
		if (Base_AdminCommon::get_access('Base_Lang_Administrator', 'translate'))
			eval_js('lang_translate = function (original, span_id) {'.
					'var ret = prompt("Translate: "+original, $(span_id).innerHTML);'.
					'if (ret === null) return;'.
					'$(span_id).innerHTML = ret;'.
					'$(span_id).style.color = "red";'.
					'new Ajax.Request(\'modules/Base/Lang/Administrator/update_translation.php\', {'.
						'method: \'post\','.
						'parameters:{'.
						'	original: original,'.
						'	new: ret,'.
						'	cid: Epesi.client_id'.
						'},'.
						'onSuccess:function(t) {'.
							'if($(span_id))$(span_id).style.color = "black";'.
						'}'.
					'});'.
				'}');
		
		$data = array();
		foreach ($custom_translations as $o=>$t) {
			if ($t || !isset($translations[$o])) $translations[$o] = $t;
		}
		foreach($translations as $o=>$t) {
			if (isset($custom_translations[$o]) && $custom_translations[$o]) {
				$t = $custom_translations[$o];
			} else {
				if ($filter==1) continue;
			}
			if ($filter==2 && !$t) continue;
			if ($filter==3 && $t) continue;
			$span_id = 'trans__'.md5($o);
			if (Base_AdminCommon::get_access('Base_Lang_Administrator', 'translate')) {
				$o = '<a href="javascript:void(0);" onclick="lang_translate(\''.Epesi::escapeJS(htmlspecialchars($o)).'\',\''.$span_id.'\');">'.$o.'</a>';
				$t = '<span id="'.$span_id.'">'.$t.'</span>';
			}
			$data[] = array($o,$t);
		}
		
		$gb = &$this->init_module('Utils/GenericBrowser',null,'lang_translations');
		$gb->set_custom_label($trans_filter);
		$gb->set_table_columns(array(
				array('name'=>__('Original'), 'order_preg'=>'/^<[^>]+>([^<]*)<[^>]+>$/i','search'=>'original'),
				array('name'=>__('Translated'),'search'=>'translated')));
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

		$form = & $this->init_module('Libs/QuickForm',__('Creating new langpack...'),'new_langpack');
		$form -> addElement('header',null,__('Create new langpack'));
		$form -> addElement('text','code',__('Language code'),array('maxlength'=>2));
		$form->registerRule('check_if_langpack_exists', 'callback', 'check_if_langpack_exists', $this);
		$form -> addRule('code', __('Specified langpack already exists'), 'check_if_langpack_exists');
		$form -> addRule('code', __('Field required'), 'required');

		Base_ActionBarCommon::add('back',__('Cancel'),$this->create_back_href());
		Base_ActionBarCommon::add('save',__('Save'),$form->get_submit_form_href());

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