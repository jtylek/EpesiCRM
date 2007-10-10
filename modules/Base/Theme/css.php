<?php
header('Content-type: text/css');
$file = '__cache.css';
if (strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')!==false && file_exists($file.'.gz')) {
	header('Content-Encoding: gzip');
	print(file_get_contents($file.'.gz'));
} elseif(file_exists($file))
	print(file_get_contents($file));
?>
