<?php
/**
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage attachment-administrator
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_Attachment_Administrator extends Module {

	public function body() {
	
	}

	public function admin() {
		if($this->is_back()) {
			$this->parent->reset();
		}
		
		$form = & $this->init_module('Libs/QuickForm',null,'xxx');
		
		$form->addElement('header', 'module_header', 'Notes & Attachments administration');
		
		$form->addElement('checkbox','view_deleted',$this->t('View deleted'));
		
		$form->setDefaults(array('view_deleted'=>isset($_SESSION['view_deleted_attachments']) && $_SESSION['view_deleted_attachments']));
		
		Base_ActionBarCommon::add('back', 'Back', $this->create_back_href());
		Base_ActionBarCommon::add('save', 'Save', $form->get_submit_form_href());
		
		if($form->validate()) {
			$v = $form->exportValues();
			$_SESSION['view_deleted_attachments'] = isset($v['view_deleted']) && $v['view_deleted'];
			$this->parent->reset();
		} else $form->display();		
	}

}

?>