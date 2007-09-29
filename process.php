<?php
ob_start();
header("content-type: application/javascript");

if(!isset($_POST['url']) || !isset($_POST['client']))
	die('alert(\'Invalid request\');');

require_once('include.php');
require_once('base.php');

ob_start(array('ErrorHandler','handle_fatal'));

Epesi::init($_POST['client']);
global $base;
$base = new Base();
$base->process($_POST['url'],isset($_POST['history'])?$_POST['history']:false);
Epesi::send_output();
ob_end_flush();

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