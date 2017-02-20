<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

require_once __DIR__ . '/../../../vendor/autoload.php';

$handleRequest = function(Request $request) {
    $filestorageId = $request->get('id');
    if (!$filestorageId) {
        return new Response('Invalid usage', 400);
    }
    define('CID', false);
    define('READ_ONLY_SESSION', true);

    require_once('../../../include.php');
    ModuleManager::load_modules();

    $adminAccess = Base_AdminCommon::get_access('Utils_FileStorage');
    if ((Base_AclCommon::i_am_admin() && $adminAccess)
        || (defined('FILE_ACCESS') && FILE_ACCESS == $filestorageId)) {
        $meta = Utils_FileStorageCommon::meta($filestorageId);
        $mime = Utils_FileStorageCommon::get_mime_type($meta['file'], $meta['filename']);
        $disposition = $request->get('view') ? 'inline' : 'attachment';

        $buffer = Utils_FileStorageCommon::read_content($filestorageId);
        $response = new Response();
        $response->setContent($buffer);
        $response->headers->set('Content-Type', $mime);
        $response->headers->set('Content-Length', strlen($buffer));
        $response->headers->set('Content-Disposition', "$disposition; filename=\"$meta[filename]\"");
        return $response;
    }
    return new Response('Access forbidden', 403);
};

/** @var Response $response */
$response = $handleRequest(Request::createFromGlobals());
$response->send();
