<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

Base_PrintCommon::register_printer(new Utils_RecordBrowser_RecordPrinter());
