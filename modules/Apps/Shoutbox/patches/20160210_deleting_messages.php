<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

PatchUtil::db_add_column('apps_shoutbox_messages', 'deleted', 'I1');
