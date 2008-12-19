<?php
/**
 * MainModuleIndicator class.
 *
 * This class provides MainModuleIndicator functionality.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage MainModuleIndicator
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_MainModuleIndicator extends Module {

	public function body() {
		$t = $this->pack_module('Base/Theme');

		//caption
		$box_module = ModuleManager::get_instance('/Base_Box|0');
		if($box_module)
			$active_module = $box_module->get_main_module();
		if($active_module && is_callable(array($active_module,'caption'))) {
			$caption = $active_module->caption();
			if ($caption!='') $caption = $this->t($caption);
			if(Variable::get('show_module_indicator')) {
				$t->assign('text', $caption);
			} else {
				$t->assign('text', '');
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
		} else {
				eval_js('document.title=\''.addslashes(Variable::get('base_page_title')).'\'');
		}
		
		//help
		$t->assign('help', '<a href="'.$this->get_module_dir().'help.php?cid='.CID.'" target="_blank">help</a>');
		
		
		$t->display();
	}
	
	public function admin() {
		$f = & $this->init_module('Libs/QuickForm');
		$f->setDefaults(array(
			'title'=>Variable::get('base_page_title'),
			'show_caption_in_title'=>Variable::get('show_caption_in_title'),
			'show_module_indicator'=>Variable::get('show_module_indicator')
			));
		$f->addElement('text','title',$this->t('Base page title'));
		$f->addElement('checkbox','show_caption_in_title',$this->t('Display module captions inside page title'));
		$f->addElement('checkbox','show_module_indicator',$this->t('Display module captions inside module'));
		
		$save_b = & HTML_QuickForm::createElement('submit', null, $this->ht('Save'));
		$back_b = & HTML_QuickForm::createElement('button', null, $this->ht('Cancel'), $this->create_back_href());
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
