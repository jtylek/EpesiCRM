<?php

use Symfony\Component\HttpFoundation\Request;

class CRM_RoundCube_RemoteAttachment extends Utils_FileStorage_ActionHandler
{
    private static $instance = null;

    private function __construct()
    {

    }

    public static function getInstance()
    {
        if (self::$instance == null)
        {
            self::$instance = new CRM_RoundCube_RemoteAttachment();
        }

        return self::$instance;
    }

    public function callCreateRemote($backref)
    {
        if (substr($backref, 0, 3) == 'rb:') {
            $backref = substr($backref, 3);
        }
        list($tab,$id,$field) = explode('/',$backref);
        $_GET['tab'] = $tab;
        $_GET['id'] = $id;
        $_GET['field'] = $field;
        $request = Request::createFromGlobals();
        $response = $this->createRemote($request);
        return $response->getStatusCode() == 200
            ? $response->getContent()
            : __('there is something wrong with the file, please contact the admin for help');
    }
}