<?php
/**
 * Theme_Administrator class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
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
		
		$form = $this->init_module('Libs/QuickForm','Changing template');
		
		$themes = Base_Theme::list_themes();
		$form->addElement('header', 'install_module_header', __('Themes Administration'));
		$form->addElement('select', 'theme', __('Choose template'), $themes);

		$form->addElement('checkbox', 'preload_selected', __('Preload selected template images'));
		$form->addElement('checkbox', 'preload_default', __('Preload default template images'));
		
		$form->setDefaults(array(
			'theme'=>Variable::get('default_theme'),
			'preload_selected'=>Variable::get('preload_image_cache_selected'),
			'preload_default'=>Variable::get('preload_image_cache_default')
			));
		
		if($form->validate()) {
			$form->process(array(& $this, 'submit_admin'));
		} else {
			$form->display();
			
			if(class_exists('ZipArchive')) {
				$this->pack_module('Utils/FileUpload',array(array($this,'upload_template'),__('Upload template')));
//				Base_ActionBarCommon::add('edit',__('Manage templates'),$this->create_callback_href(array($this,'download_template')));
			}
		}
		Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
		Base_ActionBarCommon::add('save', __('Save'), $form->get_submit_form_href());

	}
	
	public function upload_template($file, $oryginal_file) {
		$zip = new ZipArchive;
		if ($zip->open($file) == 1) {
    			$zip->extractTo(DATA_DIR.'/Base_Theme/templates/');
			Base_StatusBarCommon::message(__('Template installed'));
    			return true;
		}
		Base_StatusBarCommon::message(__('Invalid template file'),'error');
		return true;
	}
	
	public function submit_admin($data) {
		Variable::set('default_theme',$data['theme']);
		Variable::set('preload_image_cache_default',isset($data['preload_default']));
		Variable::set('preload_image_cache_selected',isset($data['preload_selected']));
		Base_ThemeCommon::create_cache();
		Base_StatusBarCommon::message('Theme changed - reloading page');
		eval_js('setTimeout(\'document.location=\\\'index.php\\\'\',\'3000\')');
		return true;
	}
	
	public function download_template() {
		$ld = $this->get_data_dir().'list/';
		if(!file_exists($ld)) return $this->download_templates_list();
		if($this->is_back()) return false;
		Base_ActionBarCommon::add('back',__('Back'),$this->create_back_href());
		Base_ActionBarCommon::add('search',__('Update templates list'),$this->create_callback_href(array($this,'download_templates_list')));
		
		$m = $this->init_module('Utils/GenericBrowser',null,'new_templates');
 		$m->set_table_columns(array(array('name'=>'Name','search'=>1),array('name'=>'Version'),array('name'=>'Screenshot'),array('name'=>'Author','search'=>1),array('name'=>'Info','search'=>1),array('name'=>'Compatible')));
		
		$content = scandir($ld);
		foreach ($content as $template_name) {
			if ($template_name == '.' || $template_name == '..')
				continue;
		
			$ini = parse_ini_file($ld.$template_name.'/info.ini');
			
			$compatible = version_compare($ini['epesi_version'],EPESI_VERSION)<=0;
			$installed = is_dir(DATA_DIR.'/Base_Theme/templates/'.$template_name);
			if($installed) {
				$installed_ini = @parse_ini_file(DATA_DIR.'/Base_Theme/templates/'.$template_name.'/info.ini');
				if(!$installed_ini) $installed_ini = array('version'=>0);
			}
			
			if(isset($ini['screenshot'])) {
				$th_big = Utils_ImageCommon::create_thumb($ld.$template_name.'/'.$ini['screenshot'],640,480);
				$thumb = '<a href="'.$th_big['thumb'].'" rel="lyteshow">'.Utils_ImageCommon::get_thumb_html($ld.$template_name.'/'.$ini['screenshot'],120,120).'</a>';		
			} else $thumb='';
			
			$r = $m->get_new_row();
			$r->add_data($template_name,$ini['version'],$thumb,$ini['author'],$ini['info'],($compatible)?'<font color="green">yes</font>':'<font color="red">NO</font> epesi '.$ini['epesi_version'].' required');
			if($compatible && !$installed)
				$r->add_action($this->create_callback_href(array($this,'install_template'),$template_name),'Install');
			if($installed) {
				$r->add_action($this->create_callback_href(array($this,'delete_template'),$template_name),'Delete');
				if($ini['version']>$installed_ini['version'])
					$r->add_action($this->create_callback_href(array($this,'update_template'),$template_name),'Update');
			}
		}
		
 		$this->display_module($m,array(true),'automatic_display');

		return true;
	}
	
	public function download_templates_list() {
		if($this->is_back()) return false;
		$this->pack_module('Utils/FileDownload',array('http://www.epesi.org/themes_repo/index.php?list',array($this,'on_download_list')));
		return true;
	}
	
	public function on_download_list($tmp,$oryg) {
		$ld = $this->get_data_dir().'list/';
		@recursive_rmdir($ld);
		mkdir($ld);
		$zip = new ZipArchive();
		$zip->open($tmp);
		$zip->extractTo($ld);
		$this->set_back_location();
	}
	
	public function install_template($template_name) {
		if($this->is_back()) return false;
		$this->pack_module('Utils/FileDownload',array('http://www.epesi.org/themes_repo/index.php?'.http_build_query(array('get'=>$template_name)),array($this,'on_download_template')));
		return true;
	}

	public function on_download_template($tmp,$oryg) {
		$zip = new ZipArchive();
		$zip->open($tmp);
		$zip->extractTo(DATA_DIR.'/Base_Theme/templates/');
		$this->set_back_location();
	}
	
	public function delete_template($template_name) {
		recursive_rmdir(DATA_DIR.'/Base_Theme/templates/'.$template_name);
	}

	public function update_template($template_name) {
		if($this->is_back()) {
			$this->unset_module_variable('deleted');
			return false;
		}
		$del = $this->get_module_variable('deleted',false);
		$this->set_module_variable('deleted',true);
		if(!$del)
			recursive_rmdir(DATA_DIR.'/Base_Theme/templates/'.$template_name);
		
		$this->pack_module('Utils/FileDownload',array('http://www.epesi.org/themes/themes/index.php?'.http_build_query(array('get'=>$template_name)),array($this,'on_download_template')));
		return true;
	}
	
}
?>
