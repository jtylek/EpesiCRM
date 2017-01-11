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
		$t = $this->pack_module(Base_Theme::module_name());

		//caption
		$box_module = Base_BoxCommon::root();
		if($box_module)
			$active_module = $box_module->get_main_module();
		if($active_module && is_callable(array($active_module,'caption'))) {
			$caption = $active_module->caption();
			if(Variable::get('show_module_indicator')) {
				$t->assign('text', $caption);
			} else {
				$t->assign('text', '');
			}
			$show_caption = Variable::get('show_caption_in_title');
            $maintenance_mode = MaintenanceMode::is_on() ? ' (Maintenance mode)' : '';
            $base_title = Variable::get('base_page_title') . $maintenance_mode;
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
		
		$t->display();
	}
	
	public function admin() {
		if($this->is_back())
		    $this->parent->reset();
		    
		$form = $this->init_module(Libs_QuickForm::module_name());

		$form->addElement('header', 'settings', __('Title'));
		$form->setDefaults(array(
			'title'=>Variable::get('base_page_title'),
			'show_caption_in_title'=>Variable::get('show_caption_in_title'),
			'show_module_indicator'=>Variable::get('show_module_indicator')
			));
		$form->addElement('text','title',__('Base page title'));
		$form->addElement('checkbox','show_caption_in_title',__('Display module captions inside page title'));
		$form->addElement('checkbox','show_module_indicator',__('Display module captions inside module'));
        $form->addElement('submit', 'button', __('Save'), $form->get_submit_form_href());
        $form->addElement('static','','<div style="width:200px"></div>','<div style="width:600px"></div>');
        if($form->validate()) {
            $form->process(array($this,'submit_config'));
        } else
            $this->display_module($form);

        $form = $this->init_module(Utils_FileUpload::module_name(),array(false));
		$form->addElement('header', 'upload', __('Small Logo'));
		$form->addElement('static','logo_size','',__('Logo image should be 193px by 83px in JPG/JPEG, GIF, PNG or BMP format'));
        $logo = Variable::get('logo_file');
        if($logo && file_exists($logo)) $form->addElement('static','logo','','<img src="'.$logo.'?'.filemtime($logo).'" />');
        $form->addElement('hidden','type','');
		$form->add_upload_element();
        $form->addElement('static','','<div style="width:200px"></div>','<div style="width:600px"></div>');
        //$form->addElement('submit', 'button', __('Upload'), $form->get_submit_form_href());

		$this->display_module($form, array( array($this,'submit_logo') ));

        $form = $this->init_module(Utils_FileUpload::module_name(),array(false));
        $form->addElement('header', 'upload', __('Login Logo'));
        $form->addElement('static','logo_size','',__('Logo image should be 550px by 200px in JPG/JPEG, GIF, PNG or BMP format'));
        $logo = Variable::get('login_logo_file');
        if($logo && file_exists($logo)) $form->addElement('static','logo','','<img src="'.$logo.'?'.filemtime($logo).'" />');
        $form->addElement('hidden','type','login_');
        $form->add_upload_element();
        $form->addElement('static','','<div style="width:200px"></div>','<div style="width:600px"></div>');
        //$form->addElement('submit', 'button', __('Upload'), $form->get_submit_form_href());

        $this->display_module($form, array( array($this,'submit_logo') ));

        Base_ActionBarCommon::add('delete',__('Delete logo'),$this->create_callback_href(array($this,'delete_logo')));
        Base_ActionBarCommon::add('back',__('Back'),$this->create_back_href());
    }
	
	public function delete_logo() {
	    $l = Variable::get('logo_file');
	    if($l) {
    		@unlink($l);
		    Variable::set('logo_file','');
	    }
        $l = Variable::get('login_logo_file');
        if($l) {
            @unlink($l);
            Variable::set('login_logo_file','');
        }
	}
	
	public function submit_config($vars) {
	    Variable::set('base_page_title',$vars['title']);
	    Variable::set('show_caption_in_title',isset($vars['show_caption_in_title']) && $vars['show_caption_in_title']);
	    Variable::set('show_module_indicator',isset($vars['show_module_indicator']) && $vars['show_module_indicator']);
	}

    public function submit_logo($file,$oryg,$vars) {
        if($oryg) {
            $reqs = array();
            if(!preg_match('/\.(jpg|jpeg|gif|png|bmp)$/i',$oryg,$reqs)) {
                print('<a href="#">'.__('Uploaded file is not valid image - JPG, GIF, PNG and BMP files are supported. Click here to proceed with another file.').'</a>');
                return;
            }
            $old = Variable::get($vars['type'].'logo_file');
            @unlink($old);
            $l = $this->get_data_dir().$vars['type'].'logo.'.$reqs[1];
            Variable::set($vars['type'].'logo_file',$l);
            rename($file,$l);
            location(array());
        }
    }

    public function logo() {
	    $t = $this->pack_module(Base_Theme::module_name());
	    $l = Variable::get('logo_file');
        if($l && file_exists($l)) $l.='?'.filemtime($l);
	    $t->assign('logo',$l);
	    $t->display('logo');
	}

    public function login_logo() {
        $t = $this->pack_module(Base_Theme::module_name());
        $l = Variable::get('login_logo_file');
        if($l && file_exists($l)) $l.='?'.filemtime($l);
        $t->assign('logo',$l);
        $t->display('login-logo');
    }
}
?>
