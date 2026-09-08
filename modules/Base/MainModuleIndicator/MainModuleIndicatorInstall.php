<?php
/**
 * MainModuleIndicatorInstall class.
 * 
 * This class provides initialization data for MainModuleIndicator module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage MainModuleIndicator
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_MainModuleIndicatorInstall extends ModuleInstall {
	public function install() {
		Variable::set('base_page_title','EPESI');
		Variable::set('show_caption_in_title','1');
		Variable::set('show_module_indicator','1');
		Variable::set('logo_file','');
        Variable::set('login_logo_file','');
		Base_ThemeCommon::install_default_theme(Base_MainModuleIndicatorInstall::module_name());
		$this->create_data_dir();
		// logo()/login_logo() (MainModuleIndicator_0.php) render the uploaded logo as a
		// plain <img src="data/Base_MainModuleIndicator/..."> URL (theme/logo.tpl,
		// theme/login-logo.tpl) - override data/.htaccess's blanket deny (see
		// modules/Base/patches/20260908_protect_data_dir.php) for just this directory.
		file_put_contents($this->get_data_dir() . '.htaccess', <<<'HTACCESS'
<ifModule mod_authz_core.c>
    Require all granted
</ifModule>
<ifModule !mod_authz_core.c>
    Allow from all
</ifModule>

HTACCESS
		);
		return true;
	}
	
	public function uninstall() {
		Variable::delete('logo_file');
        Variable::delete('login_logo_file');
		Variable::delete('base_page_title');
		Variable::delete('show_caption_in_title');
		Variable::delete('show_module_indicator');
		Base_ThemeCommon::uninstall_default_theme(Base_MainModuleIndicatorInstall::module_name());
		return true;
	}
	
	public function version() {
		return array('2.0');
	}
	public function requires($v) {
		return array(
			array('name'=>Base_BoxInstall::module_name(), 'version'=>0),
			array('name'=>Base_AdminInstall::module_name(), 'version'=>0),
			array('name'=>Base_LangInstall::module_name(), 'version'=>0),
			array('name'=>Libs_QuickFormInstall::module_name(), 'version'=>0),
			array('name'=>Base_ThemeInstall::module_name(), 'version'=>0));
	}

	public static function simple_setup() {
		return __('Epesi Core');
	}
}

?>
