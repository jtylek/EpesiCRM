<?php
require_once('include.php');

if((!Acl::is_user() || !Acl::check('Administration','Main','Users',Acl::get_user())) && !Variable::get('anonymous_setup')) {
	$form = new HTML_QuickForm('loginform','get');
	$form->addElement('text','user','Login');
	$form->addRule('user','Field required','required');
	$form->addElement('password','pass','Password');
	$form->addRule('pass','Field required','required');
	$form->registerRule('check_login', 'callback', 'submit_login');
	$form->addRule('user', 'Login or password incorrect', 'check_login', $form);
	$form->addElement('submit',null,'Ok');
	if($form->validate()) {
		$user = $form->exportValue('user');
		Acl::set_user($user);
	} else {
		$form->display();
		exit();
	}
}

function submit_login($username,$form) {
	return ModuleManager::is_installed('Base_User_Login')>=0 && Base_User_LoginCommon::check_login($username, $form->exportValue('pass')) && Acl::check('Administration','Main','Users',$username);
}
?>
