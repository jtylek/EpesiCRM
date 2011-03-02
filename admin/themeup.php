<?php
require_once('auth.php');
require_once('functions.php');
ini_set('display_errors',true);
set_time_limit(0);
$step=0;

if (isset($_GET['step'])==1){
	$step=$_GET['step'];
	}

pageheader();
print('<CENTER><div class="header">Theme Updater Utility</div></CENTER>');

switch ($step)
{
	case 1:
		// Rebuild common cache and run themeup
		starttable();
		refresh();
		print ('<BR><B>Common Cache and Theme templates were successfully updated.</B>');
		closetable();
		pagefooter();
		break;

	default:
		// Display info
		starttable();
		print '<CENTER><B>This utility will rebuild Common Cache and refresh Theme files.</B><BR><BR><BR>';
		print 'After clicking Next button please wait...<BR>';
		print 'Rebuilding theme files may take a while.</CENTER>';
		display_link('1');
		closetable();
		pagefooter();
		break;
}


// ************** Functions declarations *************
function refresh(){
	ModuleManager::create_common_cache();
	themeup();
	Base_ThemeCommon::create_cache();
}

// ************************************************
function display_link($step)
{
		print ('<tr><td>'); // <div class="content">
		print ('<div class="div-button" align="center"><a class="button" href="');
		print $_SERVER['PHP_SELF'].'?step=';
		print $step;
		print ('">Next</a>');
}

function install_default_theme_common_files($dir,$f) {
	if(class_exists('ZipArchive')) {
		$zip = new ZipArchive;
		if ($zip->open($dir.$f.'.zip') == 1)
			$zip->extractTo(DATA_DIR.'/Base_Theme/templates/default/');
		return;
	}
	mkdir(DATA_DIR.'/Base_Theme/templates/default/'.$f);
	$content = scandir($dir.$f);
	foreach ($content as $name){
		if ($name == '.' || $name == '..') continue;
		$path = $dir.$f.'/'.$name;
		if (is_dir($path))
			install_default_theme_common_files($dir,$f.'/'.$name);
		else
			copy($path,DATA_DIR.'/Base_Theme/templates/default/'.$f.'/'.$name);
	}
}

function themeup(){
	$data_dir = DATA_DIR.'/Base_Theme/templates/default/';
	$content = scandir($data_dir);
	foreach ($content as $name){
		if ($name == '.' || $name == '..') continue;
		recursive_rmdir($data_dir.$name);
	}

	$ret = DB::Execute('SELECT * FROM modules');
	while($row = $ret->FetchRow()) {
		$directory = 'modules/'.str_replace('_','/',$row[0]).'/theme_'.$row['version'];
		if (!is_dir($directory)) $directory = 'modules/'.str_replace('_','/',$row[0]).'/theme';
		$mod_name = $row[0];
		$data_dir = DATA_DIR.'/Base_Theme/templates/default';
		print('<span style="color: #339933;">Checking theme:&nbsp;&nbsp;&nbsp;'.$directory.'</span><br>');
		if (!is_dir($directory)) continue;
		$content = scandir($directory);
		print('<span style="color: #336699;">Installing theme:&nbsp;'.$directory.'</span><br>');

		$mod_name = str_replace('_','/',$mod_name);
		$mod_path = explode('/',$mod_name);
		$sum = '';
		foreach ($mod_path as $p) {
			$sum .= '/'.$p;
			@mkdir($data_dir.$sum);
		}
		foreach ($content as $name){
			if($name == '.' || $name == '..' || preg_match('/^[\.~]/',$name)) continue;
			recursive_copy($directory.'/'.$name,$data_dir.'/'.$mod_name.'/'.$name);
		}
	}

	install_default_theme_common_files('modules/Base/Theme/','images');
}

?>
