<?php
/**
 * Lang_Administrator class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage lang-administrator
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Lang_AdministratorCommon extends Base_AdminModuleCommon {
	public static function admin_caption() {
		return 'Language & Translations';
	}

	public static function user_settings(){
		if(!Variable::get('allow_lang_change')) return null;
		$ls_langs = explode(',',@file_get_contents(DATA_DIR.'/Base_Lang/cache'));
		$langs = array_combine($ls_langs,$ls_langs);
		return array('Regional settings'=>array(
			array('name'=>'language','label'=>'Language you want to use','type'=>'select','values'=>$langs,'default'=>Variable::get('default_lang'))
			));
	}
	
}

?>