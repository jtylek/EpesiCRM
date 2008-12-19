<?php
if(!isset($_GET['cid']) || !is_numeric($_GET['cid'])) {
	die('Invalid request');
}
define('CID',$_GET['cid']);
require_once('../../../include.php');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
	  <meta content="text/html; charset=UTF-8" http-equiv="content-type">
	  <title>epesi help</title>
	  <link href="help.css" type="text/css" rel="stylesheet"/>
<?php
	require_once('libs/minify/Minify/Build.php');
	$jses = array('libs/prototype.js');
	$jsses_build = new Minify_Build($jses);
	$jsses_src = $jsses_build->uri('../../../serve.php?'.http_build_query(array('f'=>array_values($jses))));
?>
		<script type="text/javascript" src="<?php print($jsses_src)?>"></script>
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
<?php
$h = & $_SESSION['client']['help'];
foreach($h as $c=>$txt) {
	$id = md5($c);
	print('<h2><a href="javascript:void(0)" onClick="$(\''.$id.'\').toggle()">'.$c.'</a></h2>'); //TODO: expandable titles
	print('<div id="'.$id.'" style="display: none;">'.file_get_contents($txt).'</div>');
}
?>
				</td>
			</tr>
		</table>
		</center>
		<br>
		<center>
		<span class="footer">Copyright &copy; 2008 &bull; <a href="http://www.telaxus.com">Telaxus LLC</a></span>
		<br>
		<p><a href="http://www.epesi.org"><img src="../../../images/epesi-powered.png" border="0"></a></p>
		</center>
</body>
</html>
