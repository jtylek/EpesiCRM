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
require_once('include/include_path.php');
require_once('include/config.php');
require_once('include/error.php');
ob_start(array('ErrorHandler','handle_fatal'));
require_once('include/database.php');
require_once('include/variables.php');
try {
$cur_ver = Variable::get('version');
} catch(Exception $s) {
$cur_ver = '';
}
if($cur_ver!==EPESI_VERSION)
	require_once('update.php');


$tables = DB::MetaTables();
if(!in_array('modules',$tables) || !in_array('variables',$tables) || !in_array('session',$tables))
	die('Database structure you are using is apparently out of date or damaged. If you didn\'t perform application update recently you should try to restore the database. Otherwise, please refer to epesi documentation in order to perform database update.');

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
		<script type="text/javascript" src="libs/prototype.js"></script>
		<script type="text/javascript" src="libs/HistoryKeeper.js"></script>
		<script type="text/javascript" src="include/epesi.js"></script>

		<style type="text/css">
			#epesiStatus {
  				/* Netscape 4, IE 4.x-5.0/Win and other lesser browsers will use this */
  				position: absolute;
  				left: 40%; top: 45%;
  				/* all */
  				background-color: #F0F0F0;
				border: 1px solid #B3B3B3;
				visibility: hidden;
				padding-top: 10px;
				padding-bottom: 10px;
				width: 20%;
				text-align: center;
				vertical-align: center;
			}
			#epesiStatus table {
				color: #B3B3B3;
				font-weight: bold;
				font-family: "Tahoma" "Verdana" "Vera-Sans" "DejaVu-Sans";
				font-size: 11px;
            }
		</style>
	</head>
	<body>
		<div id="main_content"></div>
		<div id="epesiStatus">
			<table cellspacing="0" cellpadding="0" border="0" style="width:100%;">
				<tr>
					<td style="text-align: center; vertical-align: center;"><img src="images/loader.gif" width="16" height="16" border="0"></td>
					<td style="text-align: center; vertical-align: center;">Starting epesi...</td>
				</tr>
			</table>
		</div>
		<?php
			if(defined('DEBUG'))
				print('<div id="debug" style="font-size: 0.7em;"></div>');
		?>
		<div id="error_box" onclick="this.innerHTML = ''"></div>
		<script type="text/javascript">
		<!--
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
		var history_on=1;
		history_add(0);
		Epesi.request('',0);
		unFocus.History.addEventListener('historyChange',history_call);
		-->
		</script>
	</body>
</html>
<?php
ob_end_flush();
?>
