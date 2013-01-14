<?php
if(!isset($_POST['keywords']) || !isset($_POST['cid']) || !is_numeric($_POST['cid'])) {
	die('Invalid request');
}
define('CID',$_POST['cid']);
define('READ_ONLY_SESSION',true);
require_once('../../../include.php');
ModuleManager::load_modules();

$helps = ModuleManager::call_common_methods('help');
$html = '';

$words = explode(' ',trim($_POST['keywords']));
$results = array();
$i = count($words);
while ($i>=1) {
	$results[$i] = array();
	$i--;
	$words[$i] = strtolower($words[$i]);
}

foreach($helps as $m=>$tutorials) {
	foreach ($tutorials as $tut) {
		$match = 0;
		$l = strtolower($tut['label']);
		foreach ($words as $w)
			if (strpos($l, $w)!==false)
				$match++;
		$l = strtolower($tut['keywords']);
		foreach ($words as $w)
			if (strpos($l, $w)!==false)
				$match++;
		if ($match) $results[$match][] = $tut;
	}
}
foreach ($results as $count=>$tutorials) {
	foreach ($tutorials as $tut)
		$html .= '<a href="javascript:void(0);" onclick="Helper.start_tutorial(\''.Epesi::escapeJS(trim($tut['steps'], '#')).'\')">'.$tut['label'].'</a>';
}
print('$("Base_Help__help_links").innerHTML = "'.Epesi::escapeJS($html).'";');
