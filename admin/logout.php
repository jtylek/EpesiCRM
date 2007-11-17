<?php
require_once('include.php');
if(Acl::is_user()) {
	require_once('auth.php');
	Acl::set_user();
}
header('Location: index.php');
?>
