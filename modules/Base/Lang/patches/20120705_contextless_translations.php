<?php

$path = 'data/Base_Lang/base';
$path2 = 'data/Base_Lang/custom';
$ret = scandir($path);
global $translations;
global $custom_translations;

foreach($ret as $r) {
	if ($r=='..' || $r=='.' || $r=='index.php' || strlen($r)>10) continue;
	$translations = array();
	require_once($path.'/'.$r);
	$packed = array();
	$skip = false;
	foreach ($translations as $m=>$v) {
		if (!is_array($v)) {
			$skip = true;
			break;
		}
		foreach ($v as $o=>$t) {
			if ($t=='') continue;
			if (!isset($packed[$o])) $packed[$o] = array();
			$packed[$o][$t] = $t;
		}
	}
	if ($skip) continue;
	$translations = array();
	foreach ($packed as $o=>$t) {
		$translations[$o] = reset($t);
	}
	
	$custom_translations = array();
	if (file_exists($path2.'/'.$r)) @require($path2.'/'.$r);
	$packed = array();
	foreach ($custom_translations as $m=>$v) {
		if (is_array($v)) {
			foreach ($v as $o=>$t) {
				if ($t=='') continue;
				if (!isset($packed[$o])) $packed[$o] = array();
				$packed[$o][$t] = $t;
			}
		} else {
			if ($v=='') continue;
			if (!isset($packed[$m])) $packed[$m] = array();
			$packed[$m][$v] = $v;
		}
	}
	$custom_translations = array();
	foreach ($packed as $o=>$t) {
		$custom_translations[$o] = reset($t);
	}
	$lang = str_replace('.php','',$r);
	$f = @fopen(DATA_DIR.'/Base_Lang/base/'.$lang.'.php', 'w');
	if($f===false)	return false;

	fwrite($f, "<?php\n");
	fwrite($f, "/**\n * Translation file.\n * @package epesi-translations\n * @subpackage $lang\n */\n");
	fwrite($f, 'global $translations;'."\n");
	foreach($translations as $k=>$v)
			fwrite($f, '$translations[\''.addcslashes($k,'\\\'').'\']=\''.addcslashes($v,'\\\'')."';\n");

	fclose($f);
	
	$f = @fopen(DATA_DIR.'/Base_Lang/custom/'.$lang.'.php', 'w');
	if($f===false)	return false;

	fwrite($f, "<?php\n");
	fwrite($f, "/**\n * Translation file.\n * @package epesi-custom-translations\n * @subpackage $lang\n */\n");
	fwrite($f, 'global $custom_translations;'."\n");
	foreach($custom_translations as $k=>$v)
			fwrite($f, '$custom_translations[\''.addcslashes($k,'\\\'').'\']=\''.addcslashes($v,'\\\'')."';\n");

	fclose($f);
}

?>
