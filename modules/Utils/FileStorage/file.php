<?php


require_once __DIR__ . '/ActionHandler.php';
$handler = new Utils_FileStorage_ActionHandler();
$handler->handle()->send();
