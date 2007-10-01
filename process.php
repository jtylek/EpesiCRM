<?php
ob_start();
header("content-type: application/javascript");

if(!isset($_POST['url']) || !isset($_POST['client']))
	die('alert(\'Invalid request\');');

require_once('include.php');

if(!isset($_SESSION['num_of_clients'])) {
	Epesi::alert('Session expired, restarting epesi');
	Epesi::redirect();
	Epesi::send_output();
} else {
	ob_start(array('ErrorHandler','handle_fatal'));
	Epesi::process($_POST['client'],$_POST['url'],isset($_POST['history'])?$_POST['history']:false);
	ob_end_flush();
}

$content = ob_get_contents();
ob_end_clean();

if(GZIP_OUTPUT && function_exists('ob_gzhandler') ) {
	ob_start("ob_gzhandler");
	echo $content;
	ob_end_flush();
} else {
	echo $content;
}
?>