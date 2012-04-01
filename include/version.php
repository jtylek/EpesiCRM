<?php
define("EPESI_VERSION", '1.3'); 

$svnid = '$Rev: 9174 $'; 
$scid = substr($svnid, 6); 
define("EPESI_REVISION", intval(substr($scid, 0, strlen($scid) - 2)));
?>
