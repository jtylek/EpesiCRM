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
		if($this->is_back()) {
			$this->parent->reset();
		}
		Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
		
		$tb = $this->init_module('Utils/TabbedBrowser');
		$tb->set_tab('Translations', array($this, 'translations'));
		$tb->set_tab('Settings', array($this, 'settings'));
		$this->display_module($tb);
		$tb->tag();
	}

	public function settings() {
		$form = $this->init_module('Libs/QuickForm',null,'language_setup');

		$ls_langs = explode(',',@file_get_contents(DATA_DIR.'/Base_Lang/cache'));
		$langs = array_combine($ls_langs,$ls_langs);
		$form->addElement('select','lang_code',__('Default language'), $langs, array('onchange'=>$form->get_submit_form_js()));
		if (!Base_AdminCommon::get_access('Base_Lang_Administrator', 'select_language'))
			$form->freeze('lang_code');
		
		$form->addElement('checkbox','allow_lang_change',__('Allow users to change language'), null, array('onchange'=>$form->get_submit_form_js()));
		if (!Base_AdminCommon::get_access('Base_Lang_Administrator', 'enable_users_to_select'))
			$form->freeze('allow_lang_change');
		
		$form->setDefaults(array('lang_code'=>Variable::get('default_lang'),'allow_lang_change'=>Variable::get('allow_lang_change')));
		
		if($form->validate()) {
			$form->process(array($this,'submit_admin'));
		}
		$form->display_as_column();
	}
	
	public function translations() {
		global $translations;
		global $custom_translations;
		load_js('modules/Base/Lang/Administrator/js/main.js');
		eval_js('translate_init();');

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
		eval_js('function update_credits(){$("contact_email").disabled=$("credits_website").disabled=!$("include_credits").checked||!$("allow").checked;}');
		eval_js('update_credits();');
		$ip = gethostbyname($_SERVER['SERVER_NAME']);
		$me = CRM_ContactsCommon::get_my_record();
		$form->addElement('static', 'header', '<div id="decription_label" />', $desc);
		$form->addElement('checkbox', 'allow', __('Enable sending translations'), null, array('id'=>'allow', 'onchange'=>'$("include_credits").disabled=$("send_current").disabled=$("first_name").disabled=$("last_name").disabled=!this.checked;update_credits();'));
		$form->addElement('checkbox', 'send_current', __('Send your current translations'), null, array('id'=>'send_current'));
		$form->addElement('text', 'first_name', __('First Name'), array('id'=>'first_name'));
		$form->addElement('text', 'last_name', __('Last Name'), array('id'=>'last_name'));
		$form->addElement('checkbox', 'include_credits', __('Include in credits'), null, array('id'=>'include_credits', 'onchange'=>'update_credits();'));
		$form->addElement('text', 'credits_website', __('Credits website'), array('id'=>'credits_website'));
		$form->addElement('text', 'contact_email', __('Contact e-mail'), array('id'=>'contact_email'));
		$form->addElement('static', 'IP', __('IP'), $ip);
		$lp->add_option(null, null, null, $form);
		eval_js('$("send_current").disabled=$("first_name").disabled=$("last_name").disabled=!$("allow").checked;');
		$vals = $lp->export_values();
		if ($vals) {
			$values = $vals['form'];
			if (!isset($values['allow'])) $values['allow'] = 0;
			if (!isset($values['first_name'])) $values['first_name'] = '';
			if (!isset($values['last_name'])) $values['last_name'] = '';
			if (!isset($values['include_credits'])) $values['include_credits'] = 0;
			if (!isset($values['credits_website'])) $values['credits_website'] = '';
			if (!isset($values['contact_email'])) $values['contact_email'] = '';
			DB::Execute('DELETE FROM base_lang_trans_contrib WHERE user_id=%d', array(Acl::get_user()));
			DB::Execute('INSERT INTO base_lang_trans_contrib (user_id, allow, first_name, last_name, credits, credits_website, contact_email) VALUES (%d, %d, %s, %s, %d, %s, %s)', array(Acl::get_user(), $values['allow'], $values['first_name'], $values['last_name'], $values['include_credits'], $values['credits_website'], $values['contact_email']));
			if (isset($values['send_current'])) eval_js('new Ajax.Request("modules/Base/Lang/Administrator/send_current.php",{method:"post",parameters:{cid:Epesi.client_id}});');
		}

		$allow_sending = Base_Lang_AdministratorCommon::allow_sending(true);
		if ($allow_sending===null || $allow_sending===false) {
			$form->setDefaults(array('allow'=>1, 'send_current'=>1, 'first_name'=>$me['first_name'], 'last_name'=>$me['last_name'], 'contact_email'=>$me['email']));
			$lp->open();
		} else {
			$r = DB::GetRow('SELECT * FROM base_lang_trans_contrib WHERE user_id=%d', array(Acl::get_user()));
			if (!$r['first_name']) $r['first_name'] = $me['first_name'];
			if (!$r['last_name']) $r['last_name'] = $me['last_name'];
			if (!$r['contact_email']) $r['contact_email'] = $me['email'];
			$form->setDefaults(array('allow'=>$r['allow'], 'send_current'=>0, 'first_name'=>$r['first_name'], 'last_name'=>$r['last_name'], 'contact_email'=>$r['contact_email'], 'credits_website'=>$r['credits_website'], 'include_credits'=>$r['credits']));
		}
		Base_ActionBarCommon::add('settings', __('Translations Contributions'), $lp->get_href());
		$this->display_module($lp, array(__('Translations Contributions settings')));

		if (Base_AdminCommon::get_access('Base_Lang_Administrator', 'new_langpack'))
			Base_ActionBarCommon::add('add',__('New langpack'),$this->create_callback_href(array($this,'new_lang_pack')));
		if (Base_AdminCommon::get_access('Base_Lang_Administrator', 'select_language'))
			Base_ActionBarCommon::add('refresh',__('Refresh languages'),$this->create_callback_href(array('Base_LangCommon','refresh_cache')));

		$form2 = $this->init_module('Libs/QuickForm',null,'translaction_filter');
		$form2->addElement('select','lang_filter',__('Filter'),array(__('Show all'), __('Show with custom translation'), __('Show with translation'), __('Show without translation')), array('onchange'=>$form2->get_submit_form_js()));
		
		if($form2->validate()) {
			$vals = $form2->exportValues();
			$this->set_module_variable('filter', $vals['lang_filter']);
		}
		$filter = $this->get_module_variable('filter', 0);
		$form2->setDefaults(array('lang_filter'=>$filter));
	
		ob_start();
		$form2->display_as_row();
		$trans_filter = ob_get_clean();

		if (!isset($_SESSION['client']['base_lang_administrator']['currently_translating'])) {
			$_SESSION['client']['base_lang_administrator']['currently_translating'] = Base_LangCommon::get_lang_code();
		}
		if (!isset($_SESSION['client']['base_lang_administrator']['notice'])) {
			print('<span class="important_notice">'.__('Please make sure the correct language is selected in the box below before you start translating').' <a style="float:right;" '.$this->create_callback_href(array($this, 'hide_notice')).'>'.__('Discard').'</a>'.'</span>');
		}
		if (Base_AdminCommon::get_access('Base_Lang_Administrator', 'translate')) {
			$ls_langs = explode(',',@file_get_contents(DATA_DIR.'/Base_Lang/cache'));
			$langs = array_combine($ls_langs,$ls_langs);
			$form = $this->init_module('Libs/QuickForm',null,'language_selected');
			$form->addElement('select','lang_code',__('Currently Translating'), $langs, array('onchange'=>$form->get_submit_form_js()));
			
			$form->setDefaults(array('lang_code'=>$_SESSION['client']['base_lang_administrator']['currently_translating']));
			
			if($form->validate()) {
				$form->process(array($this,'submit_language_select'));
			}
			$form->display_as_column();
		}
		
		Base_LangCommon::load($_SESSION['client']['base_lang_administrator']['currently_translating']);
		
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
				$org = '<a href="javascript:void(0);" onclick="lang_translate(\''.Epesi::escapeJS(htmlspecialchars($o)).'\',\''.$span_id.'\');">'.$o.'</a>';
				$t = '<span id="'.$span_id.'">'.$t.'</span>';
			}
			eval_js('translate_add_id("'.$span_id.'","'.Epesi::escapeJS($o).'");');
			$data[] = array($org,$t);
		}
		
		$gb = $this->init_module('Utils/GenericBrowser',null,'lang_translations');
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
		Base_LangCommon::load();
		$this->display_module($gb,array(true),'automatic_display');
		Utils_ShortcutCommon::add(array(' '), 'translate_first_on_the_list', array('disable_in_input'=>1));
	}
	
	public function hide_notice() {
		$_SESSION['client']['base_lang_administrator']['notice'] = true;
		return false;
	}
	
	public function submit_language_select($data) {
		$_SESSION['client']['base_lang_administrator']['currently_translating'] = $data['lang_code'];
	}
	
	public function new_lang_pack(){
		if ($this->is_back()) return false;

		$form = $this->init_module('Libs/QuickForm',__('Creating new langpack...'),'new_langpack');
		$form -> addElement('header',null,__('Create new langpack'));
		$form -> addElement('text','code',__('Language code'),array('maxlength'=>2));
		$form->registerRule('check_if_langpack_exists', 'callback', 'check_if_langpack_exists', $this);
		$form -> addRule('code', __('Specified langpack already exists'), 'check_if_langpack_exists');
		$form -> addRule('code', __('Field required'), 'required');

		if ($form->validate()) {
			Base_LangCommon::new_langpack($form->exportValue('code'));
			$this->unset_module_variable('action');
			return false;
		}

		Base_ActionBarCommon::add('back',__('Cancel'),$this->create_back_href());
		Base_ActionBarCommon::add('save',__('Save'),$form->get_submit_form_href());

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