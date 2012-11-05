<?php
define("EPESI_VERSION", '1.4.0'); 

$svnid = '$Rev: 9962 $'; 
$scid = substr($svnid, 6); 
define("EPESI_REVISION", intval(substr($scid, 0, strlen($scid) - 2)));
?>
