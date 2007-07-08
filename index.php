<?php
/**
 * Index file
 * 
 * This file includes all 'include files', loads modules 
 * and gets output of default module.
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @licence SPL
 * @version 1.0
 * @package epesi-base
 */
if(version_compare(phpversion(), '5.0.0')==-1)
	die("You are running an old version of PHP, php5 required.");

if(!file_exists('data/config.php')) {
	header('Location: setup.php');
	exit();
}


require_once('libs/saja/saja.php');
require_once('include.php');

global $saja;
$saja = new saja();
$saja->set_process_file('base.php');
if(SECURE_HTTP)
    $saja->secure_http();

$client_id = isset($_SESSION['num_of_clients'])?$_SESSION['num_of_clients']:0;
$client_id_next = $client_id+1;
if($client_id_next==5) $client_id_next=0;
$_SESSION['num_of_clients'] = $client_id_next;
unset($_SESSION['cl'.$client_id]);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

		<head profile="http://www.w3.org/2005/11/profile">
		<link rel="icon" type="image/png" href="images/favicon.png" />
		<title>Epesi</title>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<script type="text/javascript">var SAJA_PATH="<?php print(dirname($_SERVER['SCRIPT_NAME']).'/'); ?>"; var SAJA_HTTP_KEY="<?php print $saja->http_key; ?>"</script>
		<script type="text/javascript" src="libs/saja/saja.js"></script>
		<script type="text/javascript" src="libs/HistoryKeeper.js"></script>
		<script type="text/javascript" src="include/utils.js"></script>
		
		<style type="text/css">
			#sajaStatus {
  				/* Netscape 4, IE 4.x-5.0/Win and other lesser browsers will use this */
  				position: absolute;
  				left: 40%; top: 45%;
  				/* all */
  				background-color: #F0F0F0;
				border: 1px solid #CCCCCC;
				color: #B2B2B2;
				font-weight: bold;
				visibility: hidden;
				padding-top: 10px;
				padding-bottom: 10px;
				width: 20%;
				text-align: center;
				font-family: "Tahoma" "Verdana" "Vera-Sans" "DejaVu-Sans";
				font-size: 11px;
				vertical-align: center;
			}
		</style>
	</head>
	<body>
		<div id="main_content"></div>
		<div id="sajaStatus">
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
		var client_id=<?php echo $client_id; ?>;
		var history_on=1;
		history_call = function(history_id){
        		switch(history_on){
			    case -1: history_on=1;
			    case 0: return;
			    case 1: <?php print $saja->run("process($client_id,'',history_id)"); ?>
			}
		}
		
		history_add = function(id){
			history_on=-1;
			unFocus.History.addHistory(id);
		}
		
		unFocus.History.addEventListener('historyChange',history_call);
		
		if(window.location.hash!='#1')
			window.location = '#1';
		else
			<?php print $saja->run("process(client_id,'',0)"); ?>
			
		-->
		</script>
	</body>
</html>
