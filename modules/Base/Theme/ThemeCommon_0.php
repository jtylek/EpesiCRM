<?php
/**
 * Theme class.
 * 
 * Provides module templating.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage theme
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * load Smarty library
 */
define('SMARTY_DIR', 'modules/Base/Theme/smarty/');

require_once(SMARTY_DIR.'Smarty.class.php');


class Base_ThemeCommon extends ModuleCommon {

	/**
	 * Maps a flattened theme-root path (e.g. "Base/Box/default.css",
	 * "images/icons/save.png") onto the modules/ file that backs it.
	 * Implemented in Base_ThemeResolver so the standalone asset.php /
	 * theme_css.php entry points can share it without bootstrapping Epesi.
	 *
	 * @return string|null path relative to the project root, or null if unknown
	 */
	public static function resolve_theme_file($rel, $theme = null) {
		require_once('modules/Base/Theme/resolver.php');
		if (!isset($theme)) $theme = self::get_default_template();
		return Base_ThemeResolver::resolve($rel, $theme);
	}

	/**
	 * Base URL that flattened theme paths get appended to in templates
	 * ({$theme_dir}/Utils/Calendar/next.png). There is no longer a directory
	 * holding that layout, so this points at the asset handler which maps each
	 * path back onto modules/ - see modules/Base/Theme/asset.php.
	 */
	public static function get_theme_dir_url() {
		return 'modules/Base/Theme/asset.php?t=' . rawurlencode(self::get_default_template()) . '&f=';
	}

	public static function init_smarty() {
		$smarty = new Smarty();
		
		$theme = self::get_default_template();

		$smarty->template_dir = DATA_DIR.'/Base_Theme/templates/'.$theme;
		$smarty->compile_dir = TEMP_DIR.'/Base_Theme/compiled/';
		$smarty->compile_id = $theme;
		$smarty->config_dir = TEMP_DIR.'/Base_Theme/config/';
		$smarty->cache_dir = TEMP_DIR.'/Base_Theme/cache/';
		if (!is_dir($smarty->compile_dir)) mkdir($smarty->compile_dir, 0777, true);
        
        $smarty->register_modifier('t', array(__CLASS__, 'smarty_modifier_translate'));
		return $smarty;
	}
	
	public static function get_default_template() {
		static $theme;
		if(!isset($theme)) {
			$theme = Variable::get('default_theme');
			// A theme no longer needs a directory under data/ to be valid - it can
			// live entirely in modules/<Mod>/theme_<name>/. Checked directly rather
			// than via Base_Theme::list_themes() because this runs early enough in
			// bootstrap that the Base_Theme class may not be loaded yet.
			if($theme !== 'default'
			   && !is_dir(DATA_DIR.'/Base_Theme/templates/'.$theme)
			   && !glob('modules/*/*/theme_'.$theme, GLOB_ONLYDIR)
			   && !glob('modules/*/*/*/theme_'.$theme, GLOB_ONLYDIR))
				$theme = 'default';
		}
		return $theme;
	}
	
	public static function display_smarty($smarty, $module_name, $user_template=null, $fullname=false) {
		$module_name = str_replace('_','/',$module_name);
		if(isset($user_template)) {
			if (!$fullname)
				$module_name .= '/'.$user_template;
			else {
				if(preg_match("/.tpl$/i",$user_template)) {
					$tpl = $user_template;
					$css = str_replace('.tpl','.css',$tpl);
				} else
					$module_name = $user_template;
			}
		} else
			$module_name .= '/default';
		
		if(!isset($tpl)) {
			$tpl = $module_name.'.tpl';
			$css = $module_name.'.css';
		}

		// Templates and their CSS are served straight from modules/ - the
		// data/Base_Theme/templates/ copy that "Theme update" used to build is gone.
		// resolve_theme_file() applies the installed-theme override first, so a
		// custom theme in data/ or a modules/<Mod>/theme_<name>/ override still wins
		// over the module's base template.
		$tpl_file = self::resolve_theme_file($tpl);
		if($tpl_file === null) {
			trigger_error('Template not found: '.$tpl,E_USER_WARNING);
			return;
		}

		// {$theme_dir}/Some/Module/x.png in templates addresses the old flattened
		// theme root, which no longer exists as a directory - point it at the
		// handler that maps those paths back onto modules/.
		$smarty->assign('theme_dir', self::get_theme_dir_url());

		// Smarty 2.x's file: resource only treats a path as absolute when it
		// matches a leading "/" or a drive letter; anything else is resolved
		// against template_dir rather than the cwd, so this must be absolute.
		$smarty->display('file:'.EPESI_LOCAL_DIR.'/'.$tpl_file);

		if(isset($css)) {
			$css_file = self::resolve_theme_file($css);
			if($css_file !== null)
				load_css($css_file, self::css_loader());
		}
	}

	/**
	 * Loader script used for module CSS. It rewrites the stylesheet's
	 * theme-root-relative url() references onto modules/ as it serves - see
	 * modules/Base/Theme/theme_css.php.
	 *
	 * No query string here: Epesi::prepare_minified_files() builds the final URL
	 * as $loader.'?'.http_build_query(...), so anything already carrying a "?"
	 * would produce a malformed one. The loader resolves the active theme itself.
	 */
	private static function css_loader() {
		return 'modules/Base/Theme/theme_css.php';
	}


	/**
	 * No-op, kept because every module's install script calls it.
	 *
	 * This used to copy modules/<Mod>/theme/ into data/Base_Theme/templates/default/,
	 * which is what made a "Theme update" necessary whenever a template changed.
	 * Templates and CSS are now read straight from modules/, so there is nothing
	 * left to install and nothing to keep in sync.
	 */
	public static function install_default_theme($mod_name,$version=0) {
	}

	/**
	 * No-op counterpart of install_default_theme() - see above. Removing a module
	 * now removes its templates with it, since they only ever lived in modules/.
	 */
	public static function uninstall_default_theme($mod_name) {
	}
	
	/**
	 * Returns path to currently selected theme.
	 * 
	 * @return string directory in which currently selected theme is placed 
	 */
	public static function get_template_dir() {
		static $theme = null;
		static $themes_dir;
		if(!isset($themes_dir))
			$themes_dir = DATA_DIR.'/Base_Theme/templates/';
		if(!isset($theme)) {
			$theme = Variable::get('default_theme');
		
			if(!is_dir($themes_dir.$theme))
				$theme = 'default';
		}
		
		return $themes_dir.$theme.'/';
	}

	/**
	 * Returns path and filename of a template file.
	 * 
	 * Use this method if you want to pass full path and filename of a template file 
	 * to another method which specifically accepts such data.
	 * 
	 * @param string module name
	 * @param string path and filename (path relative to specified module)
	 * @return string path and name of a file
	 */
	public static function get_template_filename($modulename,$filename) {
		return str_replace("_", "/", $modulename).'/'.$filename;
	}

	/**
	 * Returns path and filename of a template file using path to currently selected theme.
	 * 
	 * Use this method if you want to get access to a template file of currently installed theme.
	 * Files retreived this way are accessible via common file operation functions.
	 * 
	 * @param string module name
	 * @param string path and filename (path relative to specified module)
	 * @return mixed path and name of a file, false if no such file was found
	 */
	public static function get_template_file($modulename,$filename=null) {
		if(!isset($filename))
			$filename = $modulename;
		else
			$filename = self::get_template_filename($modulename,$filename);
		// Resolves onto modules/ (or an installed theme's override) - the returned
		// path stays relative to the project root, so callers can keep using it
		// both as a filesystem path and directly as an <img src> URL.
		return self::resolve_theme_file($filename);
	}

	/**
	 * Loads css file.
	 * 
	 * @param string module name
	 * @param string css file name, 'default' by default
	 * @param bool sets whether there should be an error displayed if css is not present, true by default
	 * @return bool true on success, false otherwise
	 */
	public static function load_css($module_name,$css_name = 'default',$trig_error=true) {
		if(!isset($module_name))
			trigger_error('Invalid argument for load_css, no module was specified.',E_USER_ERROR);

		$css = self::get_template_file($module_name,$css_name.'.css');
		if ($css) {
			load_css($css,self::css_loader());
			return true;
		} else {
			if($trig_error) trigger_error('Invalid css specified: '.$module_name.'/'.$css_name.'.css',E_USER_ERROR);
			return false;
		}
	}

    /**
     * Get generic icon file.
     *
     * @param string $name icon name without extension. To check available icons explore Base/Theme/images/icons.
     * @return string path to icon
     */
    public static function get_icon($name) {
        return self::get_template_file('images/icons/'.$name.'.png');
    }
	
	private static function get_images($dir) {
		$content = scandir($dir);
		$ret = array();
		foreach ($content as $name){
			if ($name == '.' || $name == '..') continue;
			$file_name = $dir.'/'.$name;
			if (is_dir($file_name)) {
				$ret = array_merge($ret,self::get_images($file_name));
			} else {
				$ext = strtolower(substr(strrchr($file_name,'.'),1));
				if ($ext === 'jpg' ||
					$ext === 'jpeg' ||
					$ext === 'gif' ||
					$ext === 'png')
				$ret[] = $file_name;
			}
		}
		return $ret;
	}

	/**
	 * No-op. Used to drop a __css.php loader into each theme directory so that
	 * relative url() references inside module CSS resolved against the flattened
	 * theme root. That rewriting is now done while the stylesheet is served - see
	 * modules/Base/Theme/theme_css.php - so there is no per-theme file to cache.
	 */
	public static function create_cache() {
	}

	/**
	 * No-op. The shared images this used to unpack into the theme root are read
	 * straight from modules/Base/Theme/images/ now.
	 */
	public static function install_default_theme_common_files($dir,$f) {
	}

    /**
     * No-op. "Theme update" existed only to rebuild the flattened copy of every
     * module's theme/ directory under data/, which templates and CSS were then
     * read from - so any template edit needed a rebuild to become visible.
     * Everything is read from modules/ directly now, so there is nothing to
     * update; kept as a no-op for callers and old bookmarks.
     */
    public static function themeup() {
    }

    /**
	 * For internal use only.
	 */
	public static function parse_links($key, $val, $flat=true) {
		if (!is_array($val)) {
			$val = trim($val);
			$i=0;
			$count=0;
			$open="";
			$text="";
			$close="";
			$len = strlen($val);
			if ($len>2 && $val[0]==='<' && $val[1]==='a')
				while ($i<$len-1) {
					if ($val[$i]==='<') {
						if ($val[$i+1]==='a') {
							if ($count===0) {
								while ($i<$len-1 && $val[$i]!=='>') {
									$open .= $val[$i];
									$i++;
									if ($val[$i]==='"') {
										do {
											$open .= $val[$i];
											$i++;
										} while ($i<$len && $val[$i]!=='"');
									}
								}
								$open .= '>';
							} else $text .= $val[$i];
							$count++;
						} else if (substr($val,$i+1,3)==='/a>') {
							$count--;
							if ($count===0) {
								$close = '</a>';
								return array(	'open' => $open,
												'text' => $text,
												'close' => '</a>');
							} else $text .= $val[$i];
						} else $text .= $val[$i];
					} else $text .= $val[$i];
					$i++;
				}
			return array();
		} else {
			$result = array();
			foreach ($val as $k=>$v) {
				$result[$k] = Base_ThemeCommon::parse_links($k, $v, false);
			}
			return $result;
		}
	}
    
    public static function smarty_modifier_translate($string) {
        return Base_LangCommon::translate($string);
    }
}

?>
