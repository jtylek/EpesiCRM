<?php

require_once __DIR__ . '/FileActionHandler.php';

$handler = new Utils_Attachment_FileActionHandler();
$handler->handle()->send();