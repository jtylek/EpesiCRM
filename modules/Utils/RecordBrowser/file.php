<?php

require_once __DIR__ . '/FileActionHandler.php';

$handler = new Utils_RecordBrowser_FileActionHandler();
$handler->handle()->send();