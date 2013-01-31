<?php

require_once('modules/Libs/QuickForm/requires.php');

class AdminAuthorization {

    static function form() {
        try {
            $anonymous = Variable::get('anonymous_setup');
        } catch (NoSuchVariableException $e) {
            $anonymous = true;
        }

        if ((!Base_AclCommon::is_user()) && !$anonymous) {
            Base_User_LoginCommon::autologin();
        }
        if ((!Base_AclCommon::is_user()) && !$anonymous) {
            $get = count($_GET) ? '?' . http_build_query($_GET) : '';
            $form = new HTML_QuickForm('loginform', 'post', $_SERVER['PHP_SELF'] . $get);
            $form->addElement('html', '<div class="title">Admin Tools<div><hr/>');
            $form->addElement('text', 'user', 'Login');
            $form->addRule('user', 'Field required', 'required');
            $form->addElement('password', 'pass', 'Password');
            $form->addRule('pass', 'Field required', 'required');
            $form->registerRule('check_login', 'callback', 'submit_login', 'AdminAuthorization');
            $form->addRule('user', 'Login or password incorrect', 'check_login', $form);
            $form->addElement('submit', null, 'Ok');
            if ($form->validate()) {
                $user = $form->exportValue('user');
                Base_AclCommon::set_user(Base_UserCommon::get_user_id($user), true);
                // redirect below is used to better browser refresh behavior.
                header('Location: ' . $_SERVER['REQUEST_URI']);
            } else {
                return "<center>" . $form->toHtml() . "</center>";
            }
        }
    }

    static function submit_login($username, $form) {
        return ModuleManager::is_installed('Base_User_Login') >= 0 && Base_User_LoginCommon::check_login($username, $form->exportValue('pass'));
    }

}

?>