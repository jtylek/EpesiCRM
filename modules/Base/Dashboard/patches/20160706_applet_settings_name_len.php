<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

PatchUtil::db_alter_column('base_dashboard_settings', 'name', 'C(64) NOTNULL');
PatchUtil::db_alter_column('base_dashboard_default_settings', 'name', 'C(64) NOTNULL');
