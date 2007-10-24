<?php
/**
 * Lang class.
 * 
 * This class provides translations manipulation.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license SPL
 * @package epesi-base-extra
 * @subpackage lang
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides translations manipulation.
 * Translation files are kept in 'modules/Lang/translations'. 
 * Http server user should have write access to those files.
 */
class Base_LangCommon extends ModuleCommon {
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
	 public static function ts($group, $original, array $arg=array()) {
		global $translations;
		$group = str_replace('/','_',$group);
		
		if(!isset($translations)) {
			$translations = array();
			include_once('data/Base_Lang/'.self::get_lang_code().'.php');
		}
		
		if(!array_key_exists($group, $translations) || 
			!array_key_exists($original, $translations[$group])) {
			$translations[$group][$original] = '';
			//only first display of the string is not in translations database... slows down loading of the page only once...
			self::save();
		}
		$trans = $translations[$group][$original];

		if(!isset($trans) || $trans=='') $trans = $original;

		$trans = vsprintf($trans,$arg);
		return $trans;
	}

	/**
	 * For internal use only.
	 */
	public static function save($lang = null) {
		global $translations;
		//save translations file
		if (!isset($lang)) $lang = self::get_lang_code();
		$f = @fopen('data/Base_Lang/'.$lang.'.php', 'w');
		if(!$f)	return false;
		
		fwrite($f, "<?php\n");
		fwrite($f, "/**\n * Translation file.\n * @package epesi-translations\n * @subpackage $lang\n */\n");
		fwrite($f, 'global $translations;'."\n");
		foreach($translations as $p=>$xxx) 
			foreach($xxx as $k=>$v)
				fwrite($f, '$translations[\''.addcslashes($p,'\\\'').'\'][\''.addcslashes($k,'\\\'').'\']=\''.addcslashes($v,'\\\'')."';\n");
		
		fwrite($f, '?>');
		fclose($f);
		return true;
	}
	
	/**
	 * For internal use only.
	 */
	public static function load() {
		if(!@include_once('data/Base_Lang/'.self::get_lang_code().'.php')) {
			global $translations;
			$translations=array();
		}
	}
	
	public static function get_lang_code() {
		static $lang_code;
		if(!isset($lang_code)) {
			if (!Acl::check('Data','View') ||
				ModuleManager::is_installed('Base/User/Settings')==-1 || 
				ModuleManager::is_installed('Base/Lang/Administrator')==-1 || 
				(ModuleManager::is_installed('Base/Lang/Administrator')!=-1 && !Variable::get('allow_lang_change'))) 
					return Variable::get('default_lang');
			if(class_exists('Base_User_SettingsCommon'))
				$lang_code = Base_User_SettingsCommon::get('Base_Lang_Administrator','language');
		}
		return $lang_code;
	}
	
	/**
	 * For internal use only.
	 */
	public static function install_translations($mod_name,$lang_dir='lang') {
		global $translations;
		$mod_name = str_replace('/','_',$mod_name);
		$directory = 'modules/'.str_replace('_','/',$mod_name).'/'.$lang_dir;
		$content = scandir($directory);
		$trans_backup = $translations;
		foreach ($content as $name){
			if($name == '.' || $name == '..' || ereg('^[\.~]',$name)) continue;
			$langcode = substr($name,0,strpos($name,'.'));
			$translations = array();
			@include_once('data/Base_Lang/'.$langcode.'.php');
			include_once($directory.'/'.$name);
			Base_LangCommon::save($langcode);
		}
		$translations = $trans_backup; 
	}

	/**
	 * For internal use only.
	 */
	public static function new_langpack($code) {
		file_put_contents('data/Base_Lang/'.$code.'.php','');
	}

	/**
	 * For internal use only.
	 */
	public static function get_langpack($code) {
		global $translations;
		if (!is_file('data/Base_Lang/'.$code.'.php')) return false;
		include_once('data/Base_Lang/'.$code.'.php');
		$langpack = $translations;
		include_once('data/Base_Lang/'.self::get_lang_code().'.php');
		return $langpack;
	}
}

on_init(array('Base_LangCommon','load'));
?>
