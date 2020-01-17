<?php
/**
 * 
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2015, Janusz Tylek
 * @license MIT
 * @version 2.0
 * @package epesi-notify
 * 
 */

define('CID', false);
define('READ_ONLY_SESSION', true);
require_once('../../../include.php');
ModuleManager::load_modules();

if(!Acl::is_user()) exit();

$token = Base_NotifyCommon::get_session_token(true);
if(!$token) exit();
DB::Execute('UPDATE base_notify SET telegram=1 WHERE token=%s',array($token));

$domain_name = Base_UserCommon::get_my_user_login();
if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST']) {
    $domain_name .= '-'.$_SERVER['HTTP_HOST'];
} else if (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME']) {
    $domain_name .= '-'.$_SERVER['SERVER_NAME'];
}

$domain_name = preg_replace('/[^a-z0-9\-\_]/i','-',$domain_name);

header('Location: https://telegram.me/EpesiBot?'.http_build_query(array('start'=>md5(Base_AclCommon::get_user().'#'.Base_UserCommon::get_my_user_login().'#'.$token).'-'.substr($domain_name,0,31))));
