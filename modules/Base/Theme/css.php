<?php
header('Content-type: text/css');
require_once('../../../include.php');
if(!isset($_GET['d'])) die();
if($_GET['d'])
	$file = 'data/Base_Theme/templates/default/__cache.css';
else {
	$def_theme = Variable::get('default_theme');
	$file = 'data/Base_Theme/templates/'.$def_theme.'/__cache.css';
}
if (strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')!==false && file_exists($file.'.gz')) {
	header('Content-Encoding: gzip');
	print(file_get_contents($file.'.gz'));
} else
	print(file_get_contents($file));
?>
