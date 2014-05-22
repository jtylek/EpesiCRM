<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

Base_AclCommon::add_permission(_M('Watchdog - subscribe to categories'),array('ACCESS:employee','ACCESS:manager'));

?>
