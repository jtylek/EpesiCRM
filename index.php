<?php
/**
 * Index file
 *
 * This file includes all 'include files', loads modules
 * and gets output of default module.
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 */
if(version_compare(phpversion(), '5.5.0')==-1)
	die("You are running an old version of PHP, php 5.5 required.");

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

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

		<head profile="http://www.w3.org/2005/11/profile">
		<link rel="icon" type="image/png" href="images/favicon.png" />
		<link rel="apple-touch-icon" href="images/apple-favicon.png" />
		<title><?php print(EPESI);?></title>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="SKYPE_TOOLBAR" content="SKYPE_TOOLBAR_PARSER_COMPATIBLE" />
        <meta name="robots" content="NOINDEX, NOARCHIVE">
            <script type='text/javascript' src='dist/index.js'></script>
            <link href='dist/styles.css' rel="stylesheet" type="text/css">
            <style type="text/css">

                #epesi_loader {
                    font-weight: 300;
                    font-size: 15px;
                    background-color: white;
                    position: fixed;
                    left: 50%;
                    top: 30%;
                    margin-left: -180px;
                    width: 360px;
                    text-align: center;
                    vertical-align: middle;
                    z-index: 2002;
                    overflow: hidden;
                    padding-top: 20px;
                }
                #epesi_loader img {
                    padding: 15px;
                }

                #epesi_loader .text {
                    margin-top: 10px;
                    margin-bottom: 25px;
                    font-size: 22px;
                }

                #epesi_loader .spinner {
                    margin: 20px auto 20px;
                    text-align: center;
                }

                #epesi_loader .spinner > div {
                    width: 18px;
                    height: 18px;
                    background-color: #333;

                    border-radius: 100%;
                    display: inline-block;
                    -webkit-animation: sk-bouncedelay 1.4s infinite ease-in-out both;
                    animation: sk-bouncedelay 1.4s infinite ease-in-out both;
                }

                #epesi_loader .spinner .bounce1 {
                    -webkit-animation-delay: -0.32s;
                    animation-delay: -0.32s;
                }

                #epesi_loader .spinner .bounce2 {
                    -webkit-animation-delay: -0.16s;
                    animation-delay: -0.16s;
                }

                @-webkit-keyframes sk-bouncedelay {
                    0%, 80%, 100% { -webkit-transform: scale(0) }
                    40% { -webkit-transform: scale(1.0) }
                }

                @keyframes sk-bouncedelay {
                    0%, 80%, 100% {
                        -webkit-transform: scale(0);
                        transform: scale(0);
                    } 40% {
                          -webkit-transform: scale(1.0);
                          transform: scale(1.0);
                      }
                }





                .Base_StatusBar_background {
                    z-index: 2001;
                    position: fixed;
                    top: 0px;
                    left: 0px;
                    width: 100%;
                    height: 100%;
                    text-align: center;
                    vertical-align: middle;
                    background-color: rgba(255, 255, 255, 0.5);
                }

                .Base_StatusBar {
                    background-color: white;
                    position: fixed;
                    left: 50%;
                    top: 30%;
                    margin-left: -100px;
                    width: 200px;
                    text-align: center;
                    vertical-align: middle;
                    z-index: 2002;
                    overflow: hidden;
                    padding-top: 20px;
                }

                .Base_StatusBar .text {
                    font-size: 22px;
                }

                .Base_StatusBar .spinner {
                    margin: 20px auto 20px;
                    text-align: center;
                }

                .Base_StatusBar .spinner > div {
                    width: 18px;
                    height: 18px;
                    background-color: #333;

                    border-radius: 100%;
                    display: inline-block;
                    -webkit-animation: sk-bouncedelay 1.4s infinite ease-in-out both;
                    animation: sk-bouncedelay 1.4s infinite ease-in-out both;
                }

                .Base_StatusBar .spinner .bounce1 {
                    -webkit-animation-delay: -0.32s;
                    animation-delay: -0.32s;
                }

                .Base_StatusBar .spinner .bounce2 {
                    -webkit-animation-delay: -0.16s;
                    animation-delay: -0.16s;
                }

                @-webkit-keyframes sk-bouncedelay {
                    0%, 80%, 100% { -webkit-transform: scale(0) }
                    40% { -webkit-transform: scale(1.0) }
                }

                @keyframes sk-bouncedelay {
                    0%, 80%, 100% {
                        -webkit-transform: scale(0);
                        transform: scale(0);
                    } 40% {
                          -webkit-transform: scale(1.0);
                          transform: scale(1.0);
                      }
                }

            </style>
		<?php print(TRACKING_CODE); ?>
	</head>
	<body class="<?php if (DIRECTION_RTL) print(' epesi_rtl'); ?>" >

		<div id="body_content" class="page">
			<div id="main_content" class="page-main" style="display:none;"></div>
			<div id="debug_content" style="padding-top:97px;display:none;">
				<div class="button" onclick="jq('#error_box').html('');jq('#debug_content').hide();">Hide</div>
				<div id="debug"></div>
				<div id="error_box"></div>
			</div>

            <div id="epesi_loader" class="panel panel-default">
                <img src="images/epesi_logo_RGB_Solid.png">
                <div class="lead text" id="epesiStatusText"><?php print(STARTING_MESSAGE);?></div>
                <div class="spinner">
                    <div class="bounce1"></div>
                    <div class="bounce2"></div>
                    <div class="bounce3"></div>
                </div>
            </div>
            <div id="Base_StatusBar" class="Base_StatusBar_background">
                <div class="Base_StatusBar panel panel-default">
                    <p id="statusbar_text" class="lead">Loading...</p>
                    <div class="spinner">
                        <div class="bounce1"></div>
                        <div class="bounce2"></div>
                        <div class="bounce3"></div>
                    </div>
                    <div id="dismiss">Click anywhere to dismiss</div>
                </div>
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
