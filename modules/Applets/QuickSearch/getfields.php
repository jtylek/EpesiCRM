<?php
ob_start();
define('CID',false);
require_once('../../../include.php');
ModuleManager::load_modules();

if((isset($_GET['tbName']) && $_GET['tbName'] != "") &&  (isset($_GET['tbCaption']) && $_GET['tbCaption'] != "")){
$tbName = $_GET['tbName'];
$tbCaption = $_GET['tbCaption'];
$arrayFields = Utils_RecordBrowserCommon::init($tbName);
	foreach($arrayFields as $key => $value){
		print "<option value=".$tbName.":".$value['id'].">".$tbCaption." - ".$value['name']."</option>";
	}
}
$content = ob_get_contents();
ob_end_clean();

require_once('libs/minify/HTTP/Encoder.php');
$he = new HTTP_Encoder(array('content' => $content));
if (MINIFY_ENCODE)
	$he->encode();
$he->sendAll();
exit();

?>