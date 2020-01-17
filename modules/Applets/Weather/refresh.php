<?php
/**
 * Simple RSS Feed applet
 * @author j@epe.si
 * @copyright 2008 Janusz Tylek
 * @license MIT
 * @version 1.0
 * @package epesi-applets
 * @subpackage rssfeed
 */

if(!isset($_POST['feed']) || !isset($_POST['number']) || !isset($_POST['cid']))
	die('Invalid request');

define('CID', $_POST['cid']);
define('READ_ONLY_SESSION',true);
require_once('../../../include.php');
ModuleManager::load_modules();

require_once("rsslib.php");

function handle_rss_error($type, $message,$errfile,$errline,$errcontext) {
	die(__('Error getting RSS'));
}
set_error_handler('handle_rss_error');

$feed = $_POST['feed'];
$num = $_POST['number'];

echo Utils_BBCodeCommon::parse(RSS_Display($feed, $num));
exit();
?>
