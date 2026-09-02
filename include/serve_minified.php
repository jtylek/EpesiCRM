<?php
/**
 * Shared implementation behind the project's two Minify-based JS/CSS entry
 * points, serve.php (root) and modules/Base/Theme/theme_css.php. Both used to
 * carry their own ~70-line copy of this (file-list validation, Minify setup,
 * cache dir) that had drifted into two almost-but-not-quite-identical
 * versions - see AI-private/REFERENCE-optimization-opus-AI.md, item 3.4.
 *
 * Deliberately does not require include.php: these two entry points are hit
 * on every page load (often several times, once per distinct loader/theme
 * combination) and must not pay for the full module bootstrap just to hand
 * back a cached bundle.
 *
 * libs/bootstrap-icons-1.13.1/__css.php reuses serve.php as-is (a location
 * shim, not a reimplementation - see that file's own comment) and therefore
 * picks this up too. modules/Base/Theme/asset.php is NOT built on this: it
 * serves one binary file at a time (images/fonts) with ETag/Last-Modified
 * conditional GETs, a different job than combining/minifying text files.
 */
defined('_VALID_ACCESS') || define('_VALID_ACCESS', 1);

if (!defined('EPESI_SERVE_ROOT')) define('EPESI_SERVE_ROOT', dirname(__DIR__));

/**
 * @param string[] $extensions file extensions this entry point accepts, e.g. ['css','js']
 * @param array $minifierOpts extra/overriding Minify::serve() options (e.g. theme_css.php's
 *        url-rewriting CSS minifier). Merged over the shared defaults.
 */
function epesi_serve_minified(array $extensions, array $minifierOpts = array()) {
    // Apache/mod_php sets a directly-requested script's cwd to its own
    // directory, not the project root - true already for root-level serve.php,
    // not for theme_css.php three directories down. Must happen before the
    // file_exists()/realpath() checks below, which assume relative paths
    // resolve against the project root.
    chdir(EPESI_SERVE_ROOT);

    set_time_limit(0);

    $filename = isset($_GET['f']) ? $_GET['f'] : null;
    if (!isset($filename)) {
        header("HTTP/1.0 404 Not Found");
        echo "HTTP/1.0 404 Not Found";
        return;
    }

    $filenamePattern = '/[^\'"\\/\\\\]+\\.(?:' . implode('|', $extensions) . ')$/';
    if (is_string($filename)) $arr = explode(',', $filename);
    elseif (is_array($filename)) $arr = array_values($filename);
    else $arr = array();

    $root_pattern = '/' . preg_quote(EPESI_SERVE_ROOT, '/') . '/i';
    $files = array();
    foreach ($arr as $v) {
        if (preg_match($filenamePattern, $v) && file_exists($v) && preg_match($root_pattern, realpath($v)))
            $files[] = $v;
    }

    if (!$files) {
        header("HTTP/1.0 404 Not Found");
        echo "HTTP/1.0 404 Not Found";
        return;
    }

    ini_set('include_path', 'libs/minify' . PATH_SEPARATOR . '.' . PATH_SEPARATOR . 'libs' . PATH_SEPARATOR . ini_get('include_path'));
    require 'Minify.php';

    require_once('include/data_dir.php');
    require_once('include/config.php');

    $cache_dir = TEMP_DIR . '/cache/minify';
    if (!is_dir($cache_dir)) @mkdir($cache_dir, 0777, true);
    Minify::setCache($cache_dir);

    $opts = array_merge(array(
        'files' => $files,
        'maxAge' => 86400 * 365,
        'rewriteCssUris' => false,
    ), $minifierOpts);

    if (!MINIFY_ENCODE) {
        $opts['encodeOutput'] = false;
        $opts['encodeMethod'] = '';
    }
    // A caller-supplied 'minifiers' entry (theme_css.php's url-rewrite, which
    // must always run) takes this over completely rather than being merged
    // with the source-minification toggle below.
    if (!isset($opts['minifiers']) && !MINIFY_SOURCES) {
        $opts['minifiers'] = array(
            Minify::TYPE_CSS => '',
            Minify::TYPE_HTML => '',
            Minify::TYPE_JS => '',
        );
    }

    Minify::serve('Files', $opts);
    exit();
}
