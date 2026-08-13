<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license Commercial
 * @version 1.0
 * @package epesi-develop
 * @subpackage translations
 */

define('CID',false); 
require_once('../../../include.php');
ModuleManager::load_modules();

if (!Acl::is_user()) die();

$id = $_POST['tid'];
$to_lang = $_POST['to_lang'];

DB::Execute('UPDATE develop_trans_contribs SET lang=%s WHERE id=%d', array($to_lang, $id));

?>
