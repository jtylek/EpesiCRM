<?php
/**
 * CSS loader for module stylesheets served straight from modules/.
 *
 * Same job as the project-root serve.php, with one addition: module CSS
 * addresses its images relative to the flattened theme root the retired "Theme
 * update" step used to build (url('images/icons/x.png'),
 * url('Utils/Calendar/next.png')). That tree no longer exists, so the url()
 * references are remapped onto the real files under modules/ as the CSS is
 * served - see Base_ThemeCommon::rewrite_css_urls(). Doing it here rather than
 * editing the stylesheets keeps every module's .css untouched on disk, third
 * party modules included, and Minify caches the rewritten result so the
 * transform is not repeated per request.
 */
chdir(dirname(__FILE__) . '/../../../');

$serveExtensions = array('css');

set_time_limit(0);
if (isset($_GET['f'])) {
    $filename = $_GET['f'];
    $filenamePattern = '/[^\'"\\/\\\\]+\\.(?:' . implode('|', $serveExtensions) . ')$/';
    if (is_string($filename))
        $arr = explode(',', $filename);
    elseif (is_array($filename))
        $arr = array_values($filename);

    if (isset($arr)) {
        $arr2 = array();
        $this_file_dir_pattern = '/' . preg_quote(dirname(dirname(dirname(dirname(__FILE__)))), '/') . '/i';
        foreach ($arr as $v) {
            if (preg_match($filenamePattern, $v) &&
                file_exists($v) && preg_match($this_file_dir_pattern, realpath($v)))
                $arr2[] = $v;
        }

        ini_set('include_path', 'libs/minify' . PATH_SEPARATOR . '.' . PATH_SEPARATOR . 'libs' . PATH_SEPARATOR . ini_get('include_path'));
        require 'Minify.php';

        define('_VALID_ACCESS', 1);
        require_once('include/data_dir.php');
        require_once('include/config.php');
        require_once('modules/Base/Theme/resolver.php');

        // The active theme decides which override a url() resolves to, and it
        // can't be passed in the URL - Epesi::prepare_minified_files() owns the
        // query string. Looking it up costs a query on a response that is then
        // cached for a year, and a theme lookup failure must not take the whole
        // stylesheet down, so fall back to the built-in theme.
        $theme = 'default';
        try {
            require_once('include/database.php');
            require_once('include/variables.php');
            $t = Variable::get('default_theme', false);
            if (is_string($t) && preg_match('/^[A-Za-z0-9_-]+$/', $t)) $theme = $t;
        } catch (Exception $e) {
        } catch (Error $e) {
        }

        $cache_dir = TEMP_DIR . '/cache/minify';
        if (!file_exists($cache_dir))
            mkdir($cache_dir, 0777, true);
        Minify::setCache($cache_dir);

        $opts = array(
            'files' => $arr2,
            'maxAge' => 86400 * 365,
            'rewriteCssUris' => false,
        );
        if (!MINIFY_ENCODE) {
            $opts['encodeOutput'] = false;
            $opts['encodeMethod'] = '';
        }
        // Our rewrite always runs; the stock minifier only stacks on top of it
        // when the install actually asked for minified sources. Must be a named
        // static method, not a closure - Minify serialises this to build the
        // cache id. The theme travels in minifierOptions, which is part of that
        // same id, so a cached rewrite is never reused across themes.
        $opts['minifiers'] = array(
            Minify::TYPE_CSS => array('Base_ThemeResolver', 'css_minifier'),
            Minify::TYPE_HTML => '',
            Minify::TYPE_JS => '',
        );
        // A browser resolves url() against the stylesheet's URL, which is this
        // script - so a bare "modules/..." would be fetched as
        // modules/Base/Theme/modules/... urlBase corrects for that. This file
        // always lives at <root>/modules/Base/Theme/, so climbing three levels
        // always lands on the project root: no dependence on EPESI_DIR (empty
        // under CLI), on the install's subdirectory, or on hostname.
        $opts['minifierOptions'] = array(
            Minify::TYPE_CSS => array(
                'themeContext' => $theme,
                'urlBase' => '../../../',
            ),
        );

        Minify::serve('Files', $opts);
        exit();
    }
}

header("HTTP/1.0 404 Not Found");
echo "HTTP/1.0 404 Not Found";
