<?php
/**
 * Lang class.
 * 
 * This class provides translations manipulation.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides translations manipulation.
 * Translation files are kept in 'modules/Lang/translations'. 
 * Http server user should have write access to those files.
 * 
 * @package epesi-base-extra
 * @subpackage lang
 */
class Base_LangCommon {
	/**
	 * Use this function to translate desired string. Translation link is never shown.
	 * This function can be used with static functions, it doesn't require
	 * packing of 'Lang' module inside other.
	 * Example
	 * <pre>
	 * print(self::ts('group or parent module', 'some text'));
	 * </pre>
	 * Note that it's slower then t function.
	 * 
	 * 
	 * @param string group or parent module
	 * @param string string to translate
	 * @return string
	 */
	 public static function ts($group, $original) {
		global $translations;
		$group = str_replace('/','_',$group);
		
		if(!isset($translations)) {
			$translations = array();
			include_once('data/Base/Lang/'.self::get_lang_code().'.php');
		}
		
		if(!array_key_exists($group, $translations) || 
			!array_key_exists($original, $translations[$group])) {
			$translations[$group][$original] = '';
			//only first display of the string is not in translations database... slows down loading of the page only once...
			self::save();
		}
		$trans = $translations[$group][$original];
		if(!isset($trans) || $trans=='') $trans = $original;

		return $trans;
	}

	public static function save() {
		global $translations;
		//save translations file
		$lang = self::get_lang_code();
		$f = @fopen('data/Base/Lang/'.$lang.'.php', 'w');
		if(!$f)	return false;
		
		fwrite($f, "<?php\n");
		fwrite($f, "/**\n * Translation file.\n * @package epesi-translations\n * @subpackage $lang\n */\n");
		fwrite($f, 'global $translations;'."\n");
		foreach($translations as $p=>$xxx) 
			foreach($xxx as $k=>$v)
				fwrite($f, '$translations[\''.addslashes($p).'\'][\''.addslashes($k).'\']=\''.addslashes($v)."';\n");
		
		fwrite($f, '?>');
		fclose($f);
		return true;
	}
	
	public static function get_lang_code() {
		static $lang_code;
		if(!isset($lang_code)) {
//			if(array_key_exists('lang',$_SESSION))
//				$lang_code = $_SESSION['lang'];
//			else
//				$lang_code = Variable::get('default_lang');
			if (ModuleManager::is_installed('Base/User/Settings')==-1 || ModuleManager::is_installed('Base/Lang/Administrator')==-1 || (ModuleManager::is_installed('Base/Lang/Administrator')!=-1 && !Variable::get('allow_lang_change'))) return Variable::get('default_lang');
			$lang_code = Base_User_SettingsCommon::get_user_settings('Base_Lang_Administrator','language');
		}
		return $lang_code;
	}
	
}
?>
