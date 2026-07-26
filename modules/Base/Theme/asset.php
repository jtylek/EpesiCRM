<?php
/**
 * Serves a theme asset addressed by its historical "flattened theme root" path.
 *
 * Templates address images as {$theme_dir}/Utils/Calendar/next.png and CSS as
 * url('images/icons/save.png') - both relative to data/Base_Theme/templates/<theme>/,
 * the directory the old "Theme update" step used to build by copying every
 * module's theme/ dir into one tree. Templates and CSS are now served straight
 * from modules/, so that flattened tree no longer exists and those paths have
 * nothing to point at.
 *
 * Rather than rewrite ~300 references across every module (which would also
 * break any third-party module shipping CSS/templates authored the old way),
 * $theme_dir is pointed at this script and it maps the flattened path back onto
 * the real file under modules/. Only {$theme_dir}-style references pay for this
 * hop; anything resolved through Base_ThemeCommon::get_template_file() gets a
 * plain static modules/ URL.
 *
 * Assets are immutable per deployment, so responses carry a long max-age plus
 * ETag/Last-Modified to keep repeat requests as conditional GETs.
 */
define('_VALID_ACCESS', 1);
chdir(dirname(__FILE__) . '/../../../');
require_once('include/data_dir.php');
require_once('modules/Base/Theme/resolver.php');

$rel = isset($_GET['f']) && is_string($_GET['f']) ? $_GET['f'] : '';

$theme = isset($_GET['t']) && is_string($_GET['t']) ? $_GET['t'] : 'default';
if (!preg_match('/^[A-Za-z0-9_-]+$/', $theme)) $theme = 'default';

$file = Base_ThemeResolver::resolve($rel, $theme);

// resolve_theme_file() already rejects traversal and only ever returns paths
// inside modules/ or the data theme dir, but re-check against the project root
// before handing any bytes to the browser.
$root = realpath('.');
$real = $file !== null ? realpath($file) : false;
if ($real === false || strncmp($real, $root . DIRECTORY_SEPARATOR, strlen($root) + 1) !== 0 || !is_file($real)) {
    header('HTTP/1.1 404 Not Found');
    exit('Not Found');
}

$types = array(
    'png' => 'image/png', 'gif' => 'image/gif', 'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg', 'svg' => 'image/svg+xml', 'ico' => 'image/x-icon',
    'webp' => 'image/webp', 'css' => 'text/css', 'js' => 'application/javascript',
    'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf',
);
$ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
if (!isset($types[$ext])) {
    header('HTTP/1.1 404 Not Found');
    exit('Not Found');
}

$mtime = filemtime($real);
$etag = '"' . md5($real . $mtime . filesize($real)) . '"';

header('Content-Type: ' . $types[$ext]);
header('Cache-Control: public, max-age=31536000');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
header('ETag: ' . $etag);

$inm = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : null;
$ims = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) : null;
if ($inm === $etag || ($ims !== null && $ims >= $mtime)) {
    header('HTTP/1.1 304 Not Modified');
    exit();
}

header('Content-Length: ' . filesize($real));
readfile($real);
