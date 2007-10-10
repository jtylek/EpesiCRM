<?php
ob_start();
header("Content-type: text/javascript");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // date in the past
header('Status: 404');

if(!isset($_POST['url']) || !isset($_SERVER['HTTP_X_CLIENT_ID']))
	die('alert(\'Invalid request\');');

require_once('include.php');

if(!isset($_SESSION['num_of_clients'])) {
	Epesi::alert('Session expired, restarting epesi');
	Epesi::redirect();
	Epesi::send_output();
} else {
	ob_start(array('ErrorHandler','handle_fatal'));
	Epesi::process($_POST['url'],isset($_POST['history'])?$_POST['history']:false);
	ob_end_flush();
}

$content = ob_get_contents();
ob_end_clean();

file_put_contents('data/x',str_replace('Epesi.',"\nEpesi.",$content));
if(GZIP_OUTPUT && function_exists('ob_gzhandler') ) {
	ob_start("ob_gzhandler");
	echo $content;
	ob_end_flush();
} else {
	echo $content;
}
?>