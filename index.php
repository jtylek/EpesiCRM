<?php
/**
 * Index file
 *
 * This file includes all 'include files', loads modules
 * and gets output of default module.
 * @author Janusz Tylek
 * @copyright Copyright &copy; 2006-2026 by Janusz Tylek
 * @license MIT
 * @version 1.1
 * @package epesi-base
 */
if(version_compare(phpversion(), '7.0.0')==-1)
	die("You are running an old version of PHP, php 7.0 required.");

if(trim(ini_get("safe_mode")))
	die('You cannot use EPESI with PHP safe mode turned on - please disable it. Please notice this feature is deprecated since PHP 5.3 and will be removed in PHP 7.0.');

define('_VALID_ACCESS',1);
require_once('include/data_dir.php');
if(!file_exists(DATA_DIR.'/config.php')) {
	header('Location: setup.php');
	exit();
}

if(!is_writable(DATA_DIR))
	die('Cannot write into "'.DATA_DIR.'" directory. Please fix privileges.');

// require_once('include/include_path.php');
require_once('include/config.php');
require_once('include/maintenance_mode.php');
require_once('include/error.php');
require_once('include/misc.php');
require_once('include/database.php');
require_once('include/variables.php');
$tables = DB::MetaTables();
if(!in_array('modules',$tables) || !in_array('variables',$tables) || !in_array('session',$tables))
	// Same reasoning/self-contained styling as maintenance_mode.php's die()
	// page just above this in the require chain: Smarty/the theme system
	// aren't loaded yet at this point, so this can't link an external
	// stylesheet - everything needed is inlined here instead.
	die('<!DOCTYPE html><html lang="en"><head><meta charset="utf-8" />'
		. '<meta name="viewport" content="width=device-width, initial-scale=1" />'
		. '<title>Database Not Ready</title><style>'
		. 'body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;'
		. 'background-color:#f4f6f9;font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;color:#212529;}'
		. '.card{background:#fff;border-radius:0.5rem;box-shadow:0 0.5rem 1.5rem rgba(0,0,0,0.15);'
		. 'padding:2.5rem 2rem;max-width:28rem;width:90%;text-align:center;}'
		. '.icon{width:4rem;height:4rem;margin:0 auto 1.25rem;border-radius:50%;background-color:#fdecea;'
		. 'display:flex;align-items:center;justify-content:center;font-size:1.75rem;line-height:1;}'
		. 'h1{font-size:1.35rem;font-weight:600;margin:0 0 0.75rem;}'
		. 'p{margin:0;line-height:1.5;color:#495057;}'
		. '</style></head><body><div class="card">'
		. '<div class="icon">&#9888;</div>'
		. '<h1>Database structure out of date or damaged</h1>'
		. '<p>If you didn\'t perform an application update recently, you should try to restore the database. '
		. 'Otherwise, please refer to the EPESI documentation to perform a database update.</p>'
		. '</div></body></html>');
if(epesi_requires_update()) {
    header('Location: update.php');
    exit();
}

ob_start();

require_once('modules/Base/Theme/smarty/Smarty.class.php');
$smarty = new Smarty();
$smarty->template_dir = 'theme';
$smarty->compile_dir = TEMP_DIR.'/Base_Theme/compiled/';
$smarty->compile_id = 'root';
if (!is_dir($smarty->compile_dir)) mkdir($smarty->compile_dir, 0777, true);

$smarty->assign('EPESI', EPESI);
// IPHONE (detect_iphone(), include/misc.php) is unrelated to the retired
// mobile/desktop chooser below - still drives its own, separate behaviour
// (theme/index.tpl's iphone JS flag, tap-to-call links, calendar rendering
// tweaks elsewhere), so kept as-is.
$smarty->assign('IPHONE', (bool)IPHONE);

ini_set('include_path', 'libs/minify' . PATH_SEPARATOR . '.' . PATH_SEPARATOR . 'libs' . PATH_SEPARATOR . ini_get('include_path'));
require_once('Minify/Build.php');
$jquery = DEBUG_JS ? 'libs/jquery-1.11.3.js' : 'libs/jquery-1.11.3.min.js';
$jquery_migrate = DEBUG_JS ? 'libs/jquery-migrate-1.2.1.js' : 'libs/jquery-migrate-1.2.1.min.js';
$jses = array('libs/prototype.js', $jquery, $jquery_migrate, 'libs/jquery-ui-1.10.1.custom.min.js', 'libs/HistoryKeeper.js', 'include/epesi.js');
if(!DEBUG_JS) {
	$jsses_build = new Minify_Build($jses);
	$jsses_src = $jsses_build->uri('serve.php?' . http_build_query(array('f' => array_values($jses))));
	$js_tags_html = "<script type='text/javascript' src='$jsses_src'></script>";
} else {
	$js_tags_html = '';
	foreach($jses as $js)
		$js_tags_html .= "<script type='text/javascript' src='$js'></script>";
}
$csses = array('libs/jquery-ui-1.10.1.custom.min.css');
$csses_build = new Minify_Build($csses);
$csses_src = $csses_build->uri('serve.php?'.http_build_query(array('f'=>array_values($csses))));

$accepts_html = isset($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'html') !== false;
$init_js_inline = '';
if($accepts_html) {
	ob_start();
	require_once 'init_js.php';
	$init_js_inline = ob_get_clean();
}

$smarty->assign('js_tags_html', $js_tags_html);
$smarty->assign('csses_src', $csses_src);
$smarty->assign('DIRECTION_RTL', (bool)DIRECTION_RTL);
$smarty->assign('TRACKING_CODE', TRACKING_CODE);
$smarty->assign('STARTING_MESSAGE', STARTING_MESSAGE);
// Which theme styles the "Starting epesi..." splash below - inlined
// rather than calling Base_ThemeCommon::get_default_template() (that
// class extends ModuleCommon, which isn't required by this file's own,
// much smaller bootstrap chain - only include.php's full one loads it).
// Same Variable::get() + directory/glob check that method itself uses.
// Variable::get(..., false) doesn't throw - Base_Theme may not be installed
// yet (e.g. this splash renders before any module does, on a brand new setup).
$default_theme = Variable::get('default_theme', false) ?: 'adminltedark';
if ($default_theme !== 'default'
        && !glob('modules/*/*/theme_'.$default_theme, GLOB_ONLYDIR)
        && !glob('modules/*/*/*/theme_'.$default_theme, GLOB_ONLYDIR))
	$default_theme = 'default';
$smarty->assign('theme_name', $default_theme);
$smarty->assign('accepts_html', $accepts_html);
$smarty->assign('init_js_inline', $init_js_inline);
$smarty->assign('get_query_string', http_build_query($_GET));

$smarty->display('index.tpl');

$content = ob_get_contents();
ob_end_clean();

require_once('libs/minify/HTTP/Encoder.php');
$he = new HTTP_Encoder(array('content' => $content));
if (MINIFY_ENCODE)
	$he->encode();
$he->sendAll();
?>
