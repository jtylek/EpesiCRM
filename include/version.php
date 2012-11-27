<?php
define("EPESI_VERSION", '1.4.1'); 

$svnid = '$Rev: 10078 $'; 
$scid = substr($svnid, 6); 
define("EPESI_REVISION", intval(substr($scid, 0, -2)));
?>
