<?php
/**
 * TCPDFInstall class.
 *
 * This module uses TCPDF PHP class released under
 * GNU LESSER GENERAL PUBLIC LICENSE Version 2.1
 * Author: Nicola Asuni 
 * Copyright (c) 2001-2008: Nicola Asuni
 * 
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Janusz Tylek
 * @version 1.0
 * @license MIT
 * @package epesi-libs
 * @subpackage tcpdf
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Libs_TCPDFInstall extends ModuleInstall {
	public function install() {
		Base_ThemeCommon::install_default_theme($this->get_type());
		$this->create_data_dir();
		// admin()'s logo preview (TCPDF_0.php) renders the uploaded PDF logo as a plain
		// <img src="data/Libs_TCPDF/company_logo.png?..."> URL - override data/.htaccess's
		// blanket deny (see modules/Base/patches/20260908_protect_data_dir.php), but only for
		// this one filename: unlike MainModuleIndicator/CRM_Contacts_Photo, this directory also
		// holds generated PDFs (TCPDF_0.php's get_href()/full_path()) whose names are only an
		// md5 of path+user+CID+session_id - not a real secret, but not meant to be openly
		// listable/guessable either. A directory-wide allow would expose those too.
		file_put_contents($this->get_data_dir() . '.htaccess', <<<'HTACCESS'
<Files "company_logo.png">
    <ifModule mod_authz_core.c>
        Require all granted
    </ifModule>
    <ifModule !mod_authz_core.c>
        Allow from all
    </ifModule>
</Files>

HTACCESS
		);
		DB::CreateTable('libs_tcpdf_pdf_index',
						'created_on T,'.
						'filename C(32)',
						array('constraints'=>''));
		return true;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme($this->get_type());
		DB::DropTable('libs_tcpdf_pdf_index');
		return true;
	}

	public function version() {
		return array('3.1.001');
	}
	public function requires($v) {
		return array(
					array('name'=>Base_LangInstall::module_name(), 'version'=>0)
					);
	}
    public static function simple_setup() {
        return false;
    }
}

?>
