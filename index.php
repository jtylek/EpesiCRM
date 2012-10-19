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
if(version_compare(phpversion(), '5.0.0')==-1)
	die("You are running an old version of PHP, php5 required.");

if(trim(ini_get("safe_mode")))
	die('You cannot use EPESI with PHP safe mode turned on - please disable it. Please notice this feature is deprecated since PHP 5.3 and will be removed in PHP 6.0.');

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
require_once('include/error.php');
ob_start(array('ErrorHandler','handle_fatal'));
require_once('include/database.php');
require_once('include/variables.php');
$cur_ver = Variable::get('version');
if($cur_ver!==EPESI_VERSION) {
	if(isset($_GET['up'])) {
		require_once('update.php');
		$retX = ob_get_clean();
		if(trim($retX))
			die($retX);
		header('Location: index.php');
		exit();
	} else {
		?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
      <meta content="text/html; charset=ISO-8859-1" http-equiv="content-type">
      <title>EPESI update</title>
      <link href="setup.css" type="text/css" rel="stylesheet"/>
</head>
<body>
        <table id="banner" border="0" cellpadding="0" cellspacing="0">
            <tr>
                <td class="image">&nbsp;</td>
                <td class="back">&nbsp;</td>
            </tr>
        </table>
        <br>
        <center>
        <table id="main" border="0" cellpadding="0" cellspacing="0">
            <tr>
                <td>
				Updating EPESI from version <?php echo $cur_ver; ?> to <?php echo EPESI_VERSION; ?>. This operation may take several minutes.
				<a href="index.php?up=1">Click here to proceed</a>
                </td>
            </tr>
        </table>
        </center>
        <br>
        <center>
        <span class="footer">Copyright &copy; 2008 &bull; <a href="http://www.telaxus.com">Telaxus LLC</a></span>
        <br>
        <p><a href="http://www.epesi.org"><img src="images/epesi-powered.png" alt="image" border="0"></a></p>
        </center>
</body>
</html>
<?php
		ob_end_flush();
		exit();
	}
}

$tables = DB::MetaTables();
if(!in_array('modules',$tables) || !in_array('variables',$tables) || !in_array('session',$tables))
	die('Database structure you are using is apparently out of date or damaged. If you didn\'t perform application update recently you should try to restore the database. Otherwise, please refer to EPESI documentation in order to perform database update.');

require_once('include/misc.php');

if(IPHONE) {
	if(!isset($_GET['force_epesi'])) {
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8" />
	<meta id="viewport" name="viewport" content="width=device-width; initial-scale=1.0; maximum-scale=1.0; user-scalable=0;" />
	<title>EPESI CRM</title>
	<link rel="stylesheet" href="libs/UiUIKit/stylesheets/iphone.css" />
	<link rel="apple-touch-icon" href="images/apple-touch-icon.png" />
	<script type="text/javascript" charset="utf-8">
		window.onload = function() {
		  setTimeout(function(){window.scrollTo(0, 1);}, 100);
		}
	</script>
</head>

<body>
<div id="header">
		<h1>EPESI CRM</h1>
</div>

Please choose EPESI version:<ul>
<li><a href="mobile.php" class="white button">mobile</a><br>
<li><a href="index.php?force_epesi=1" class="green button">desktop</a>
</ul>

</body>
</html>
<?php
		ob_end_flush();
		exit();
	}
} elseif(detect_mobile_device()) {
	header('Location: mobile.php');
	exit();
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

		<head profile="http://www.w3.org/2005/11/profile">
		<link rel="icon" type="image/png" href="images/favicon.png" />
		<link rel="apple-touch-icon" href="images/apple-favicon.png" />
		<title>EPESI</title>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<meta http-equiv="X-UA-Compatible" content="IE=8" />
		<meta name="SKYPE_TOOLBAR" content="SKYPE_TOOLBAR_PARSER_COMPATIBLE" /> 
<?php
	ini_set('include_path','libs/minify'.PATH_SEPARATOR.'.'.PATH_SEPARATOR.'libs'.PATH_SEPARATOR.ini_get('include_path'));
	require_once('Minify/Build.php');
	$jses = array('libs/prototype.js','libs/jquery-1.7.2.min.js','libs/jquery-ui-1.8.21.custom.min.js','libs/HistoryKeeper.js','include/epesi.js');
	$jsses_build = new Minify_Build($jses);
	$jsses_src = $jsses_build->uri('serve.php?'.http_build_query(array('f'=>array_values($jses))));
?>
		<script type="text/javascript" src="<?php print($jsses_src)?>"></script>
<?php
	$csses = array('libs/jquery-ui-1.8.21.custom.css');
	$csses_build = new Minify_Build($csses);
	$csses_src = $csses_build->uri('serve.php?'.http_build_query(array('f'=>array_values($csses))));
?>
		<link type="text/css" href="<?php print($csses_src)?>" rel="stylesheet"></link>

		<style type="text/css">
			<?php if (DIRECTION_RTL) print('body { direction: rtl; }'); ?>
			#epesiStatus {
  				/* Netscape 4, IE 4.x-5.0/Win and other lesser browsers will use this */
  				position: fixed;
  				left: 50%; top: 30%;
                margin-left: -280px;
  				/* all */
  				/*background-color: #e6ecf2;*/
  				background-color: white;
				border: 5px solid #336699;
				visibility: hidden;
				width: 560px;
				text-align: center;
				vertical-align: middle;
				z-index: 2002;
                color: #336699;
				overflow: hidden;
				
				/* css3 shadow border*/
				-webkit-box-shadow: 1px 1px 15px black;
				-moz-box-shadow: 1px 1px 15px black;
				box-shadow: 1px 1px 15px black;
				/* end css3 shadow border*/
				
				/* border radius */
				-webkit-border-radius: 6px;
				-moz-border-radius: 6px;
				border-radius: 6px;
				/* end border radius */
			}
			#epesiStatus table {
				color: #336699;
				font-weight: bold;
				font-family: Tahoma, Verdana, Vera-Sans, DejaVu-Sans;
				font-size: 11px;
				border: 5px solid #FFFFFF;
            }

		</style>
		<?php print(TRACKING_CODE); ?>
	</head>
	<body <?php if (DIRECTION_RTL) print('class="epesi_rtl"'); ?> >

		<div id="body_content">
			<div id="main_content" style="display:none;"></div>
			<div id="debug_content" style="padding-top:97px;display:none;">
				<div class="button" onclick="$('error_box').innerHTML='';$('debug_content').style.display='none';">Hide</div>
				<div id="debug"></div>
				<div id="error_box"></div>
			</div>
			
			<div id="epesiStatus">
				<table cellspacing="0" cellpadding="0" border="0" style="width: 100%;">
					<tr>
						<td><img src="images/logo.png" alt="logo" width="550" height="200" border="0"></td>
					</tr>
					<tr>
						<td style="text-align: center; vertical-align: middle; height: 30px;"><span id="epesiStatusText"><?php print(STARTING_MESSAGE);?></span></td>
					</tr>
					<tr>
						<td style="text-align: center; vertical-align: middle; height: 30px;"><img src="images/loader.gif" alt="loader" width="256" height="10" border="0"></td>
					</tr>
				</table>
			</div>	
		</div>
		<script type="text/javascript" src="init_js.php?<?php print(http_build_query($_GET));?>"></script>
        <noscript>Please enable JavaScript in your browser and let EPESI work!</noscript>
		<?php if(IPHONE) { ?>
		<script type="text/javascript">var iphone=true;</script>
		<?php } ?>
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
