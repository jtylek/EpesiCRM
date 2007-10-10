<?php
if(!isset($_POST['feed']) || !isset($_POST['number']))
	die('Invalid request');

require_once('../../../include.php');
require_once("rsslib.php");
//ModuleManager::load_modules();

class MailClientErrorObserver extends ErrorObserver {
	public function update_observer($type, $message,$errfile,$errline,$errcontext) {
		die('Error getting RSS');
		return false;
	}
}

$err = new MailClientErrorObserver();
ErrorHandler::add_observer($err);

$feed = $_POST['feed'];
$num = $_POST['number'];

echo RSS_Display($feed, $num);

?>
