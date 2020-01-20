<?php
/**
 * Provides error to mail handling.
 *
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
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
		
		Base_ActionBarCommon::add('back',__('Cancel'),$this->create_back_href());
		Base_ActionBarCommon::add('save',__('Save'),$form->get_submit_form_href());
		
		$form->setDefaults(array('mail'=>Variable::get('error_mail')));
		
		if($form->validate()) {
			Variable::set('error_mail',$form->exportValue('mail'));
			$this->parent->reset();
		} else {
			$form->display();
		}
	}	
}
?>