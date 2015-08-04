<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

Base_AclCommon::add_permission(_M('Dashboard - manage applets'),array('ACCESS:employee'));
