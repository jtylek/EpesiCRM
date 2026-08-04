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
	 * Loads the CSS/JS framework the active theme is built on.
	 *
	 * Called from Base_Box, which renders on every page (including the anonymous
	 * login screen), and from Base_User_Login so that module stays self-contained.
	 * Epesi::load_css()/load_js() de-duplicate per session, so calling it more than
	 * once is harmless. No-op for themes that need no framework, which keeps the
	 * default theme's page weight unchanged.
	 */
	public static function load_theme_assets() {
		if (!self::is_adminlte_family()) return;
		load_css('libs/bootstrap-5.3.8/css/bootstrap.min.css');
		// Served through its own loader so the @font-face url("fonts/...")
		// references in the vendored CSS resolve against the icon package's
		// directory rather than the project root - see that file's comment.
		load_css('libs/bootstrap-icons-1.13.1/bootstrap-icons.min.css',
		         'libs/bootstrap-icons-1.13.1/__css.php');
		load_css('libs/adminlte-4.1.0/css/adminlte.min.css');
		load_js('libs/bootstrap-5.3.8/js/bootstrap.bundle.min.js');
		// drives the sidebar toggle (data-lte-toggle="sidebar") in the shell
		load_js('libs/adminlte-4.1.0/js/adminlte.min.js');
		// adminlte.min.js ships a color-mode toggler (class "Me") that runs on
		// script load: it reads localStorage['lte-theme'] ("light"/"dark"/"auto"),
		// falls back to the OS's prefers-color-scheme if nothing is stored, and
		// applies data-bs-theme+colorScheme to <html> only, then dispatches a
		// "changed.lte.color-mode" CustomEvent (detail.resolved) on every future
		// toggle click. Several of AdminLTE's own CSS rules key off a
		// data-bs-theme ancestor directly (e.g. "[data-bs-theme=dark]
		// .app-sidebar"), and a descendant combinator doesn't care WHICH ancestor
		// carries the matching attribute - so <html>'s value isn't actually the
		// only one in play: Base_Box's shell markup ALSO stamps data-bs-theme
		// directly onto .epesi-adminlte (the wrapper around the entire logged-in
		// app, everything this theme renders except Leightbox popups, which
		// attach straight to <body>). If that inner copy is left at whatever
		// theme_name resolved to server-side and never updated, it silently wins
		// the "[data-bs-theme=dark] .card"-style rules for every Bootstrap/
		// AdminLTE-native component underneath it (dashboard applet cards,
		// tables, form controls, ...) regardless of what <html>'s own attribute
		// says - reported as the toggle visibly changing the shell chrome
		// (sidebar/navbar/background) but leaving every card/table still dark.
		// Keeping both elements' attributes identical at all times closes that
		// gap without needing to touch a single one of AdminLTE's own scoped
		// rules. adminltedark's sidebar footer ships its own icon-only
		// data-bs-theme-value toggle (Base_Box/theme_adminltedark/default.tpl,
		// next to Logout) that "Me" listens for clicks on and persists to that
		// same localStorage key - so for adminltedark this only SEEDS a default
		// the first time the key has never been set, rather than forcing it
		// every load, so a returning user's own light/dark choice sticks. Since
		// it's a single toggle rather than a light/dark pair, its own
		// data-bs-theme-value has to always hold the OPPOSITE of whatever's
		// currently active (Me only ever "sets to this literal value", it has
		// no toggle concept) - set here for the initial load, and flipped again
		// on every later "changed.lte.color-mode" event (also where
		// .epesi-adminlte is kept following <html>), for the lifetime of the
		// page (this is the shell - not re-rendered by ordinary AJAX
		// navigation, so the listener never needs re-attaching). The toggle's
		// own icon swap (data-lte-theme-icon) is seeded the same way here, but
		// on every later click Me's own _showActiveTheme() already re-toggles
		// it natively - no JS of ours needed for that half. adminlte (light)
		// has no toggle, so it keeps pinning unconditionally and skips all of
		// this - there's nothing for it to ever desync from. Either way,
		// Epesi.append_js only runs once every queued load_js() script has
		// finished loading (see Epesi.js_loader() in include/epesi.js), so this
		// always executes after adminlte.min.js's own auto-detection/click
		// listener are registered, not in a race with them - but that's a
		// guarantee about OTHER SCRIPTS, not about Base_Box's own shell markup.
		// Logging in doesn't do a normal navigation - the login form's response
		// patches Box's shell into the DOM the same AJAX-push way any other
		// module swap does, and this exact script (queued from Box's own
		// construction, as part of that same patch) can run before that
		// patch has actually landed: confirmed by instrumenting a real login
		// with a pre-existing 'light' preference already in localStorage -
		// document.querySelector('.epesi-adminlte') came back null at the
		// point this ran, so neither it nor .epesi-theme-toggle got synced,
		// and the shell rendered dark (its SSR default) until the next full
		// reload. A plain reload doesn't hit this - by then Box's whole shell
		// is already part of the one synchronous HTML response - which is why
		// earlier testing (always via reload) never caught it. Retrying for a
		// few seconds rather than giving up after one null check makes this
		// self-healing regardless of exactly when that patch lands.

		// Guarantees window.epesi_confirm()/epesi_alert() (the styled Bootstrap-modal
		// replacements for native confirm()/alert(), see include/module.php) exist on every
		// AdminLTE-family page, not just ones where some module happened to render a
		// confirm/alert link first - needed for purely client-side callers like QuickForm's
		// unsaved-changes leave-page guard (Epesi.confirmLeave in include/epesi.js).
		Module::inject_confirm_modal();
		Module::inject_alert_modal();

		if (self::is_dark_theme()) {
			eval_js_once(
				"try{var s=localStorage.getItem('lte-theme');".
				"if(s!=='light'&&s!=='dark')localStorage.setItem('lte-theme','dark');}catch(e){}".
				"var m='dark';try{var s2=localStorage.getItem('lte-theme');if(s2==='light'||s2==='dark')m=s2;}catch(e){}".
				"document.documentElement.setAttribute('data-bs-theme',m);".
				"document.documentElement.style.colorScheme=m;".
				"var epesiSyncTries=0;".
				"(function epesiSyncThemeShell(){".
					"var w=document.querySelector('.epesi-adminlte');".
					"var tb=document.querySelector('.epesi-theme-toggle');".
					"if(w)w.setAttribute('data-bs-theme',m);".
					"if(tb){".
						"tb.setAttribute('data-bs-theme-value',m==='dark'?'light':'dark');".
						"tb.querySelectorAll('[data-lte-theme-icon]').forEach(function(ic){".
							"ic.classList.toggle('d-none',ic.getAttribute('data-lte-theme-icon')!==m);".
						"});".
					"}".
					"if((!w||!tb)&&++epesiSyncTries<50)setTimeout(epesiSyncThemeShell,100);".
				"})();".
				"document.addEventListener('changed.lte.color-mode',function(e){".
					"var w2=document.querySelector('.epesi-adminlte');".
					"if(w2&&e.detail&&e.detail.resolved)w2.setAttribute('data-bs-theme',e.detail.resolved);".
					"var tb2=document.querySelector('.epesi-theme-toggle');".
					"if(tb2&&e.detail&&e.detail.resolved)tb2.setAttribute('data-bs-theme-value',e.detail.resolved==='dark'?'light':'dark');".
				"});"
			);
		} else {
			eval_js_once("try{localStorage.setItem('lte-theme','light');}catch(e){}"
				."document.documentElement.setAttribute('data-bs-theme','light');"
				."document.documentElement.style.colorScheme='light';");
		}
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

		// Unused for resolution - display_smarty() below always passes an
		// absolute 'file:'.EPESI_LOCAL_DIR.'/'.$tpl_file path instead, since
		// templates are resolved via resolve_theme_file()/the resolver, not
		// looked up relative to this directory. Smarty still requires some
		// value here, so this just points at the project root.
		$smarty->template_dir = EPESI_LOCAL_DIR;
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
			// Base_Theme may not be installed yet (e.g. this runs during the
			// pre-module-install splash on a brand new setup, or FirstRun's
			// admin-creation wizard, which both run before ThemeInstall.php
			// has had a chance to persist 'adminltedark' as default_theme).
			$theme = Variable::get('default_theme', false) ?: 'adminltedark';
			// A theme lives entirely in modules/<Mod>/theme_<name>/ - checked
			// directly rather than via Base_Theme::list_themes() because this
			// runs early enough in bootstrap that the Base_Theme class may not
			// be loaded yet.
			if($theme !== 'default'
			   && !glob('modules/*/*/theme_'.$theme, GLOB_ONLYDIR)
			   && !glob('modules/*/*/*/theme_'.$theme, GLOB_ONLYDIR))
				$theme = 'default';
		}
		return $theme;
	}

	/**
	 * True for any theme built on the Bootstrap/AdminLTE framework - currently
	 * just 'adminltedark' ('adminlte', the light-only original, was removed
	 * 2026-08-04, see AI-shared/adminlte-theme.md: adminltedark covers light
	 * mode itself via the navbar toggle, and the project isn't investing in a
	 * second, separately-maintained family member). Call sites that used to
	 * check get_default_template() === 'adminlte' to pick Bootstrap-based
	 * markup/JS over the legacy default theme should check this instead, so
	 * they don't need updating again for every future member of the family.
	 */
	public static function is_adminlte_family($theme = null) {
		if (!isset($theme)) $theme = self::get_default_template();
		return in_array($theme, array('adminltedark'), true);
	}

	/**
	 * True for the dark member of the AdminLTE family - lets shared
	 * adminlte-family templates/PHP pick a light or dark asset/attribute
	 * value without hardcoding which theme names are "dark".
	 */
	public static function is_dark_theme($theme = null) {
		if (!isset($theme)) $theme = self::get_default_template();
		return $theme === 'adminltedark';
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
		// Lets a template branch on the active theme directly (e.g. the shell's
		// data-bs-theme pin) without a PHP-side helper for every such spot.
		$smarty->assign('theme_name', self::get_default_template());

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
