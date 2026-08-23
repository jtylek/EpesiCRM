<?php
/**
 * Theme_Administrator class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.0
 * @package epesi-base
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
		
		$form = $this->init_module(Libs_QuickForm::module_name(),'Changing template');

		$themes = Base_Theme::list_themes();
		$form->addElement('header', 'install_module_header', __('Themes Administration'));
		$form->addElement('select', 'theme', __('Choose template'), $themes);

		$form->setDefaults(array(
			'theme'=>Variable::get('default_theme'),
			));

		if($form->validate()) {
			$form->process($this->submit_admin(...));
		} else {
			$form->display_as_column();
		}
		Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
		Base_ActionBarCommon::add('save', __('Save'), $form->get_submit_form_href());

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
