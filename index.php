<?php
/**
 * Index file
 *
 * This file includes all 'include files', loads modules
 * and gets output of default module.
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @license SPL
 * @version 1.0
 * @package epesi-base
 */
if(version_compare(phpversion(), '5.0.0')==-1)
	die("You are running an old version of PHP, php5 required.");

if(!file_exists('data/config.php')) {
	header('Location: setup.php');
	exit();
}

define('_VALID_ACCESS',1);
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
		$ret = ob_get_clean();
		if(trim($ret))
			die($ret);
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
		<script type="text/javascript" src="serve.php?f=libs/prototype.js,libs/HistoryKeeper.js,include/epesi.js"></script>

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
				font-family: "Tahoma" "Verdana" "Vera-Sans" "DejaVu-Sans";
				font-size: 11px;
				border: 5px solid #FFFFFF;
            }
            body {
                /*background-color: #5C5C5C;*/
            }
		</style>
		<script type="text/javascript">
		<!--
		function epesi_init() {
			var browser=navigator.appName;
			var b_version=navigator.appVersion;
			var version=parseFloat(b_version);
			if (browser=="Microsoft Internet Explorer") {
				alert("Sorry but Internet Explorer is not supported.\nPlease upgrade	to Firefox.");
				window.location = "http://www.mozilla.com/firefox/";
			}

			history_call = function(history_id){
        		switch(history_on){
				    case -1: history_on=1;
			    		return;
					case 1: Epesi.request('',history_id);
				}
			}

			history_add = function(id){
				history_on=-1;
				unFocus.History.addHistory(id);
			}

			Epesi.client_id=<?php print($client_id); ?>;
			Epesi.process_file='<?php print(rtrim(str_replace('\\','/',dirname($_SERVER['PHP_SELF'])),'/').'/process.php'); ?>';

			var history_on=1;
			history_add(0);
			Epesi.request('',0);
			unFocus.History.addEventListener('historyChange',history_call);
		}
		-->
		</script>
	</head>
	<body onload="epesi_init()">
		<div id="body_content">
		<div id="main_content"></div>

		<?php
			if(defined('DEBUG'))
				print('<div id="debug" style="font-size: 0.8em;"></div>');
		?>
		<div id="error_box" onclick="this.innerHTML = ''"></div>
		<div id="epesiStatus">
			<table cellspacing="0" cellpadding="0" border="0" style="width: 100%;">
                <tr>
                    <td><img src="images/logo.png" width="550" height="200" border="0"></td>
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
ob_end_flush();
?>
