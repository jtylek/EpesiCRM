<?php

require_once __DIR__ . '/../FileStorage/ActionHandler.php';

class Utils_RecordBrowser_FileActionHandler
    extends Utils_FileStorage_ActionHandler
{
    protected function hasAccess($action, $request)
    {
        return false;
    }

}

$handler = new Utils_RecordBrowser_FileActionHandler();
$handler->handle()->send();