<?php

require __DIR__ . '/RemoteActionHandler.php';

$handler = new Utils_FileStorage_RemoteActionHandler();
$handler->handle()->send();