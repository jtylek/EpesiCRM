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

define('_VALID_ACCESS',1);
require_once('include/data_dir.php');
if(!file_exists(DATA_DIR.'/config.php')) {
	header('Location: setup.php');
	exit();
}

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
      <title>Epesi update</title>
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
				Updating epesi from version <?php echo $cur_ver; ?> to <?php echo EPESI_VERSION; ?>. This operation may take several minutes.
				<a href="index.php?up=1">Click here to proceed</a>
                </td>
            </tr>
        </table>
        </center>
        <br>
        <center>
        <span class="footer">Copyright &copy; 2008 &bull; <a href="http://www.telaxus.com">Telaxus LLC</a></span>
        <br>
        <p><a href="http://www.epesi.org"><img src="images/epesi-powered.png" border="0"></a></p>
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
	die('Database structure you are using is apparently out of date or damaged. If you didn\'t perform application update recently you should try to restore the database. Otherwise, please refer to epesi documentation in order to perform database update.');

require_once('include/misc.php');

if(detect_iphone()) {
	if(!isset($_GET['force_epesi'])) {
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8" />
	<meta id="viewport" name="viewport" content="width=device-width; initial-scale=1.0; maximum-scale=1.0; user-scalable=0;" />
	<title>epesi CRM</title>
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
		<h1>epesi CRM</h1>
</div>

Please choose epesi version:<ul>
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

if(defined('CID')) {
	if(constant('CID')!==false) die('Invalid update script defined custom CID. Please try to refresh site manually.');
} else
	define('CID',false); //i know that i won't access $_SESSION['client']
require_once('include/session.php');

$client_id = isset($_SESSION['num_of_clients'])?$_SESSION['num_of_clients']:0;
$client_id_next = $client_id+1;
if($client_id_next==5) $client_id_next=0;
$_SESSION['num_of_clients'] = $client_id_next;
DB::Execute('DELETE FROM session_client WHERE session_name=%s AND client_id=%d',array(session_id(),$client_id));
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

		<head profile="http://www.w3.org/2005/11/profile">
		<link rel="icon" type="image/png" href="images/favicon.png" />
		<title>Epesi</title>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<meta http-equiv="X-UA-Compatible" content="IE=8" />
<?php
	ini_set('include_path','libs/minify'.PATH_SEPARATOR.'.'.PATH_SEPARATOR.'libs'.PATH_SEPARATOR.ini_get('include_path'));
	require_once('Minify/Build.php');
	$jses = array('libs/prototype.js','libs/HistoryKeeper.js','include/epesi.js');
	$jsses_build = new Minify_Build($jses);
	$jsses_src = $jsses_build->uri('serve.php?'.http_build_query(array('f'=>array_values($jses))));
?>
		<script type="text/javascript" src="<?php print($jsses_src)?>"></script>

		<style type="text/css">
			#epesiStatus {
  				/* Netscape 4, IE 4.x-5.0/Win and other lesser browsers will use this */
  				position: absolute;
  				left: 50%; top: 30%;
                margin-left: -280px;
  				/* all */
  				/*background-color: #e6ecf2;*/
  				background-color: white;
				border: 2px solid #336699;
				visibility: hidden;
				width: 560px;
				text-align: center;
				vertical-align: middle;
                color: #336699;
			}
			#epesiStatus table {
				color: #336699;
				font-weight: bold;
				font-family: Tahoma, Verdana, Vera-Sans, DejaVu-Sans;
				font-size: 11px;
				border: 5px solid #FFFFFF;
            }
		</style>
	</head>
	<body onload="Epesi.init(<?php print($client_id); ?>,'<?php print(rtrim(str_replace('\\','/',dirname($_SERVER['PHP_SELF'])),'/').'/process.php'); ?>','<?php print(http_build_query($_GET));?>')">
		<div id="body_content">
		<div id="main_content"></div>
		<div style="padding-top:97px;">
			<div id="debug"></div>
			<div id="error_box" onclick="this.innerHTML = ''"></div>
		</div>
		<div id="epesiStatus">
			<table cellspacing="0" cellpadding="0" border="0" style="width: 100%;">
                <tr>
                    <td><img src="images/logo.gif" width="550" height="200" border="0"></td>
                </tr>
				<tr>
					<td style="text-align: center; vertical-align: center; height: 40px;"><span id="epesiStatusText">Starting epesi ...<span></td>
                </tr>
                <tr>
					<td style="text-align: center; vertical-align: center; height: 30px;"><img src="images/loader.gif" width="256" height="10" border="0"></td>
				</tr>
			</table>
		</div>

		</div>
	</body>
</html>
<?php
$content = ob_get_contents();
ob_end_clean();

require_once('libs/minify/HTTP/Encoder.php');
$he = new HTTP_Encoder(array('content' => $content));
$he->encode();
$he->sendAll();
?>
