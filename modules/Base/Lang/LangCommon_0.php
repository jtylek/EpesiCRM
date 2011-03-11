<?php
/**
 * Lang class.
 *
 * This class provides translations manipulation.
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
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
		if (!$original) return '';
		global $translations;
		global $custom_translations;
		if(is_string($group))
			$group = str_replace(array('/','\\'),'_',$group);
		elseif($group instanceof Module)
			$group = $group->get_type();
		else
			trigger_error('Invalid argument passed to Base_LangCommon::ts');

		if(!isset($translations))
			self::load();

		if(!array_key_exists($group, $translations) ||
			!array_key_exists($original, $translations[$group])) {
			$translations[$group][$original] = '';
			//only first display of the string is not in translations database... slows down loading of the page only once...
			self::save();
		}
		
		if (isset($custom_translations[$group][$original]))
			$trans = $custom_translations[$group][$original];
		else {
			$trans_oryg = $translations[$group][$original];
			if(!isset($trans_oryg) || $trans_oryg=='') $trans = $original;
				else $trans=$trans_oryg;
		}

		$trans = @vsprintf($trans,$arg);
		if ($original && !$trans) $trans = '<b>Invalid translation, misused char % (use double %%)</b>';
		
		return $trans;
	}

	/**
	 * For internal use only.
	 */
	public static function save($lang = null) {
		global $translations;
		global $custom_translations;
		//save translations file
		if (!isset($lang)) $lang = self::get_lang_code();
		$f = @fopen(DATA_DIR.'/Base_Lang/base/'.$lang.'.php', 'w');
		if(!$f)	return false;

		fwrite($f, "<?php\n");
		fwrite($f, "/**\n * Translation file.\n * @package epesi-translations\n * @subpackage $lang\n */\n");
		fwrite($f, 'global $translations;'."\n");
		foreach($translations as $p=>$xxx)
			foreach($xxx as $k=>$v)
				fwrite($f, '$translations[\''.addcslashes($p,'\\\'').'\'][\''.addcslashes($k,'\\\'').'\']=\''.addcslashes($v,'\\\'')."';\n");

		fwrite($f, '?>');
		fclose($f);

		if (isset($custom_translations)) {
			$f = @fopen(DATA_DIR.'/Base_Lang/custom/'.$lang.'.php', 'w');
			if(!$f)	return false;

			fwrite($f, "<?php\n");
			fwrite($f, "/**\n * Translation file - custom translations.\n * @package epesi-translations\n * @subpackage $lang\n */\n");
			fwrite($f, 'global $custom_translations;'."\n");
			foreach($custom_translations as $p=>$xxx)
				foreach($xxx as $k=>$v)
					fwrite($f, '$custom_translations[\''.addcslashes($p,'\\\'').'\'][\''.addcslashes($k,'\\\'').'\']=\''.addcslashes($v,'\\\'')."';\n");

			fwrite($f, '?>');
			fclose($f);
		}
		return true;
	}

	/**
	 * For internal use only.
	 */
	public static function load() {
		@include_once(DATA_DIR.'/Base_Lang/base/'.self::get_lang_code().'.php');
		global $translations;
		if(!is_array($translations))
			$translations=array();

		@include_once(DATA_DIR.'/Base_Lang/custom/'.self::get_lang_code().'.php');
		global $custom_translations;
		if(!is_array($custom_translations))
			$custom_translations=array();
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
		$directory = 'modules/'.str_replace('_','/',$mod_name).'/'.$lang_dir;
		if (!is_dir($directory)) return;
		$content = scandir($directory);
		$trans_backup = $translations;
		foreach ($content as $name){
			if($name == '.' || $name == '..' || preg_match('/^[\.~]/',$name)) continue;
			$langcode = substr($name,0,strpos($name,'.'));
			$translations = array();
			@include(DATA_DIR.'/Base_Lang/base/'.$langcode.'.php');
			include($directory.'/'.$name);
			Base_LangCommon::save($langcode);
		}
		$translations = $trans_backup;
		self::refresh_cache();
	}

	/**
	 * For internal use only.
	 */
	public static function new_langpack($code) {
		file_put_contents(DATA_DIR.'/Base_Lang/base/'.$code.'.php','');
		file_put_contents(DATA_DIR.'/Base_Lang/custom/'.$code.'.php','');
		self::refresh_cache();
	}

	/**
	 * For internal use only.
	 */
	public static function get_langpack($code) {
		global $translations;
		if (!is_file(DATA_DIR.'/Base_Lang/base/'.$code.'.php')) return false;
		include_once(DATA_DIR.'/Base_Lang/base/'.$code.'.php');
		$langpack = $translations;
		include_once(DATA_DIR.'/Base_Lang/base/'.self::get_lang_code().'.php');
		return $langpack;
	}

	public static function refresh_cache() {
		$ls_langs = scandir(DATA_DIR.'/Base_Lang/base');
		$langs = array();
		foreach ($ls_langs as $entry)
			if (preg_match('/.\.php$/i', $entry)) {
				$lang = substr($entry,0,-4);
				$langs[] = $lang;
			}
		file_put_contents(DATA_DIR.'/Base_Lang/cache',implode(',',$langs));
	}
	
// ********************************************************************\
// Translation of text with one argument only
// The directory is parsed using debug_backtrace

	 public static function t($original, array $arg=array()) {
		// Get a directory of a script from which the function was called
		$gr = self::get_type_with_bt(1);
		return self::ts($gr,$original,$arg);
	}

// ********************************************************************\
// Translation of group of text
// The directory is parsed using debug_backtrace
/*
	 public static function TranslateGroup($textarray) {
		global $translations;
		// Get a directory of a script from which the function was called
		$call_dir=debug_backtrace();
		$dirname = dirname($call_dir[0]['file']);
		// extract module name
		$pos=strrpos($dirname,'modules')+8;
		$group = substr($dirname,$pos);
		
		$group = str_replace(array('/','\\'),'_',$group);

		if(!isset($translations)) {
			$translations = array();
			include_once(DATA_DIR.'/Base_Lang/'.self::get_lang_code().'.php');
		}

		
		return $textarray;
		
		$texttotranslate=array();
		foreach ($textarray as $line){
		// We need: LABEL, Text, $args
		//	foreach ($line as $text){
				$texttotranslate['group']=$group;
				$texttotranslate['label']=$line[0];
				$texttotranslate['text']=$line[1];
		//		$texttotranslate['arg']=$text[2];
		//	}
		} // end of foreach
		
		return $texttotranslate;
		
		if(!array_key_exists($group, $translations) ||
			!array_key_exists($original, $translations[$group])) {
			$translations[$group][$original] = '';
			//only first display of the string is not in translations database... slows down loading of the page only once...
			self::save();
		}
		$trans = $translations[$group][$original];

		if(!isset($trans) || $trans=='') $trans = $original;

		$trans = @vsprintf($trans,$arg);
		if ($trans=='' && $original) $trans = 'Invalid string to translate: '.$trans;


		return $trans;
	}
*/
}

Module::register_method('t',array('Base_LangCommon','ts')); // interactive ts
Module::register_method('ht',array('Base_LangCommon','ts')); // DEPRECATED
on_init(array('Base_LangCommon','load'));
?>
