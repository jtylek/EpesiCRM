<?php
die();
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license Commercial
 * @version 1.0
 * @package epesi-premium
 * @subpackage timesheet
 */

set_time_limit(0);

define('CID',false); 
require_once('../../../include.php');
ModuleManager::load_modules();

global $strings;
$strings = array();

function scan_file($file) {
	if (substr($file, -4, 4)!=='.php') return;
	$c = file_get_contents($file);
	$i = 0;
	$sc = strlen($c);
	$string = '';
	$parenthesis = 0;
	$inquote = false;
	$inquote_d = false;
	global $strings;
	while ($i<$sc) {
		$fnc = substr($c, $i, 3);
		if ($fnc=='_'.'_(' || $fnc=='_'.'M(') {
			$i += 3;
			if ($c[$i]==')') continue;
			do {
				if ($c[$i]=="'" && $c[$i-1]!='\\' && !$inquote_d) $inquote = !$inquote;
				if ($c[$i]=='"' && $c[$i-1]!='\\' && !$inquote) $inquote_d = !$inquote_d;
				if ($c[$i]=="(" && !$inquote && !$inquote_d) $parenthesis++;
				if ($c[$i]==")" && !$inquote && !$inquote_d) $parenthesis--;
				$string .= $c[$i];
				$i++;
				if (!isset($c[$i])) die($file.'<hr>Fatal: '.$string);
			} while(($c[$i]!=')' && $c[$i]!=',') || $inquote || $inquote_d || $parenthesis != 0);
			$string = trim($string);
			$strings[$string][] = $file;
			$string = '';
		}
		$i++;
	}
}

function recursive_scan($path) {
	if (!is_dir($path)) {
		scan_file($path);
		return;
	}
	$path = rtrim($path, '/');
	$content = scandir($path);
	foreach ($content as $name) {
		if ($name == '.' || $name == '..')
			continue;
		$name = $path . '/' . $name;
		if (is_dir($name) && is_link($name)==false) {
			recursive_scan($name);
		} else
			scan_file($name);
	}
}

recursive_scan('modules');

$tabs = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
print('<b>Problems:</b><br>');
$i = 0;
$o_file = '';
$clean = array();
foreach ($strings as $s=>$files) {
	$first = $s[0];
	$last = substr($s, -1, 1);
	if ($first!='"' && $first!="'") {
		$f = reset($files);
		if (count($files)==2 && $f=='modules/Base/Lang/LangCommon_0.php' && $s = '$string') // method definition
			continue;
		print($tabs.'Files:'.'<br>');
		foreach ($files as $f)
			print($tabs.$tabs.$f.'<br>');
		print($tabs.'String:'.'<br>');
		print($tabs.$tabs.$s.'<br>');
		print($tabs.'Message:'.'<br>');
		print($tabs.$tabs.'Not starting with a quote<hr>');
		continue;
	}
	if ($last!='"' && $last!="'") {
		print($tabs.'Files:'.'<br>');
		foreach ($files as $f)
			print($tabs.$tabs.$f.'<br>');
		print($tabs.'String:'.'<br>');
		print($tabs.$tabs.$s.'<br>');
		print($tabs.'Message:'.'<br>');
		print($tabs.$tabs.'Not ending with a quote<hr>');
		continue;
	}
	if ($last!=$first) {
		print($tabs.'Files:'.'<br>');
		foreach ($files as $f)
			print($tabs.$tabs.$f.'<br>');
		print($tabs.'String:'.'<br>');
		print($tabs.$tabs.$s.'<br>');
		print($tabs.'Message:'.'<br>');
		print($tabs.$tabs.'Starting and ending quote don\'t match<hr>');
		continue;
	}
	if ($last===$first && preg_match('/[^\\\\]'.$first.'/i', substr($s, 1, -1))>=1) {
		print($tabs.'Files:'.'<br>');
		foreach ($files as $f)
			print($tabs.$tabs.$f.'<br>');
		print($tabs.'String:'.'<br>');
		print($tabs.$tabs.$s.'<br>');
		print($tabs.'Message:'.'<br>');
		print($tabs.$tabs.'Quote found in the middle of sentence<hr>');
		continue;
	}
	$s = substr($s, 1, -1);
	if ($first=='"') $s = addcslashes($s,'\\\'');
	$clean[$s] = $files;
}

$exceptions = array('Assigned to','No.','Email','Projects_Report_%s','AMOUNT DUE','Earnings_Report_%s','Shipment - type','Timesheet_Report_%s','Shipment - ETA','Sales_Report_%s','Companies_Report_%s');

$similar = array();
foreach ($strings as $s=>$files) {
	$s = substr($s, 1, -1);
	if (in_array($s, $exceptions)) continue;
	$ss = strtolower(preg_replace('/[^a-zA-Z0-9%#]/', '', $s));
	$similar[$ss][$s] = (isset($similar[$ss][$s])?$similar[$ss][$s]:array()) + $files;
}

print('<b>Similar:</b><br>');
foreach ($similar as $sm=>$v) {
	if (count($v)>1) {
		print($tabs.'Simplified:'.'<br>');
		print($tabs.$tabs.$sm.'<br>');
		foreach ($v as $s=>$files) {
			print($tabs.$tabs.'String:'.'<br>');
			print($tabs.$tabs.$tabs.$s.'<br>');
			print($tabs.$tabs.'Files:'.'<br>');
			foreach ($files as $f)
				print($tabs.$tabs.$tabs.$f.'<br>');
		}
	}
}

if (!isset($_GET['go'])) {
	die('<a href="?go=1">GO</a>');
}

function is_premium($file) {
	$file = str_replace('modules/', '', $file);
	if (substr($file, 0, 8) == 'Premium/') return true;
	if (substr($file, 0, 7) == 'Custom/') return true;
	if (substr($file, 0, 8) == 'Develop/') return true;
	if (substr($file, 0, 6) == 'Tests/') return true;
	return false;
}

$modules = array();
foreach ($clean as $s=>$files) {
	$is_premium = true;
	foreach ($files as $f) {
		$is_premium &= is_premium($f);
	}
	if(!$is_premium) {
		$modules['Base/Box'][$s] = true;
		continue;
	}
	foreach ($files as $f) {
		$org = $f;
		$f = str_replace('modules/', '', $f);
		do {
			$old = $f;
			$f = preg_replace('/\/[^\/]*$/', '', $f);
		} while($old!=$f && $f && !DB::GetOne('SELECT name FROM available_modules WHERE name=%s', array(str_replace('/','_',$f))));
		if (!$f) print('No module found for: '.$org.'<br>');
		else $modules[$f][$s] = true;
	}
}
$dict_dir = 'modules/Develop/Translations/dictionaries';
$dict = scandir($dict_dir);
foreach ($dict as $name) {
	if ($name == '.' || $name == '..') continue;
	global $translations;
	$translations = array();
	require_once($dict_dir.'/'.$name);
	$lang = basename($name, '.php');
	print('<b>Language:</b> '.$lang.'<br>');
	$trans = array();
	$sums = array();
	foreach ($modules as $m=>$ts) {
		$section = substr($m, 0, strpos($m, '/'));
		if (!isset($trans[$section])) $trans[$section] = 0;
		if (!isset($sums[$section])) $sums[$section] = 0;
	
		$output = array();
		foreach ($ts as $s=>$v) {
			if (isset($translations[$s])) $trans[$section]++;
			$sums[$section]++;
			$output[$s] = isset($translations[$s])?$translations[$s]:'';
		}
		@mkdir('modules/'.$m.'/lang/');
		$f = @fopen('modules/'.$m.'/lang/'.$lang.'.php', 'w');
		if(!$f)	return false;

		fwrite($f, "<?php\n");
		fwrite($f, "/**\n * Translation file.\n * @package epesi-translations\n * @subpackage $lang\n */\n");
		fwrite($f, 'global $translations;'."\n");
		foreach($output as $k=>$t)
				fwrite($f, '$translations[\''.addcslashes($k,'\\\'').'\']=\''.addcslashes($t,'\\\'')."';\n");

		fclose($f);
	}
	foreach ($sums as $section=>$q)
		print($tabs.$section.': '.$trans[$section].' / '.$sums[$section].' ('.number_format(100*$trans[$section]/$sums[$section],1).'%)<br>');
}

?>
