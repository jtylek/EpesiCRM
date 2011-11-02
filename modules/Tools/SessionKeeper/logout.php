<?php
header("Content-type: text/javascript");

define('JS_OUTPUT',1);
require_once('../../../include.php');
if(Acl::is_user()) {
    Acl::set_user();
    die('document.location=\'index.php\';');
}
?>
