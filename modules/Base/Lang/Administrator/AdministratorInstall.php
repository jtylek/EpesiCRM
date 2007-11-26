<?php
/**
 * Lang_AdministratorInstall class.
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license SPL
 * @package epesi-base-extra
 * @subpackage lang-administrator
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Lang_AdministratorInstall extends ModuleInstall {
	public function version() {
		return array('1.0.0');
	}

	public function install() {
		return Variable::set('allow_lang_change',true);
		Base_ThemeCommon::install_default_theme($this->get_type());
	}

	public function uninstall() {
		return Variable::delete('allow_lang_change');
		Base_ThemeCommon::uninstall_default_theme($this->get_type());
	}
	public function requires($v) {
		return array(
			array('name'=>'Base/Admin','version'=>0),
			array('name'=>'Base/Acl','version'=>0),
			array('name'=>'Base/Theme','version'=>0),
			array('name'=>'Libs/QuickForm','version'=>0),
			array('name'=>'Base/User','version'=>0),
			array('name'=>'Utils/GenericBrowser','version'=>0),
			array('name'=>'Base/User/Settings','version'=>0), // TODO: not required directly but needed to make this module fully operational. Should we delete the requirement?
			array('name'=>'Base/Lang','version'=>0));
	}

	public static function post_install() {
		$ls_langs = scandir('data/Base_Lang');
		$langs = array();
		foreach ($ls_langs as $entry)
			if (ereg('.\.php$', $entry)) {
				$lang = substr($entry,0,-4);
				$langs[$lang] = $lang;
			}
		return array(
				array('name'=>'allow_change','label'=>'Allow users to change language','type'=>'checkbox','values'=>null,'default'=>true),
				array('name'=>'lang_code','label'=>'Default epesi language','type'=>'select','values'=>$langs,'default'=>'en')
			);
	}

	public static function post_install_process($v) {
		Variable::set('default_lang',$v['lang_code']);
		Variable::set('allow_lang_change',isset($v['allow_change']) && $v['allow_change']);
	}
}

?>
