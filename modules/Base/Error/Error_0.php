<?php
/**
 * Provides error to mail handling.
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
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
			
		$form = & $this->init_module('Libs/QuickForm','Errors to mail');
		
		$form->addElement('text', 'mail', $this->t('Send bugs to'));
		$form->addRule('mail', $this->t('This is not valid e-mail address.'),'email');
		$form->addElement('static', '', '',$this->t('Leave empty to don\'t report bugs.'));
		
		Base_ActionBarCommon::add('back','Cancel',$this->create_back_href());
		Base_ActionBarCommon::add('save','Save',$form->get_submit_form_href());
		
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