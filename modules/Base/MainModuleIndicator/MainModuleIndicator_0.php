<?php
/**
 * MainModuleIndicator class.
 * 
 * This class provides MainModuleIndicator sending functionality.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides MainModuleIndicator sending functionality.
 * @package epesi-base-extra
 * @subpackage MainModuleIndicator
 */
class Base_MainModuleIndicator extends Module {

	public function body($arg) {
		$box_module = ModuleManager::get_instance('/Base_Box|0');
		if($box_module)
			$active_module = $box_module->get_main_module();
		if($active_module && is_callable(array($active_module,'caption'))) {
			$caption = $active_module->caption();
			if(Variable::get('show_module_indicator')) {
				$t = & $this->pack_module('Base/Theme');
				$t->assign('text', $caption);
				$t->display();
			}
			$show_caption = Variable::get('show_caption_in_title');
			$base_title = Variable::get('base_page_title');
			if($show_caption || strlen($base_title)>0) {
				if($show_caption && strlen($base_title)>0)
					$caption = $base_title.' - '.$caption;
				elseif(strlen($base_title)>0)
					$caption = $base_title;
				eval_js('document.title=\''.addslashes($caption).'\'');
			}
		}
	}
	
	public function admin() {
		$f = & $this->init_module('Libs/QuickForm');
		$l = & $this->init_module('Base/Lang');
		$f->setDefaults(array(
			'title'=>Variable::get('base_page_title'),
			'show_caption_in_title'=>Variable::get('show_caption_in_title'),
			'show_module_indicator'=>Variable::get('show_module_indicator')
			));
		$f->addElement('text','title',$l->t('Base page title'));
		$f->addElement('checkbox','show_caption_in_title',$l->t('Display module captions inside page title'));
		$f->addElement('checkbox','show_module_indicator',$l->t('Display module captions inside module'));
		
		$save_b = & HTML_QuickForm::createElement('submit', null, $l->ht('Save'));
		$back_b = & HTML_QuickForm::createElement('button', null, $l->ht('Cancel'), $this->create_back_href());
		$f->addGroup(array($save_b,$back_b),'submit_button');

		if($f->validate()) {
			$vars = $f->exportValues();
			Variable::set('base_page_title',$vars['title']);
			Variable::set('show_caption_in_title',$vars['show_caption_in_title']);
			Variable::set('show_module_indicator',$vars['show_module_indicator']);
			$this->parent->reset();
		}
		$f->display();
	}
}
?>
