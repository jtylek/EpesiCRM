<?php
/**
 * Index file
 *
 * This file includes all 'include files', loads modules
 * and gets output of default module.
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2006-2020 Janusz Tylek
 * @license MIT
 * @version 1.9.0 
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
    header("Location: update.php");
    exit();
}

$tables = DB::MetaTables();
if(!in_array('modules',$tables) || !in_array('variables',$tables) || !in_array('session',$tables))
	die('Database structure you are using is apparently out of date or damaged. If you didn\'t perform application update recently you should try to restore the database. Otherwise, please refer to EPESI documentation in order to perform database update.');

ob_start();
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

	<head profile="http://www.w3.org/2005/11/profile">
		
		<link rel="icon" type="image/png" href="images/favicon.png">
		<link type="text/css" href="include/epesi_stat_box.css" rel="stylesheet">
		<title><?php print(EPESI);?></title>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="robots" content="NOINDEX, NOARCHIVE">
		<meta name="epesi_ver" content="1.9.0">

		<?php
		ini_set('include_path', 'libs/minify' . PATH_SEPARATOR . '.' . PATH_SEPARATOR . 'libs' . PATH_SEPARATOR . ini_get('include_path'));
		require_once('Minify/Build.php');
		$jquery = DEBUG_JS ? 'libs/jquery-1.11.3.js' : 'libs/jquery-1.11.3.min.js';
		$jquery_migrate = DEBUG_JS ? 'libs/jquery-migrate-1.2.1.js' : 'libs/jquery-migrate-1.2.1.min.js';
		$jses = array('libs/prototype.js', $jquery, $jquery_migrate, 'libs/jquery-ui-1.10.1.custom.min.js', 'libs/HistoryKeeper.js', 'include/epesi.js');
		if(!DEBUG_JS) {
			$jsses_build = new Minify_Build($jses);
			$jsses_src = $jsses_build->uri('serve.php?' . http_build_query(array('f' => array_values($jses))));
		echo("<script type='text/javascript' src='$jsses_src'></script>");
			} else {
		foreach($jses as $js)
			print("<script type='text/javascript' src='$js'></script>");
			}
		$csses = array('libs/jquery-ui-1.10.1.custom.min.css');
		$csses_build = new Minify_Build($csses);
		$csses_src = $csses_build->uri('serve.php?'.http_build_query(array('f'=>array_values($csses))));
		
		if (DIRECTION_RTL) print('body { direction: rtl; }');
		if (DIRECTION_RTL) print('class="epesi_rtl"');
		print(TRACKING_CODE);
		?>

	</head>

	<body>
		

		<div class="status" id="epesiStatus" style="visibility: hidden;"><?php print(STARTING_MESSAGE);?></div>

		<!-- TO DO Load them via CSS background images <img src="images/logo.png">
		<img src="images/loader.gif"> -->

		<div id="debug_content" style="padding-top:100px;display:none;">
				<div class="button" onclick="$('error_box').innerHTML='';$('debug_content').style.display='none';">Hide</div>
				<div id="debug"></div>
				<div id="error_box"></div>
			</div>

		</div>
		
		<?php 
        /*
         * init_js file allows only num_of_clients sessions. If there is image
         * with empty src="" browser will load index.php file, so we cannot
         * include init_js file directly because num_of_clients request will
         * reset our history and restart EPESI.
         * 
         * Check here if request accepts html. If it does we can assume that
         * this is request for page and include init_js file which is faster.
         * If there is not 'html' in accept use script with src property.
         */
        
		if(isset($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'html') !== false) { ?>
			<script type="text/javascript"><?php require_once 'init_js.php'; ?></script>
        	<?php } else { ?>
				<script type="text/javascript" src="init_js.php?<?php print(http_build_query($_GET));?>"></script>
        		<?php } ?>
        		<noscript>Please enable JavaScript in your browser and let <?php print(EPESI);?> work!</noscript>
	</body>
</html>

<?php
$content = ob_get_contents();
ob_end_clean();

require_once('libs/minify/HTTP/Encoder.php');
$he = new HTTP_Encoder(array('content' => $content));
if (MINIFY_ENCODE)
	$he->encode();
$he->sendAll();
?>
