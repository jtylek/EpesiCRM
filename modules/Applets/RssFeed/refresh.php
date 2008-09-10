<?php
if(!isset($_POST['feed']) || !isset($_POST['number']))
	die('Invalid request');

require_once("rsslib.php");

function handle_rss_error($type, $message,$errfile,$errline,$errcontext) {
	die('Error getting RSS');
}
set_error_handler('handle_rss_error');

$feed = $_POST['feed'];
$num = $_POST['number'];

echo RSS_Display($feed, $num);
exit();
?>
