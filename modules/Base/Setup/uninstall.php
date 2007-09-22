<?php
/**
 * Setup class
 * 
 * This file contains setup module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com> and Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @license SPL
 * @package epesi-base-extra
 * @subpackage setup
 */

require_once('../../../include.php');
ModuleManager::load_modules();
require_once('modules/Libs/QuickForm/requires.php');

if(!isset($_GET['user']) || !isset($_GET['pass'])) {
	$form = new HTML_QuickForm('loginform','get',$_SERVER['PHP_SELF'].'?'.http_build_query($_GET));
	$form->addElement('text','user','Login');
	$form->addElement('password','pass','Password');
	$form->addElement('submit',null,'Ok');
	$form->display();
	exit();
}
$user = $_GET['user'];
$pass = $_GET['pass'];
if((!DB::GetOne('SELECT count(id) FROM user_login ul INNER JOIN user_password up ON ul.id=up.user_login_id WHERE login=%s AND password=%s',array($user,md5($pass))) ||
	 !Acl::check('Administration','Main','Users',$user)) && !Variable::get('anonymous_setup')) die('Access denied');

/*
 * Ok, you are in.
 */
//create default module form
$form = new HTML_QuickForm('modulesform','post',$_SERVER['PHP_SELF'].'?'.http_build_query($_GET));
$form->addElement('header', null, 'Uninstall module');

$ret = DB::Execute('SELECT * FROM modules ORDER BY name');
while($row = $ret->FetchRow())
	$form->addElement('checkbox',$row['name'],$row['name'].' (ver '.$row['version'].')');

$form->addElement('submit', 'submit_button', 'OK');

//validation or display
if ($form->validate()) {
	//uninstall
	$vals = $form->exportValues();
	$modules_prio_rev = array();
	$ret = DB::Execute('SELECT * FROM modules ORDER BY priority DESC');
	while($row = $ret->FetchRow())
		if(isset($vals[$row['name']]) && $vals[$row['name']]) {
			if (!ModuleManager::uninstall($row['name'])) {
				die('Unable to remove module '.$row['name']);
			}
			if(count(ModuleManager::$modules)==0)
				die('No modules installed');
		}
} else
	$form->display();
?>
