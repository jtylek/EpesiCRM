<?php

require __DIR__ . '/RemoteActionHandler.php';

$_GET['action'] = 'remote';
$handler = new Utils_FileStorage_RemoteActionHandler();
$handler->handle()->send();