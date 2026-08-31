<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 *
 * Serves one or more CSS/JS files from this project, combined and minified
 * through Minify's "Files" controller. modules/Base/Theme/theme_css.php is
 * the same thing with one addition (theme-aware url() rewriting for module
 * CSS) - see include/serve_minified.php, which both share.
 */
require_once __DIR__ . '/include/serve_minified.php';
epesi_serve_minified(array('css', 'js'));

header("HTTP/1.0 404 Not Found");
echo "HTTP/1.0 404 Not Found";
