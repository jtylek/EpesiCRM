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
				$t->assign('text', '');
				eval_js('document.title=\''.addslashes(Variable::get('base_page_title')).'\'');
		}
		
		//help
		$t->assign('help', '<a href="'.$this->get_module_dir().'help.php?cid='.CID.'" target="_blank">help</a>');
			
		$t->display();
	}
	
	public function admin() {
		if($this->is_back())
		    $this->parent->reset();
		    
		$form = & $this->init_module('Utils/FileUpload',array(false));

		$form->addElement('header', 'settings', $this->t('Title'));
		$form->setDefaults(array(
			'title'=>Variable::get('base_page_title'),
			'show_caption_in_title'=>Variable::get('show_caption_in_title'),
			'show_module_indicator'=>Variable::get('show_module_indicator')
			));
		$form->addElement('text','title',$this->t('Base page title'));
		$form->addElement('checkbox','show_caption_in_title',$this->t('Display module captions inside page title'));
		$form->addElement('checkbox','show_module_indicator',$this->t('Display module captions inside module'));

		$form->addElement('header', 'upload', $this->t('Logo'));
		$form->add_upload_element();

		Base_ActionBarCommon::add('save','Save',$form->get_submit_form_href());
		Base_ActionBarCommon::add('delete','Delete logo',$this->create_callback_href(array($this,'delete_logo')));
		Base_ActionBarCommon::add('back','Back',$this->create_back_href());

		$this->display_module($form, array( array($this,'submit_all') ));
	}
	
	public function delete_logo() {
	    $l = Variable::get('logo_file');
	    if($l) {
    		@unlink($l);
		Variable::set('logo_file','');
	    }
	}
	
	public function submit_all($file,$oryg,$vars) {
	    Variable::set('base_page_title',$vars['title']);
	    Variable::set('show_caption_in_title',isset($vars['show_caption_in_title']) && $vars['show_caption_in_title']);
	    Variable::set('show_module_indicator',isset($vars['show_module_indicator']) && $vars['show_module_indicator']);
	    if($oryg) {
		$reqs = array();
    		if(!preg_match('/\.(jpg|jpeg|gif|png|bmp)$/i',$oryg,$reqs)) {
    		    print('<a href="#">'.$this->t('Uploaded file is not valid image - JPG, GIF, PNG and BMP files are supported. Click here to proceed with another file.').'</a>');
		    return;
    		}
		$l = $this->get_data_dir().'logo.'.$reqs[1];
		Variable::set('logo_file',$l);
    		rename($file,$l);
	    }
	    $this->parent->reset();
	}
	
	public function logo() {
	    $t = $this->pack_module('Base/Theme');
	    $l = Variable::get('logo_file');
	    $t->assign('logo',$l);
	    $t->display('logo');
	}
}
?>
