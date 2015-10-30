<?php
/**
 * 
 * @author Pawel Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2015, Telaxus LLC
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

$token = DB::GetOne('SELECT token FROM base_notify WHERE token LIKE "UID:%d;%%"',array(Base_AclCommon::get_user()));
if(!$token) {
	$token = 'UID:' . Base_AclCommon::get_user() . ';' . md5(get_epesi_url().'#'.microtime(true).'#'.mt_rand(0,1000000));
	DB::Execute('INSERT INTO base_notify (token, cache) VALUES (%s, %s)', array($token, Base_NotifyCommon::serialize(array())));
}
DB::Execute('UPDATE base_notify SET telegram=1 WHERE token=%s',array($token));

$domain_name = Base_UserCommon::get_my_user_login();
if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST']) {
    $domain_name .= '-'.$_SERVER['HTTP_HOST'];
} else if (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME']) {
    $domain_name .= '-'.$_SERVER['SERVER_NAME'];
}

$domain_name = preg_replace('/[^a-z0-9\-\_]/i','-',$domain_name);

header('Location: https://telegram.me/EpesiBot?'.http_build_query(array('start'=>md5($token).'-'.substr($domain_name,0,31))));
