<?php
require_once('auth.php');
print('<span style="font-family: courier; font-size: 11px;">');

$data_dir = 'data/Base_Theme/templates/default/';
print('Cleaning up directory...<br><br>');
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
	$data_dir = 'data/Base_Theme/templates/default/';
	print('<span style="color: #339933;">Checking theme:&nbsp;&nbsp;&nbsp;'.$directory.'</span><br>');
	if (!is_dir($directory)) continue;
	$content = scandir($directory);
	print('<span style="color: #336699;">Installing theme:&nbsp;'.$directory.'</span><br>');
	foreach ($content as $name){
		if($name == '.' || $name == '..' || ereg('^[\.~]',$name)) continue;
		recursive_copy($directory.'/'.$name,$data_dir.$mod_name.'__'.$name);
	}
}

function install_default_theme_common_files($dir,$f) {
	if(class_exists('ZipArchive')) {
		$zip = new ZipArchive;
		if ($zip->open($dir.$f.'.zip') == 1)
			$zip->extractTo('data/Base_Theme/templates/default/');
		return;
	}
	mkdir('data/Base_Theme/templates/default/'.$f);
	$content = scandir($dir.$f);
	foreach ($content as $name){
		if ($name == '.' || $name == '..') continue;
		$path = $dir.$f.'/'.$name;
		if (is_dir($path))
			install_default_theme_common_files($dir,$f.'/'.$name);
		else
			copy($path,'data/Base_Theme/templates/default/'.$f.'/'.$name);
	}
}
install_default_theme_common_files('modules/Base/Theme/','images');

print('</span>');
print('<hr><a href="index.php">back</a>');
?>