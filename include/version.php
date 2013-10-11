<?php
define("EPESI_VERSION", '1.5.4');

$svnid = '$Rev: 11043 $';
$scid = substr($svnid, 6); 
define("EPESI_REVISION", intval(substr($scid, 0, -2)));
?>
