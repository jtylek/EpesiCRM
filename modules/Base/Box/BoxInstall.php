<?php
/**
 * BoxInit class.
 *
 * This class provides initialization of Box module.
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license SPL
 * @package epesi-base-extra
 * @subpackage box
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_BoxInstall extends ModuleInstall {

	public function install() {
		Base_LangCommon::install_translations($this->get_type());
		Base_ThemeCommon::install_default_theme('Base/Box');

		return true;
	}

	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('Base/Box');

		return true;
	}

	public function version() {
		return array('1.0.0');
	}

	public function requires($v) {
		return array (
			array('name'=>'Base/Lang', 'version'=>0),
			array('name'=>'Base/Setup', 'version'=>0),
			array('name'=>'Utils/Tooltip', 'version'=>0),
			array('name'=>'Base/Acl', 'version'=>0),
			array('name'=>'Base/Theme/Administrator', 'version'=>0)
		);
	}
}
?>
