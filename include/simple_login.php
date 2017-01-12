<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class SimpleLogin {

    static function form() {
        try {
            $anonymous = Variable::get('anonymous_setup');
        } catch (NoSuchVariableException $e) {
            $anonymous = true;
        }

        if (!Base_AclCommon::is_user() && Base_User_LoginCommon::is_banned()) {
            return self::t('You have exceeded the number of allowed login attempts.');
        }

        if ((!Base_AclCommon::is_user()) && !$anonymous) {
            Base_User_LoginCommon::autologin();
        }
        if ((!Base_AclCommon::is_user()) && !$anonymous) {
            $get = count($_GET) ? '?' . http_build_query($_GET) : '';
            $form = new HTML_QuickForm('loginform', 'post', $_SERVER['PHP_SELF'] . $get);
            $form->setRequiredNote('<span style="font-size:80%; color:#ff0000;">*</span><span style="font-size:80%;">'.self::t('denotes required field').'</span>');
            $form->addElement('text', 'username', self::t('Username'));
            $form->addRule('username', 'Field required', 'required');
            $form->addElement('password', 'password', self::t('Password'));
            $form->addRule('password', 'Field required', 'required');
            // register and add a rule to check if user is banned
            $form->registerRule('check_user_banned', 'callback', 'rule_login_banned', 'Base_User_LoginCommon');
            $form->addRule('username', self::t('You have exceeded the number of allowed login attempts.'), 'check_user_banned');
            // register and add a rule to check if user and password exists
            $form->registerRule('check_login', 'callback', 'submit_login', 'Base_User_LoginCommon');
            $form->addRule(array('username', 'password'), self::t('Login or password incorrect'), 'check_login', $form);
            $form->addElement('submit', null, self::t('Login'));
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

    private static function t($str)
    {
        if (function_exists('_V')) {
            return _V($str);
        }
        return $str;
    }
}