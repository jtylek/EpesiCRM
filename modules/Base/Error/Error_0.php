<?php
/**
 * Error class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license SPL
 * @package epesi-base-extra
 * @subpackage lang-administrator
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Error extends Module implements Base_AdminInterface {
	private $lang;
	
	public function body() {
	}
	
	public function admin() {
		if($this->is_back()) $this->parent->reset();
			
		$this->lang = & $this->init_module('Base/Lang');
		
		$form = & $this->init_module('Libs/QuickForm','Changing theme');
		
		$form->addElement('text', 'mail', $this->lang->t('Send bugs to'));
		$form->addRule('mail', $this->lang->t('This is not valid e-mail address.'),'email');
		$form->addElement('static', '', '',$this->lang->t('Leave empty to don\'t report bugs.'));
		
		$ok_b = HTML_QuickForm::createElement('submit', 'submit_button', $this->lang->ht('OK'));
		$cancel_b = HTML_QuickForm::createElement('button', 'cancel_button', $this->lang->ht('Cancel'), 'onClick="'.$this->create_back_href().'"');
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