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
	$script_filename = str_replace('\\','/',$_SERVER['SCRIPT_FILENAME']);
	if(strcmp($local_dir,substr($script_filename,0,strlen($local_dir)))) 
		trigger_error('Detection of epesi directory failed. You cannot use virtual hosts with this configuration.',E_USER_ERROR);
	$file_url = substr($script_filename,strlen($local_dir));
	$dir_url = substr($_SERVER['SCRIPT_NAME'],0,strlen($_SERVER['SCRIPT_NAME'])-strlen($file_url));
	$dir = trim($dir_url,'/');
    $req = $protocol.$_SERVER['HTTP_HOST'].'/'.$dir.($dir?'/':'');
    foreach($virtual_hosts as $h=>$dir) {
		if(!is_string($h)) die('Invalid map.php file: not string host address');
		if($h==='') die('Invalid map.php file: empty host address');
		if(strlen($h)<2 || $h[0]!=='/' || $h[strlen($h)-1]!=='/')
			$h = '/'.addcslashes($h,'/').'/';
		if(preg_match($h,$req)) {
		    if($dir===false) die('Forbidden');
	    	define('DATA_DIR',$dir);
		    return;
		}
    }
    die('Invalid address');
}
?>
