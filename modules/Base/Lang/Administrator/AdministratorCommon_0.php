<?php
/**
 * Lang_Administrator class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Lang_AdministratorCommon extends Base_AdminModuleCommon {
	public static function admin_caption() {
		return 'Language & Translations';
	}

	public static function user_settings(){
		if(!Variable::get('allow_lang_change')) return null;
		$ls_langs = scandir('data/Base/Lang');
		$langs = array();
		foreach ($ls_langs as $entry)
			if (ereg('.\.php$', $entry)) {
				$lang = substr($entry,0,-4);
				$langs[$lang] = $lang;
			}
		return array('Language'=>array(
			array('name'=>'language','label'=>'Language you want to use','values'=>$langs,'default'=>Variable::get('default_lang'))
			));
	}
	
}

?>