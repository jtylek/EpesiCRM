<?php
/**
 * Provides error to mail handling.
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage error
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Error extends Module implements Base_AdminInterface {
	public function body() {
	}
	
	public function admin() {
		if($this->is_back()) $this->parent->reset();
			
		$form = $this->init_module(Libs_QuickForm::module_name(),'Errors to mail');
		
		$form->addElement('text', 'mail', __('Send bugs to'));
		$form->addRule('mail', __('Invalid e-mail address'),'email');
		$form->addElement('static', '', '',__('Leave empty to disable bug reports.'));

		// Profiling switches. Super-admin only, and scoped to *this session* - see
		// include/profiling.php for why they are not a global Variable::set(). Anyone
		// else just gets the error-mail field, exactly as before.
		$sa = Base_AclCommon::i_am_sa();
		if ($sa) {
			$defaults = Profiling::config_defaults();
			// A 'header' element would be wrong here: theme_adminltedark/column.tpl
			// renders every header in its own loop *before* any field, so it reads as the
			// title of the whole form - including the unrelated bug-mail field above it.
			// A label-less 'static' lands in document order, where the divider belongs.
			$form->addElement('static', 'profiling_header', '', '<div class="epesi-qf-header mt-3"><strong>'.__('Profiling (this session only)').'</strong></div>');
			$form->addElement('checkbox', 'profile_sql', __('Show SQL queries and timings'), null, 'class="epesi-switch"');
			$form->addElement('checkbox', 'profile_modules', __('Show module render times'), null, 'class="epesi-switch"');
			// "next request" is not a wart to hide: the override is read once at bootstrap,
			// before anything renders, so the request that saves it has already passed the
			// point where module timings would have been collected.
			$form->addElement('static', '', '', __('These affect only your own session, apply from your next request onward, and are dropped when you log out. Turning either on adds real per-request overhead, so leave them off when you are done. Defaults from config.php: SQL %s, modules %s.', array($defaults['sql'] ? __('on') : __('off'), $defaults['modules'] ? __('on') : __('off'))));
		}
		
		Base_ActionBarCommon::add('back',__('Cancel'),$this->create_back_href());
		Base_ActionBarCommon::add('save',__('Save'),$form->get_submit_form_href());
		
		$form->setDefaults(array('mail'=>Variable::get('error_mail')));
		if ($sa) $form->setDefaults(array('profile_sql'=>Profiling::$sql, 'profile_modules'=>Profiling::$modules));
		
		if($form->validate()) {
			Variable::set('error_mail',$form->exportValue('mail'));
			if ($sa) {
				// Written as an explicit override in both directions, so a super-admin
				// can also silence a panel that config.php turned on globally.
				Profiling::set_session_override((bool) $form->exportValue('profile_sql'), (bool) $form->exportValue('profile_modules'));
			}
			$this->parent->reset();
		} else {
			$form->display_as_column();
		}
	}	
}
?>