<?php
/**
 * MainModuleIndicator class.
 *
 * This class provides MainModuleIndicator functionality.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Janusz Tylek
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
		$box_module = ModuleManager::get_instance('/Base_Box|0');
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

		// Only consumed by the adminlte theme (a Bootstrap Icon next to the
		// caption) - the default theme's own default.tpl doesn't reference it.
		// module_icon mirrors the caption()-delegation pattern above (a module
		// like CRM_Contacts or Base_HomePage can expose an icon() method that
		// points at the actual active table/page's own icon, e.g. Companies vs
		// Contacts) - it takes priority in Base_BootstrapIcons::resolve() over
		// module_type, the same order Base_Menu's build_menu_html() already
		// uses (per-link icon first, module fallback second), so this bar's
		// icon can't disagree with the sidebar's for the same screen.
		$t->assign('module_icon', ($active_module && is_callable(array($active_module,'icon'))) ? $active_module->icon() : null);
		// module_type: prefer the packed child's real type over Base_HomePage's
		// own (get_type() is final, so Base_HomePage can't just override it
		// directly - see that module's packed_module_type() for why this needs
		// its own accessor). Without this, "Home" landing on e.g. Dashboard
		// reported as module_type=Base_HomePage on first load (only a REAL
		// navigation to Dashboard - not via Home - tracked it correctly),
		// breaking any :has()+data-module-type CSS keyed off the real module,
		// e.g. Base_Box's own Dashboard-specific ActionBar-hiding rule.
		$module_type = (isset($active_module) && $active_module) ? $active_module->get_type() : null;
		if ($active_module && is_callable(array($active_module,'packed_module_type'))) {
			$packed_type = $active_module->packed_module_type();
			if ($packed_type) $module_type = $packed_type;
		}
		$t->assign('module_type', $module_type);

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
		$form->addElement('checkbox','show_caption_in_title',__('Display module captions inside page title'), null, array('class'=>'epesi-switch'));
		$form->addElement('checkbox','show_module_indicator',__('Display module captions inside module'), null, array('class'=>'epesi-switch'));
        $form->addElement('submit', 'button', __('Save'), $form->get_submit_form_href().' class="submit btn btn-primary"');
        $form->addElement('static','','<div style="width:200px"></div>','<div style="width:600px"></div>');
        if($form->validate()) {
            $form->process($this->submit_config(...));
        } else
            $form->display_as_column();

        $form = $this->init_module(Utils_FileUpload::module_name(),array(false));
		$form->addElement('header', 'upload', __('Small Logo'));
		$form->addElement('static','logo_size','',__('Logo image should be 193px by 83px in JPG/JPEG, GIF, PNG or BMP format'));
        $logo = Variable::get('logo_file');
        // Same fallback path this logo's own display template
        // (theme/logo.tpl) already falls back to when nothing's been
        // uploaded - mirrors reality instead of guessing, same principle as
        // the Printing Options logo preview.
        if ($logo && file_exists($logo)) {
            $preview = $logo.'?'.filemtime($logo);
            $preview_caption = __('Current logo');
        } else {
            $preview = Base_ThemeCommon::get_template_file('images/logo-small.png');
            $preview_caption = __('Current logo (default EPESI logo - no custom logo uploaded)');
        }
        $form->addElement('hidden','type','');
		$form->add_upload_element();
        $form->addElement('static','','<div style="width:200px"></div>','<div style="width:600px"></div>');
        //$form->addElement('submit', 'button', __('Upload'), $form->get_submit_form_href());

		// column.tpl renders every button in its own trailing section, always
		// after the whole field grid and with nothing after it - so a
		// 'static' element (or plain print() once the card's already closed)
		// can't land below the button while staying inside the card. Capture
		// the rendered card HTML and splice the preview in just before its
		// own closing </div> instead.
		//
		// set_inline_display() is required for this to actually work:
		// display_module() normally prints just a placeholder <span> (real
		// content gets injected separately, out-of-band, later in the page
		// render) - get_html_of_module() only returns the real HTML directly
		// when the module is in inline-display mode (see Utils_RecordBrowser/
		// Reports/Reports_0.php's identical use on a GenericBrowser instance).
		// Without this, ob_get_clean() below only ever captures the
		// placeholder span, and the spliced-in preview ends up appended after
		// it - outside the card once the real content is injected.
		$form->set_inline_display();
		ob_start();
		$this->display_module($form, array( $this->submit_logo(...) ));
		$html = ob_get_clean();
		$preview_html = '<div>'.htmlspecialchars($preview_caption).'</div><img src="'.htmlspecialchars($preview).'" style="max-width:300px;" />';
		// Second-to-last </div>, not the last one - see TCPDF_0.php's
		// identical fix for why.
		$last = strrpos($html, '</div>');
		$pos = $last !== false ? strrpos(substr($html, 0, $last), '</div>') : false;
		print($pos !== false ? substr($html, 0, $pos).$preview_html.substr($html, $pos) : $html.$preview_html);

        $form = $this->init_module(Utils_FileUpload::module_name(),array(false));
        $form->addElement('header', 'upload', __('Login Logo'));
        $form->addElement('static','logo_size','',__('Logo image should be 550px by 200px in JPG/JPEG, GIF, PNG or BMP format'));
        $logo = Variable::get('login_logo_file');
        // Same fallback path theme_adminltedark/login-logo.tpl already falls
        // back to when nothing's been uploaded.
        if ($logo && file_exists($logo)) {
            $preview = $logo.'?'.filemtime($logo);
            $preview_caption = __('Current logo');
        } else {
            $preview = Base_ThemeCommon::get_template_file('images/logo.png');
            $preview_caption = __('Current logo (default EPESI logo - no custom logo uploaded)');
        }
        $form->addElement('hidden','type','login_');
        $form->add_upload_element();
        $form->addElement('static','','<div style="width:200px"></div>','<div style="width:600px"></div>');
        //$form->addElement('submit', 'button', __('Upload'), $form->get_submit_form_href());

        $form->set_inline_display();
        ob_start();
        $this->display_module($form, array( $this->submit_logo(...) ));
        $html = ob_get_clean();
        $preview_html = '<div>'.htmlspecialchars($preview_caption).'</div><img src="'.htmlspecialchars($preview).'" style="max-width:300px;" />';
        // Second-to-last </div>, not the last one - see TCPDF_0.php's
        // identical fix for why.
        $last = strrpos($html, '</div>');
        $pos = $last !== false ? strrpos(substr($html, 0, $last), '</div>') : false;
        print($pos !== false ? substr($html, 0, $pos).$preview_html.substr($html, $pos) : $html.$preview_html);

        Base_ActionBarCommon::add('delete',__('Delete logo'),$this->create_callback_href($this->delete_logo(...)));
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
