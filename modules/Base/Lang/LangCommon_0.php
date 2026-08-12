<?php
/**
 * Lang class.
 *
 * This class provides translations manipulation.
 *
 * @author Paul Bukowski
 * @copyright Copyright &copy; 2006-2022 by Janusz Tylek
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
	 * Don not use this function to translate, use the __() call instead.
	 */
	public static function t($original, array $arg=array()) { return self::translate(null,$original,$arg); }
	/**
	 * Don not use this function to translate, use the __() call instead.
	 */
	public static function ts($group, $original, array $arg=array()) { return self::translate($original, $arg); }
	/**
	 * Don not use this function to translate, use the __() call instead.
	 */
	public static function translate($original, array $arg=array(), $translate = true) {
		if (!$original) return '';
//		if ($original[0]=='*') trigger_error('Re-translation '.$original);
		global $translations;
		global $custom_translations;

		if(!isset($translations))
			self::load();

		if(isset($translations[$original]) && $translations[$original] && $translate)
			$translated = $translations[$original];
		else
			$translated = $original;

		if (isset($custom_translations[$original]) && $custom_translations[$original] && $translate)
			$translated = $custom_translations[$original];

		$translated = @vsprintf($translated,$arg);
		if ($original && !$translated) $translated = '<b>Invalid translation, misused char % (use double %%)</b>';
		
		return $translated;
	}
	
	public static function print_flag($code, $label, $href='') {
		$file = 'modules/Base/Lang/theme/flags/'.$code.'.svg';
		if (!file_exists($file))
			$file = 'modules/Base/Lang/theme/flag_placeholder.png';
		print(	'<a '.$href.' class="flag_button">'.
					'<img class="flag" src="'.$file.'" />'.
					'<span class="label">'.$label.'</span>'.
				'</a>');
	}

    public static function get_base_languages() {
        $files = scandir('modules/Base/Lang/lang');
        $langs = array();
        $all_langs = self::get_all_languages();
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) == 'php') {
                $lang_code = pathinfo($file, PATHINFO_FILENAME);
                if (isset($all_langs[$lang_code])) {
                    $langs[$lang_code] = $all_langs[$lang_code];
                }
            }
        }
        return $langs;
    }

    public static function get_all_languages() {
        return array (
            'ab' => 'Abkhaz',
            'aa' => 'Afar',
            'af' => 'Afrikaans',
            'ak' => 'Akan',
            'sq' => 'Albanian',
            'am' => 'Amharic',
            'ar' => 'Arabic',
            'an' => 'Aragonese',
            'hy' => 'Armenian',
            'as' => 'Assamese',
            'av' => 'Avaric',
            'ae' => 'Avestan',
            'ay' => 'Aymara',
            'az' => 'Azerbaijani',
            'bm' => 'Bambara',
            'ba' => 'Bashkir',
            'eu' => 'Basque',
            'be' => 'Belarusian',
            'bn' => 'Bengali',
            'bh' => 'Bihari',
            'bi' => 'Bislama',
            'bs' => 'Bosnian',
            'br' => 'Breton',
            'bg' => 'Bulgarian',
            'my' => 'Burmese',
            'ca' => 'Catalan',
            'ch' => 'Chamorro',
            'ce' => 'Chechen',
            'ny' => 'Chichewa',
            'zh' => 'Chinese',
            'cv' => 'Chuvash',
            'kw' => 'Cornish',
            'co' => 'Corsican',
            'cr' => 'Cree',
            'hr' => 'Croatian',
            'cs' => 'Czech',
            'da' => 'Danish',
            'dv' => 'Divehi',
            'nl' => 'Dutch',
            'dz' => 'Dzongkha',
            'en' => 'English',
            'eo' => 'Esperanto',
            'et' => 'Estonian',
            'ee' => 'Ewe',
            'fo' => 'Faroese',
            'fj' => 'Fijian',
            'fi' => 'Finnish',
            'fr' => 'French',
            'ff' => 'Fula',
            'gl' => 'Galician',
            'ka' => 'Georgian',
            'de' => 'German',
            'el' => 'Greek',
            'gn' => 'Guaraní',
            'gu' => 'Gujarati',
            'ht' => 'Haitian',
            'ha' => 'Hausa',
            'he' => 'Hebrew',
            'hz' => 'Herero',
            'hi' => 'Hindi',
            'ho' => 'Hiri Motu',
            'hu' => 'Hungarian',
            'ia' => 'Interlingua',
            'id' => 'Indonesian',
            'ie' => 'Interlingue',
            'ga' => 'Irish',
            'ig' => 'Igbo',
            'ik' => 'Inupiaq',
            'io' => 'Ido',
            'is' => 'Icelandic',
            'it' => 'Italian',
            'iu' => 'Inuktitut',
            'ja' => 'Japanese',
            'jv' => 'Javanese',
            'kl' => 'Kalaallisut',
            'kn' => 'Kannada',
            'kr' => 'Kanuri',
            'ks' => 'Kashmiri',
            'kk' => 'Kazakh',
            'km' => 'Khmer',
            'ki' => 'Kikuyu',
            'rw' => 'Kinyarwanda',
            'ky' => 'Kyrgyz',
            'kv' => 'Komi',
            'kg' => 'Kongo',
            'ko' => 'Korean',
            'ku' => 'Kurdish',
            'kj' => 'Kwanyama',
            'la' => 'Latin',
            'lb' => 'Luxembourgish',
            'lg' => 'Ganda',
            'li' => 'Limburgish',
            'ln' => 'Lingala',
            'lo' => 'Lao',
            'lt' => 'Lithuanian',
            'lu' => 'Luba-Katanga',
            'lv' => 'Latvian',
            'gv' => 'Manx',
            'mk' => 'Macedonian',
            'mg' => 'Malagasy',
            'ms' => 'Malay',
            'ml' => 'Malayalam',
            'mt' => 'Maltese',
            'mi' => 'Māori',
            'mr' => 'Marathi (Marāṭhī)',
            'mh' => 'Marshallese',
            'mn' => 'Mongolian',
            'na' => 'Nauru',
            'nv' => 'Navajo',
            'nb' => 'Norwegian Bokmål',
            'nd' => 'North Ndebele',
            'ne' => 'Nepali',
            'ng' => 'Ndonga',
            'nn' => 'Norwegian Nynorsk',
            'no' => 'Norwegian',
            'ii' => 'Nuosu',
            'nr' => 'South Ndebele',
            'oc' => 'Occitan',
            'oj' => 'Ojibwe',
            'cu' => 'Old Church Slavonic',
            'om' => 'Oromo',
            'or' => 'Oriya',
            'os' => 'Ossetian',
            'pa' => 'Panjabi',
            'pi' => 'Pāli',
            'fa' => 'Persian',
            'pl' => 'Polish',
            'ps' => 'Pashto',
            'pt' => 'Portuguese - Brazil',
            'pt_PT' => 'Portuguese - Portugal',
            'qu' => 'Quechua',
            'rm' => 'Romansh',
            'rn' => 'Kirundi',
            'ro' => 'Romanian',
            'ru' => 'Russian',
            'sa' => 'Sanskrit (Saṁskṛta)',
            'sc' => 'Sardinian',
            'sd' => 'Sindhi',
            'se' => 'Northern Sami',
            'sm' => 'Samoan',
            'sg' => 'Sango',
            'sr' => 'Serbian',
            'gd' => 'Scottish Gaelic',
            'sn' => 'Shona',
            'si' => 'Sinhala',
            'sk' => 'Slovak',
            'sl' => 'Slovene',
            'so' => 'Somali',
            'st' => 'Southern Sotho',
            'es' => 'Spanish',
            'su' => 'Sundanese',
            'sw' => 'Swahili',
            'ss' => 'Swati',
            'sv' => 'Swedish',
            'ta' => 'Tamil',
            'te' => 'Telugu',
            'tg' => 'Tajik',
            'th' => 'Thai',
            'ti' => 'Tigrinya',
            'bo' => 'Tibetan Standard',
            'tk' => 'Turkmen',
            'tl' => 'Tagalog',
            'tn' => 'Tswana',
            'to' => 'Tonga',
            'tr' => 'Turkish',
            'ts' => 'Tsonga',
            'tt' => 'Tatar',
            'tw' => 'Twi',
            'ty' => 'Tahitian',
            'ug' => 'Uighur',
            'uk' => 'Ukrainian',
            'ur' => 'Urdu',
            'uz' => 'Uzbek',
            've' => 'Venda',
            'vi' => 'Vietnamese',
            'vo' => 'Volapük',
            'wa' => 'Walloon',
            'cy' => 'Welsh',
            'wo' => 'Wolof',
            'fy' => 'Western Frisian',
            'xh' => 'Xhosa',
            'yi' => 'Yiddish',
            'yo' => 'Yoruba',
            'za' => 'Zhuang',
            'zu' => 'Zulu',
        );
    }

	/**
	 * Writes a custom translation override into
	 * data/Base_Lang/custom/<module>/<code>.php (created on first write, never
	 * inside modules/ - that tree is shipped source, this is per-instance
	 * data). Re-saving an already-overridden key replaces its value in place
	 * rather than appending a new line, so the file never grows a duplicate
	 * per edit. Clears that language's merged cache so the edit is visible
	 * on the next request.
	 */
	public static function append_custom($module, $lang, $arr) {
		if (!$module || !$lang) return false;
		$dir = DATA_DIR.'/Base_Lang/custom/'.$module;
		if (!is_dir($dir) && !@mkdir($dir, 0777, true)) return false;
		$file = $dir.'/'.$lang.'.php';

		// Read the existing overrides before opening the write handle below -
		// include() opens its own handle on $file, which on Windows gets
		// denied while a second handle on the same file holds flock(LOCK_EX).
		global $custom_translations;
		$backup = $custom_translations;
		$custom_translations = array();
		if (file_exists($file)) {
			ob_start();
			include($file);
			ob_get_clean();
		}
		$merged = array_merge($custom_translations, $arr);
		$custom_translations = $backup;

		$f = @fopen($file, 'w');
		if (!$f) return false;
		if (flock($f, LOCK_EX)) {
			fwrite($f, "<?php\n".'global $custom_translations;'."\n");
			foreach ($merged as $k=>$v)
				fwrite($f, '$custom_translations[\''.addcslashes($k,'\\\'').'\']=\''.addcslashes($v,'\\\'')."';\n");
			flock($f, LOCK_UN);
		}
		fclose($f);

		Cache::clear('lang_merged_'.$lang);
		return true;
	}

	/**
	 * Merges every installed module's lang/<code>.php (shipped defaults, read
	 * from modules/) and data/Base_Lang/custom/<module>/<code>.php
	 * (per-instance overrides, read from data/ - never modules/) for the
	 * given language, the same way module code itself is merged per request
	 * via ModuleManager::get_load_priority_array() - no build step, no
	 * on-disk cache file.
	 *
	 * @return array [$translations, $custom_translations, $translation_module]
	 */
	private static function build_merge($lang_code) {
		global $translations, $custom_translations;
		$translations_backup = $translations;
		$custom_backup = $custom_translations;

		$merged_translations = array();
		$merged_custom = array();
		$translation_module = array();

		$modules = ModuleManager::get_load_priority_array();
		if ($modules) foreach ($modules as $row) {
			$module = $row['name'];
			$dir = 'modules/'.str_replace('_','/',$module).'/lang';

			$file = $dir.'/'.$lang_code.'.php';
			if (file_exists($file)) {
				$translations = array();
				ob_start();
				include($file);
				ob_get_clean();
				foreach ($translations as $k=>$v) {
					$merged_translations[$k] = $v;
					$translation_module[$k] = $module;
				}
			}

			$custom_file = DATA_DIR.'/Base_Lang/custom/'.$module.'/'.$lang_code.'.php';
			if (file_exists($custom_file)) {
				$custom_translations = array();
				ob_start();
				include($custom_file);
				ob_get_clean();
				foreach ($custom_translations as $k=>$v)
					$merged_custom[$k] = $v;
			}
		}

		$translations = $translations_backup;
		$custom_translations = $custom_backup;

		return array($merged_translations, $merged_custom, $translation_module);
	}

	/**
	 * For internal use only.
	 */
	public static function load($lang_code = null) {
		global $translations;
		global $custom_translations;
        self::$lang_code = $lang_code;
        if ($lang_code === null)
            $lang_code = self::get_lang_code();
		if (!$lang_code) return;

		$cached = Cache::get('lang_merged_'.$lang_code);
		if ($cached === null) {
			$cached = self::build_merge($lang_code);
			Cache::set('lang_merged_'.$lang_code, $cached);
		}
		list($translations, $custom_translations, self::$translation_module) = $cached;

		self::$loaded = true;
		eval_js_once('Epesi.default_indicator="'.__('Loading...').'";');
	}

	/**
	 * Returns the module that owns the given original string in the
	 * currently loaded language, or null if unknown - used by the
	 * translation admin UI to know which module's lang/<code>_custom.php
	 * an edit belongs in.
	 */
	public static function get_translation_module($original) {
		return self::$translation_module[$original] ?? null;
	}

	private static $translation_module = array();
	private static $lang_code;
	private static $loaded = false;

    public static function detect_and_load_language()
    {
        $browser_lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null;
        if ($browser_lang) {
            $browser_lang = substr($browser_lang, 0, 2); // get first two characters
            if (in_array($browser_lang, self::get_installed_langs())) {
                Base_LangCommon::load($browser_lang);
            }
        }
    }

	public static function get_lang_code() {
		if(defined('FORCE_LANG_CODE')) return FORCE_LANG_CODE;
		if(!isset(self::$lang_code)) {
			if (!Base_AclCommon::is_user() ||
				Base_User_SettingsInstall::is_installed() == false ||
				!Variable::get('allow_lang_change', false))
					return Variable::get('default_lang');
			if(class_exists('Base_User_SettingsCommon'))
				self::$lang_code = Base_User_SettingsCommon::get('Base_Lang_Administrator','language');
		}
		return self::$lang_code;
	}

	/**
	 * Registers a new language code as installed. There's nothing to seed
	 * on disk for it - a language with no per-module custom files yet is
	 * already valid, it just falls back to defaults everywhere.
	 */
	public static function new_langpack($code) {
		$codes = self::get_installed_lang_codes();
		if (!in_array($code, $codes)) {
			$codes[] = $code;
			Variable::set('installed_langs', implode(',', $codes));
		}
	}

	/**
	 * For internal use only.
	 */
	public static function get_langpack($code, $s='base') {
		if (!in_array($code, self::get_installed_lang_codes())) return array();
		$merged = self::build_merge($code);
		return $s === 'base' ? $merged[0] : $merged[1];
	}

	public static function get_installed_langs() {
        $all_langs = self::get_all_languages();
        $ret = array();
        foreach (self::get_installed_lang_codes() as $lang_code) {
            if (isset($all_langs[$lang_code]))
                $ret[$lang_code] = $all_langs[$lang_code] . " ($lang_code)";
        }
		return $ret;
	}

	private static function get_installed_lang_codes() {
		$raw = Variable::get('installed_langs', '');
		if ($raw === '') {
			// Installations that already had Base_Lang installed before this
			// per-module rewrite never ran the new install() seeding step -
			// self-heal once by seeding from Base's own bundled languages.
			$raw = implode(',', array_keys(self::get_base_languages()));
			Variable::set('installed_langs', $raw);
		}
		$codes = explode(',', $raw);
		return array_values(array_filter($codes, fn($c) => $c !== ''));
	}
}

function __($string, $arg2=array()) {
	return Base_LangCommon::translate($string, $arg2);
}
function _V($string, $arg2=array()) { // ****** _V Definition - variable translations
	return Base_LangCommon::translate($string, $arg2);
}
function _M($string, $arg2=array()) { // ****** _M Definition - mark translations - doesn't translate, only marks string to translate
	return Base_LangCommon::translate($string, $arg2, false);
}

Module::register_method('t',array('Base_LangCommon','ts')); // DEPRECATED
Module::register_method('ht',array('Base_LangCommon','ts')); // DEPRECATED
on_init(array('Base_LangCommon','load'));

?>
