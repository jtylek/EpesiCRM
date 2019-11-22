<?php
if(!isset($_POST['cid']) || !is_numeric($_POST['cid'])) {
	die('Invalid request');
}
define('CID',$_POST['cid']);
define('READ_ONLY_SESSION',true);
require_once('../../../include.php');
ModuleManager::load_modules();

$helps = ModuleManager::call_common_methods('help');
$html = '';

foreach($helps as $m=>$tutorials) {
	foreach ($tutorials as $tut) {
		if (!$tut['context']) continue;
		$html .= '<a href="javascript:void(0);" onclick="Helper.start_tutorial(\''.Epesi::escapeJS(trim($tut['steps'], '#')).'\')">'.$tut['label'].'</a>';
	}
}
print('$("Base_Help__help_suggestions").innerHTML = "'.Epesi::escapeJS($html).'";');
