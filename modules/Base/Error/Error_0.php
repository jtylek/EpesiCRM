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
			
		$form = & $this->init_module('Libs/QuickForm','Changing theme');
		
		$form->addElement('text', 'mail', $this->t('Send bugs to'));
		$form->addRule('mail', $this->t('This is not valid e-mail address.'),'email',true);
		$form->addElement('static', '', '',$this->t('Leave empty to don\'t report bugs.'));
		
		$ok_b = HTML_QuickForm::createElement('submit', 'submit_button', $this->ht('OK'));
		$cancel_b = HTML_QuickForm::createElement('button', 'cancel_button', $this->ht('Cancel'), 'onClick="'.$this->create_back_href().'"');
		$form->addGroup(array($ok_b, $cancel_b));
		
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