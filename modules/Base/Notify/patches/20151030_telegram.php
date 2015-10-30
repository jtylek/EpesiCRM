<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

PatchUtil::db_add_column('base_notify','telegram','I1 DEFAULT 0');
