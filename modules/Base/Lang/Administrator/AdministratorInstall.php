<?php
/**
 * Lang_AdministratorInstall class.
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage lang-administrator
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Lang_AdministratorInstall extends ModuleInstall {
	public function version() {
		return array('1.0.0');
	}

	public function install() {
		DB::CreateTable('base_lang_trans_contrib',
			'id I4 AUTO KEY,'.
			'user_id I4,'.
			'allow I1,'.
			'first_name C(64),'.
			'last_name C(64),'.
			'credits I1,'.
			'credits_website C(128),'.
			'contact_email C(128)',
			array());
		Base_ThemeCommon::install_default_theme($this->get_type());
		return Variable::set('allow_lang_change',true);
	}

	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme($this->get_type());
		return Variable::delete('allow_lang_change');
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
		$ls_langs = scandir(DATA_DIR.'/Base_Lang/base');
		$langs = array();
		foreach ($ls_langs as $entry)
			if (preg_match('/.\.php$/i', $entry)) {
				$lang = substr($entry,0,-4);
				$langs[$lang] = $lang;
			}
		return array(
				array('name'=>'allow_change','label'=>__('Allow users to change language'),'type'=>'checkbox','values'=>null,'default'=>true),
				array('name'=>'lang_code','label'=>__('Default EPESI language'),'type'=>'select','values'=>$langs,'default'=>'en')
			);
	}

	public static function post_install_process($v) {
		Variable::set('default_lang',$v['lang_code']);
		Variable::set('allow_lang_change',isset($v['allow_change']) && $v['allow_change']);
	}

	public static function simple_setup() {
		return __('EPESI Core');
	}
}

?>
