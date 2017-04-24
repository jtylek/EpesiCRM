<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Utils_FileStorage_ActionHandler
{
    /**
     * You can override this variable to define allowed actions
     *
     * @var array Possible actions to execute
     */
    protected $allowedActions = ['download', 'preview', 'remote'];

    const actions = ['download'=>0, 'preview'=>1, 'remote'=>2];

    /**
     * You can override this variable to allow access for not logged in users
     *
     * @var bool
     */
    protected $forUsersOnly = true;

    public function handle()
    {
        $this->loadEpesi();
        $request = Request::createFromGlobals();
        $response = $this->handleAction($request);
        if (!$response) {
            $response = new Response('Bad request', 400);
        }
        return $response;
    }

    protected function getHandlingScript()
    {
        return get_epesi_url() . '/modules/Utils/FileStorage/file.php';
    }

    protected function getRemoteScript()
    {
        return get_epesi_url() . '/modules/Utils/FileStorage/remote.php';
    }

    public function getActionUrls($filestorageId, $params = [])
    {
        $urls = [];
        $file = $this->getHandlingScript();
        $params['id'] = $filestorageId;
        foreach ($this->allowedActions as $action) {
            $paramsWithAction = ['action' => $action] + $params;
            $urls[$action] = "$file?" . http_build_query($paramsWithAction);
        }
        return $urls;
    }

    protected function loadEpesi()
    {
        define('CID', false);
        define('READ_ONLY_SESSION', true);

        require_once('../../../include.php');
        ModuleManager::load_modules();

    }

    protected function hasAccess($action, $request)
    {
        $adminAccess = Base_AdminCommon::get_access('Utils_FileStorage');
        return Base_AclCommon::i_am_admin() && $adminAccess;
    }

    protected function hasUserAccess()
    {
        if ($this->forUsersOnly) {
            return Base_AclCommon::is_user();
        }
        return true;
    }

    protected function handleAction(Request $request)
    {
        $action = $request->get('action', 'download');
        if (!$this->hasUserAccess()) {
            return new Response('Only for logged in users.', 403);
        }
        if (!in_array($action, $this->allowedActions)
            || !$this->hasAccess($action, $request)
        ) {
            return false;
        }
        $method = $request->server->get('REQUEST_METHOD');
        switch ($action) {
            case 'download':
                return $this->getFile($request, 'attachment');
            case 'preview':
                return $this->getFile($request, 'inline');
            case 'remote':
                switch($method) {
                    case Request::METHOD_POST:
                        return $this->createRemote($request);
                    case Request::METHOD_GET:
                        return $this->getFile($request, 'attachment');
                }
        }
        return false;
    }

    protected function getFile(Request $request, $disposition)
    {
        $filestorageId = $request->get('id');
        $type = $request->get('action');
        try {
            $meta = Utils_FileStorageCommon::meta($filestorageId);
            $buffer = Utils_FileStorageCommon::read_content($filestorageId);
        } catch (Utils_FileStorage_Exception $ex) {
            if (Base_AclCommon::i_am_admin()) {
                return new Response($ex->getMessage(), 400);
            }
            return false;
        }
        
        $type = self::actions[$type];

        $remote_address = get_client_ip_address();
        $remote_host = gethostbyaddr($remote_address);
        DB::Execute('INSERT INTO utils_filestorage_access(file_id,date_accessed,accessed_by,type,ip_address,host_name) '.
            'VALUES (%d,%T,%d,%d,%s,%s)',array($filestorageId,time(),Acl::get_user()?Acl::get_user():0,$type,$remote_address,$remote_host));

        $mime = Utils_FileStorageCommon::get_mime_type($meta['file'], $meta['filename']);

        $response = new Response();
        $response->setContent($buffer);
        $response->headers->set('Content-Type', $mime);
        $response->headers->set('Content-Length', strlen($buffer));
        $response->headers->set('Content-Disposition', "$disposition; filename=\"$meta[filename]\"");
        return $response;
    }

    protected function createRemote(Request $request)
    {
        $params = $request->query->all();
        $expires_on = time()+3600*24*7;
        $token = md5($params['tab'].$params['id'].$params['field'].$expires_on.mt_rand());
        DB::Execute('INSERT INTO utils_filestorage_remote(file_id,token,created_on,created_by,expires_on) VALUES (%d,%s,%T,%d,%T)',
            [$params['id'],$token,time(),Acl::get_user(),$expires_on]);
        $id = DB::Insert_ID('utils_filestorage_remote','id');
        return new Response(
            $this->getRemoteScript().'?'.
            http_build_query(array('id'=>$id,'token'=>$token)),
            Response::HTTP_OK,
            ['content-type' => 'text/plain']
        );
    }
}
