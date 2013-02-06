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
            $form->addElement('text', 'username', 'Login');
            $form->addRule('username', 'Field required', 'required');
            $form->addElement('password', 'password', 'Password');
            $form->addRule('password', 'Field required', 'required');
            // register and add a rule to check if user is banned
            $form->registerRule('check_user_banned', 'callback', 'rule_login_banned', 'Base_User_LoginCommon');
            $form->addRule('username', __('You have exceeded the number of allowed login attempts.'), 'check_user_banned');
            // register and add a rule to check if user and password exists
            $form->registerRule('check_login', 'callback', 'submit_login', 'Base_User_LoginCommon');
            $form->addRule(array('username', 'password'), 'Login or password incorrect', 'check_login', $form);
            $form->addElement('submit', null, 'Ok');
            if ($form->validate()) {
                $user = $form->exportValue('username');
                Base_AclCommon::set_user(Base_UserCommon::get_user_id($user), true);
                // redirect below is used to better browser refresh behavior.
                header('Location: ' . $_SERVER['REQUEST_URI']);
            } else {
                return "<center>" . $form->toHtml() . "</center>";
            }
        }
    }
}

?>