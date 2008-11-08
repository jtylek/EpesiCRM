<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @license SPL
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

$ret = @include_once('map.php');
if($ret===false)
    define('DATA_DIR','data');
else {
    global $virtual_hosts;
	if(!isset($virtual_hosts) || !is_array($virtual_hosts)) {
	    define('DATA_DIR','data');
		return;
	}
		
    $protocol = (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS'])!== "off") ? 'https://' : 'http://';
	$local_dir = dirname(dirname(str_replace('\\','/',__FILE__)));
	$url_dir = substr($local_dir,strlen(str_replace('\\','/',$_SERVER['SCRIPT_FILENAME']))-strlen(str_replace('\\','/',$_SERVER['SCRIPT_NAME'])));
	$dir = trim($url_dir,'/');
    $req = $protocol.$_SERVER['HTTP_HOST'].'/'.$dir.($dir?'/':'');
    foreach($virtual_hosts as $h=>$dir) {
		if(!is_string($h)) die('Invalid map.php file: not string host address');
		if($h==='') die('Invalid map.php file: empty host address');
		if(ereg($h,$req)) {
		    if($dir===false) die('Forbidden');
	    	define('DATA_DIR',$dir);
		    return;
		}
    }
    die('Invalid address');
}
?>
