<?php
/**
 * Theme_Administrator class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package epesi-base-extra
 * @subpackage theme-administrator
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Theme_Administrator extends Module implements Base_AdminInterface{
	
	public function body() {
		$this->admin();
	}
	
	public function admin() {
		if($this->is_back()) {
			if($this->parent->get_type()=='Base_Admin')
				$this->parent->reset();
			else
				location(array());
			return;
		}
		
		$this->lang = & $this->init_module('Base/Lang'); 
		
		$form = & $this->init_module('Libs/QuickForm','Changing theme');
		
		$themes = Base_Theme::list_themes();
		$form->addElement('select', 'theme', $this->lang->t('Choose theme'), $themes);
		
		$ok_b = HTML_QuickForm::createElement('submit', 'submit_button', $this->lang->ht('OK'));
		$cancel_b = HTML_QuickForm::createElement('button', 'cancel_button', $this->lang->ht('Cancel'), 'onClick="'.$this->create_back_href().'"');
		$form->addGroup(array($ok_b, $cancel_b));
		
		$form->setDefaults(array('theme'=>Variable::get('default_theme')));
		
		if($form->validate()) {
			$form->process(array(& $this, 'submit_admin'));
/*			if($this->parent->get_type()=='Base_Admin')
			    $this->parent->reset();
			else
			    location(array());*/
		} else {
			$form->display();
			
			if(class_exists('ZipArchive'))
				$this->pack_module('Utils/FileUpload',array(array($this,'upload_template'),$this->lang->t('Upload template')));
		}
	}
	
	public function upload_template($file, $oryginal_file) {
		$zip = new ZipArchive;
		if ($zip->open($file) == 1) {
    			$zip->extractTo('data/Base_Theme/templates/');
			Base_StatusBarCommon::message($this->lang->t('Template installed'));
    			return true;
		}
		Base_StatusBarCommon::message($this->lang->t('Invalid template file'),'error');
		return true;
	}
	
	public function submit_admin($data) {
		Variable::set('default_theme',$data['theme']);
		Base_ThemeCommon::create_cache();
		Base_StatusBarCommon::message('Theme changed - reloading page');
		eval_js('setTimeout(\'document.location=\\\'index.php\\\'\',\'3000\')');
		return true;
	}
	
}
?>
