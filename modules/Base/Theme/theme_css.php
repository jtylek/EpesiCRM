<?php
/**
 * CSS loader for module stylesheets served straight from modules/.
 *
 * Same job as the project-root serve.php (both share include/serve_minified.php),
 * with one addition: module CSS addresses its images relative to the flattened
 * theme root the retired "Theme update" step used to build (url('images/icons/x.png'),
 * url('Utils/Calendar/next.png')). That tree no longer exists, so the url()
 * references are remapped onto the real files under modules/ as the CSS is
 * served - see Base_ThemeCommon::rewrite_css_urls(). Doing it here rather than
 * editing the stylesheets keeps every module's .css untouched on disk, third
 * party modules included, and Minify caches the rewritten result so the
 * transform is not repeated per request.
 */
require_once dirname(__FILE__) . '/../../../include/serve_minified.php';
require_once dirname(__FILE__) . '/resolver.php';

// epesi_serve_minified() also chdir()s to the project root, but not until
// after this file finishes building $minifierOpts below - the relative
// requires just after this need it done first. chdir() to the same directory
// twice is harmless.
chdir(EPESI_SERVE_ROOT);

// The active theme decides which override a url() resolves to, and it can't
// be passed in the URL - Epesi::prepare_minified_files() owns the query
// string. Looking it up costs a query on a response that is then cached for a
// year, and a theme lookup failure must not take the whole stylesheet down,
// so fall back to the built-in theme.
$theme = 'default';
try {
    require_once('include/database.php');
    require_once('include/variables.php');
    $t = Variable::get('default_theme', false);
    if (is_string($t) && preg_match('/^[A-Za-z0-9_-]+$/', $t)) $theme = $t;
} catch (Exception $e) {
} catch (Error $e) {
}

// Minify::TYPE_CSS/TYPE_HTML/TYPE_JS are just 'text/css'/'text/html'/
// 'application/x-javascript' - spelled out literally here rather than
// referencing the Minify class, which epesi_serve_minified() has not loaded
// yet at the point this array is built.
epesi_serve_minified(array('css'), array(
    // Our rewrite always runs; the stock minifier only stacks on top of it
    // when the install actually asked for minified sources. Must be a named
    // static method, not a closure - Minify serialises this to build the
    // cache id. The theme travels in minifierOptions, which is part of that
    // same id, so a cached rewrite is never reused across themes.
    'minifiers' => array(
        'text/css' => array('Base_ThemeResolver', 'css_minifier'),
        'text/html' => '',
        'application/x-javascript' => '',
    ),
    // A browser resolves url() against the stylesheet's URL, which is this
    // script - so a bare "modules/..." would be fetched as
    // modules/Base/Theme/modules/... urlBase corrects for that. This file
    // always lives at <root>/modules/Base/Theme/, so climbing three levels
    // always lands on the project root: no dependence on EPESI_DIR (empty
    // under CLI), on the install's subdirectory, or on hostname.
    'minifierOptions' => array(
        'text/css' => array(
            'themeContext' => $theme,
            'urlBase' => '../../../',
        ),
    ),
));

header("HTTP/1.0 404 Not Found");
echo "HTTP/1.0 404 Not Found";
