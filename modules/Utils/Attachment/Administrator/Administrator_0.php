<?php
/**
 * 
 * @author shacky@poczta.fm
 * @copyright shacky@poczta.fm
 * @license SPL
 * @version 0.1
 * @package utils-attachment-administrator
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_Attachment_Administrator extends Module {

	public function body() {
	
	}

	public function admin() {
		global $translations;
		$this->lang = & $this->init_module('Base/Lang');

		if($this->is_back()) {
			$this->parent->reset();
		}
		
		$form = & $this->init_module('Libs/QuickForm',null,'xxx');
		
		$form->addElement('header', 'module_header', 'Notes & Attachments administration');
		
		$form->addElement('checkbox','view_deleted',$this->lang->t('View deleted'));
		
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