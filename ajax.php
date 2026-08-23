<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

if (!isset($_GET['key']) || !isset($_GET['cid']) || !is_numeric($_GET['cid']))
    die('Error: Invalid request');

define('CID', $_GET['cid']);
define('READ_ONLY_SESSION', true);
require_once('include.php');
ModuleManager::load_modules();

if (!isset($_SESSION['ajax_callbacks'])) {
    print('Session expired, please reload the page');
    return;
}
if (!isset($_SESSION['ajax_callbacks'][$_GET['key']])) {
    die('Invalid callback key');
}
$params = $_SESSION['ajax_callbacks'][$_GET['key']];
$callback = $params['callback'];
$args = $params['args'];

if (!is_callable($callback))
    throw new Exception('Callback ' . print_r($callback, true) . ' is not callable');

$request = Request::createFromGlobals();
/** @var Response $response */
$response = call_user_func_array($callback, array($request, $args));

if(!$response instanceof Response)
    throw new Exception('Ajax callback must return instance of HttpFoundation\Response');

$response->send();