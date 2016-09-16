<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

do {
    Patch::require_time(10);
    Utils_RecordBrowserCommon::indexer(300, $total);
} while($total);
