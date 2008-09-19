<?php
//TODO: load_css, menu tree

define('CID',false);
require_once('include.php');
ModuleManager::load_modules();
ob_start();
$page = false;
$caption = '&nbsp;';
$menus = ModuleManager::call_common_methods('mobile_menu');
foreach($menus as $m=>$r) {
	if(!is_array($r)) continue;
	foreach($r as $cap=>$met) {
		$method = array($m.'Common',$met);
		$md5 = md5(serialize($method));
		if(isset($_GET['page']) && $_GET['page']==$md5) {
			$page = $method;
			$caption = $cap;
		}
		print('<a href="mobile.php?'.http_build_query(array('page'=>$md5)).'">'.$cap.'</a><br>');
	}
}
if($page) {
	$menu = ob_get_clean();

	ob_start();
	$ret = call_user_func($page);
	$body = ob_get_clean();

	$caption .= ' <a href="mobile.php">back</a>';

	if(isset($ret) && $ret===false)
		header('Location: mobile.php');
} else {
	$body = ob_get_clean();
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
      <meta content="text/html; charset=ISO-8859-1" http-equiv="content-type">
      <title>Epesi</title>
      <link href="setup.css" type="text/css" rel="stylesheet"/>
</head>
<body>
        <table id="banner" border="0" cellpadding="0" cellspacing="0">
            <tr>
                <td class="image">&nbsp;</td>
                <td class="back"><?php print($caption); ?></td>
            </tr>
        </table>
        <br>
        <center>
        <table id="main" border="0" cellpadding="0" cellspacing="0">
            <tr>
                <td>
				<?php print($body); ?>
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
