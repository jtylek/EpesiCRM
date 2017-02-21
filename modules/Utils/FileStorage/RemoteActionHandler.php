<?php

require_once __DIR__ . '/ActionHandler.php';

class Utils_FileStorage_RemoteActionHandler
    extends Utils_FileStorage_ActionHandler
{
    protected $forUsersOnly = false; // override to allow external downloads

    protected function hasAccess($action, $request)
    {
        return false;
    }

}
