<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license Commercial
 * @version 1.0
 * @package epesi-premium
 * @subpackage timesheet
 */

define('CID',false); 
require_once('../../../include.php');
ModuleManager::load_modules();

if (!isset($_GET['first_name']) ||
	!isset($_GET['last_name']) ||
	!isset($_GET['lang']) ||
	!isset($_GET['credits']) ||
	!isset($_GET['credits_website']) ||
	!isset($_GET['contact_email']) ||
	!isset($_GET['ip']) ||
	!isset($_GET['original']) ||
	!isset($_GET['translation']))
	die('Invalid request');

$ip = $_GET['ip'];
$last_name = $_GET['last_name'];
$first_name = $_GET['first_name'];
$credits = $_GET['credits'];
$credits_website = $_GET['credits_website'];
$contact_email = $_GET['contact_email'];
$uid = Develop_TranslationsCommon::get_user($first_name, $last_name, $ip, $credits, $credits_website, $contact_email);
$lang = $_GET['lang'];
$org = $_GET['original'];
$trans = $_GET['translation'];
DB::Execute('INSERT INTO develop_trans_contribs (user_id,lang,org,trans,used,discarded,received_on) VALUES (%d, %s, %s, %s, 0, 0, %T)', array($uid, $lang, $org, $trans, date('Y-m-d H:i:s')));
print('OK;');
?>
