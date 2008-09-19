<?php
define('CID',false);
require_once('include.php');
ModuleManager::load_modules();
ob_start();
$page = false;
$caption = '&nbsp;';
/*
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
		print('<a href="iphone.php?'.http_build_query(array('page'=>$md5)).'">'.$cap.'</a><br>');
	}
}
*/
if($page) {
	$menu = ob_get_clean();

	ob_start();
	$ret = call_user_func($page);
	$body = ob_get_clean();

	$caption .= ' <a href="iphone.php">back</a>';

	if(isset($ret) && $ret===false)
		header('Location: iphone.php');
} else {
	$body = ob_get_clean();
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8" />
	<meta id="viewport" name="viewport" content="width=320; initial-scale=1.0; maximum-scale=1.0; user-scalable=0;" />
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
		<a href="iphone.php" id="backButton">Logout</a>
</div>
	
<a href="http://sun.telaxus.com/test/uiuikit/index.html" class="green button">Agenda</a>
<a href="http://sun.telaxus.com/test/uiuikit/index.html" class="red button">Contacts</a>
<a href="http://sun.telaxus.com/test/uiuikit/index.html" class="white button">Tasks</a>

<?php print($body); ?>
        
</body>
</html>
