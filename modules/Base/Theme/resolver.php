<?php
/**
 * Theme path resolution, with no dependency on the module framework.
 *
 * Kept separate from Base_ThemeCommon (which extends ModuleCommon and therefore
 * drags in all of Epesi) so the two lightweight web entry points - asset.php and
 * theme_css.php - can resolve paths without bootstrapping the application on
 * every image or stylesheet request. Base_ThemeCommon delegates here.
 *
 * Requires only the DATA_DIR constant and a working directory at the project root.
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_ThemeResolver {

	/**
	 * Maps a "flattened theme root" path onto the file that actually backs it.
	 *
	 * Templates, CSS and get_template_file() callers all address theme assets the
	 * way the retired "Theme update" step laid them out under
	 * data/Base_Theme/templates/<theme>/ - either "<module path>/<rest>"
	 * (Base/Box/default.css, Utils/Calendar/next.png) or "images/<rest>" for the
	 * assets shared by every module. Nothing copies files there any more, so those
	 * paths resolve back to their source under modules/ instead:
	 *
	 *   1. an installed theme's own override in data/ still wins, so downloaded
	 *      or hand-edited themes keep working exactly as before;
	 *   2. modules/<module path>/theme_<theme>/<rest> - a theme's per-module
	 *      override shipped alongside the module itself;
	 *   3. modules/<module path>/theme/<rest> - the module's own base version,
	 *      or modules/Base/Theme/images/<rest> for the shared assets.
	 *
	 * Module names nest (Base_User_Login -> Base/User/Login), so the module prefix
	 * can't be assumed to be two segments; the longest prefix that actually has a
	 * theme dir wins.
	 *
	 * @param string $rel flattened path, e.g. "Base/Box/default.css"
	 * @param string $theme theme name
	 * @return string|null path relative to the project root, null if unknown
	 */
	public static function resolve($rel, $theme = 'default') {
		static $cache = array();
		$key = $theme . '|' . $rel;
		if (!array_key_exists($key, $cache))
			$cache[$key] = self::resolve_uncached($rel, $theme);
		return $cache[$key];
	}

	private static function resolve_uncached($rel, $theme) {
		$rel = ltrim(str_replace('\\', '/', (string)$rel), '/');
		// reject traversal outright - this feeds a web-facing asset handler
		if ($rel === '' || strpos($rel, '..') !== false || strpos($rel, "\0") !== false)
			return null;

		if ($theme !== 'default' && defined('DATA_DIR')) {
			$f = DATA_DIR . '/Base_Theme/templates/' . $theme . '/' . $rel;
			if (is_readable($f)) return $f;
		}

		// assets shared by every module, historically copied to the theme root
		if (strpos($rel, 'images/') === 0) {
			$f = 'modules/Base/Theme/' . $rel;
			return is_readable($f) ? $f : null;
		}

		$variants = ($theme !== 'default') ? array('theme_' . $theme, 'theme') : array('theme');
		$parts = explode('/', $rel);
		for ($n = count($parts) - 1; $n >= 1; $n--) {
			$prefix = implode('/', array_slice($parts, 0, $n));
			$rest = implode('/', array_slice($parts, $n));
			foreach ($variants as $v) {
				$f = 'modules/' . $prefix . '/' . $v . '/' . $rest;
				if (is_readable($f)) return $f;
			}
		}
		return null;
	}

	/**
	 * Minify minifier hook for CSS - see modules/Base/Theme/theme_css.php.
	 *
	 * Deliberately a named static method rather than a closure: Minify serialises
	 * the minifier and its options to build the cache id, and closures cannot be
	 * serialised. The active theme rides along in minifierOptions for the same
	 * reason - it is part of that cache id, so two themes can't be served each
	 * other's cached rewrite.
	 */
	public static function css_minifier($css, $options = array()) {
		$theme = isset($options['themeContext']) ? $options['themeContext'] : 'default';
		$base = isset($options['urlBase']) ? $options['urlBase'] : '';
		$css = self::rewrite_css_urls($css, $theme, $base);
		if (defined('MINIFY_SOURCES') && MINIFY_SOURCES && class_exists('Minify_CSS'))
			$css = Minify_CSS::minify($css, $options);
		return $css;
	}

	/**
	 * Rewrites the theme-root-relative url() references inside module CSS to point
	 * at the real files under modules/. Applied while the stylesheet is served so
	 * the module's own .css stays untouched on disk - third-party modules ship CSS
	 * written against the old flattened layout too.
	 *
	 * @param string $base prefix making the result absolute from the web root.
	 *        Required: a browser resolves url() against the stylesheet's own URL,
	 *        and the stylesheet is served by a script sitting in
	 *        modules/Base/Theme/, so a bare "modules/..." would be requested as
	 *        modules/Base/Theme/modules/... Pass EPESI_DIR.
	 */
	public static function rewrite_css_urls($css, $theme = 'default', $base = '') {
		return preg_replace_callback(
			'#url\(\s*([\'"]?)([^)\'"]+?)\1\s*\)#i',
			function ($m) use ($theme, $base) {
				$ref = trim($m[2]);
				// leave absolute, protocol-relative and inline data URIs alone
				if ($ref === '' || preg_match('#^(data:|https?:|//|/)#i', $ref))
					return $m[0];
				$suffix = '';
				if (preg_match('#^([^?\#]*)([?\#].*)$#', $ref, $s)) {
					$ref = $s[1];
					$suffix = $s[2];
				}
				$target = self::resolve($ref, $theme);
				// unresolved refs are left verbatim - a number of them are already
				// dead in the shipped CSS and rewriting would only obscure that
				return $target === null ? $m[0] : 'url("' . $base . $target . $suffix . '")';
			},
			$css
		);
	}
}
