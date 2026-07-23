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
if(epesi_requires_update()) {
    header('Location: update.php');
    exit();
}
$tables = DB::MetaTables();
if(!in_array('modules',$tables) || !in_array('variables',$tables) || !in_array('session',$tables))
	die('Database structure you are using is apparently out of date or damaged. If you didn\'t perform application update recently you should try to restore the database. Otherwise, please refer to EPESI documentation in order to perform database update.');

ob_start();

if(IPHONE && !isset($_GET['force_desktop'])) {
	$show_iphone_prompt = true;
} elseif(!IPHONE && detect_mobile_device()) {
	header('Location: mobile.php');
	exit();
} else {
	$show_iphone_prompt = false;
}

require_once('modules/Base/Theme/smarty/Smarty.class.php');
$smarty = new Smarty();
$smarty->template_dir = 'theme';
$smarty->compile_dir = DATA_DIR.'/Base_Theme/compiled/';
$smarty->compile_id = 'root';

$smarty->assign('EPESI', EPESI);
$smarty->assign('IPHONE', (bool)IPHONE);
$smarty->assign('show_iphone_prompt', $show_iphone_prompt);

if(!$show_iphone_prompt) {
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
	$smarty->assign('accepts_html', $accepts_html);
	$smarty->assign('init_js_inline', $init_js_inline);
	$smarty->assign('get_query_string', http_build_query($_GET));
}

$smarty->display('index.tpl');

if($show_iphone_prompt)
	exit();

$content = ob_get_contents();
ob_end_clean();

require_once('libs/minify/HTTP/Encoder.php');
$he = new HTTP_Encoder(array('content' => $content));
if (MINIFY_ENCODE)
	$he->encode();
$he->sendAll();
?>
