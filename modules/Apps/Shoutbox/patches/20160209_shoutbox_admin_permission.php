<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

Base_AclCommon::add_permission(_M('Shoutbox Admin'), array('SUPERADMIN'));
