<?php
define("EPESI_VERSION", '1.2.2'); 

$svnid = '$Rev: 8605 $'; 
$scid = substr($svnid, 6); 
define("EPESI_REVISION", intval(substr($scid, 0, strlen($scid) - 2)));
?>
