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
	const translation_server_url = 'http://translate.epesibim.com';

	public static function admin_caption() {
		return array('label'=>__('Language & Translations'), 'section'=>__('Regional Settings'));
	}

	public static function admin_access_levels() {
		return array(
			'select_language'=>array('label'=>__('Select language'), 'default'=>1),
			'enable_users_to_select'=>array('label'=>__('Enable users to select language'), 'default'=>1),
			'translate'=>array('label'=>__('Custom translations'), 'default'=>1),
			'new_langpack'=>array('label'=>__('New language pack'), 'default'=>1)
		);
	}

	public static function user_settings($just_retrieve = false){
		if(!Variable::get('allow_lang_change')) return null;
		if(DEMO_MODE && Base_UserCommon::get_my_user_login()=='admin') {
			$langs = array('en'=>'en');
		} else {
			$langs = Base_LangCommon::get_installed_langs();
		}
		if ($just_retrieve) {
			$group = $label1 = $label2 = '';
		} else {
			$group = __('Regional Settings');
			$label1 = __('Language');
			$label2 = __('Language you want to use');
		}
		return array($group=>array(
			array('type'=>'header','label'=>$label1,'name'=>null),
			array('name'=>'language','label'=>$label2,'type'=>'select','values'=>$langs,'default'=>Variable::get('default_lang'))
			));
	}
	
	public static function allow_sending($flush=false) {
		static $cache = -1;
		if ($cache===-1 || $flush) $cache = DB::GetOne('SELECT allow FROM base_lang_trans_contrib WHERE user_id=%d',Acl::get_user());
		return $cache;
	}
	
	public static function send_translation($lang, $org, $trans) {
		if (!self::allow_sending()) return false;
		$ip = gethostbyname($_SERVER['SERVER_NAME']);
		$r = DB::GetRow('SELECT * FROM base_lang_trans_contrib WHERE user_id=%d', array(Acl::get_user()));
		$q = array('first_name'=>$r['first_name'], 'last_name'=>$r['last_name'], 'lang'=>$lang, 'ip'=>$ip, 'original'=>$org, 'translation'=>$trans, 'credits'=>$r['credits'], 'credits_website'=>$r['credits_website'], 'contact_email'=>$r['contact_email']);
		file_get_contents(self::translation_server_url.'/translations.php?'.http_build_query($q));
	}
}

?>