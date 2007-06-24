<?php
/*
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @licence SPL
 */
$delimiter = ($_ENV['OS']=='Windows_NT')?';':':';
$dir = dirname(dirname(__FILE__));
ini_set('include_path',$dir.'/libs'.$delimiter.$dir.$delimiter.ini_get('include_path'));

?>
