<?php
define("EPESI_VERSION", '1.5.3');

$svnid = '$Rev: 10944 $';
$scid = substr($svnid, 6); 
define("EPESI_REVISION", intval(substr($scid, 0, -2)));
?>
