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

        require_once('modules/Libs/QuickForm/requires.php');

        if ((!Base_AclCommon::is_user()) && !$anonymous) {
            return self::build_form();
        }
    }

    // Same login form/validation as form(), but ignores the site's
    // "anonymous_setup" convenience mode - form() intentionally treats that
    // mode as "everyone is already authenticated" (no session required at
    // all), which is right for browsing the app but wrong for a handful of
    // destructive, standalone entry points (admin/, update.php, check.php)
    // that must always require a real logged-in session regardless of how
    // the rest of the site is configured.
    static function force_login_form() {
        if (!Base_AclCommon::is_user() && Base_User_LoginCommon::is_banned()) {
            return self::t('You have exceeded the number of allowed login attempts.');
        }

        require_once('modules/Libs/QuickForm/requires.php');

        if (!Base_AclCommon::is_user()) {
            return self::build_form();
        }
    }

    private static function build_form() {
        Base_User_LoginCommon::autologin();
        if (Base_AclCommon::is_user()) return;

        $get = count($_GET) ? '?' . http_build_query($_GET) : '';
        $form = new HTML_QuickForm('loginform', 'post', $_SERVER['PHP_SELF'] . $get);
        $form->setRequiredNote('<span style="font-size:80%; color:#ff0000;">*</span><span style="font-size:80%;">'.self::t('denotes required field').'</span>');
        // 'class'=>'form-control' matches Base_User_Login's own adminlte
        // form (Login_0.php) - the array renderer below just returns each
        // element's toHtml() as-is, so the input-group styling in
        // login_form.tpl depends on the element itself already carrying
        // this class, not something the template can add after the fact.
        $form->addElement('text', 'username', self::t('Username'), array('class' => 'form-control', 'placeholder' => self::t('Username')));
        $form->addRule('username', 'Field required', 'required');
        $form->addElement('password', 'password', self::t('Password'), array('class' => 'form-control', 'placeholder' => self::t('Password')));
        $form->addRule('password', 'Field required', 'required');
        // register and add a rule to check if user is banned
        $form->registerRule('check_user_banned', 'callback', 'rule_login_banned', 'Base_User_LoginCommon');
        $form->addRule('username', self::t('You have exceeded the number of allowed login attempts.'), 'check_user_banned');
        // register and add a rule to check if user and password exists
        $form->registerRule('check_login', 'callback', 'submit_login', 'Base_User_LoginCommon');
        $form->addRule(array('username', 'password'), self::t('Login or password incorrect'), 'check_login', $form);
        // Named (not the original anonymous submit element) so the array
        // renderer below can key it as $form_data.submit_button, same
        // convention Login_0.php's own submit button uses.
        $form->addElement('submit', 'submit_button', self::t('Login'), array('class' => 'submit btn btn-primary'));
        if ($form->validate()) {
            $user = $form->exportValue('username');
            Base_AclCommon::set_user(Base_UserCommon::get_user_id($user), true);
            // redirect below is used to better browser refresh behavior.
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit();
        }

        // Same array-form renderer Base_User_Login (and every other
        // QuickForm-driven Smarty screen) uses via QuickForm_0::assign_theme()
        // - $form here is a plain HTML_QuickForm though (SimpleLogin predates
        // and doesn't go through the epesi module/QuickForm_0 wrapper), so
        // the renderer is invoked directly rather than through that helper.
        require_once('modules/Libs/QuickForm/Renderer/TCMSArraySmarty.php');
        $renderer = new HTML_QuickForm_Renderer_TCMSArraySmarty();
        $form->accept($renderer);

        return self::render('login_form.tpl', array(
            'form_data' => $renderer->toArray(),
            'login_box_msg' => self::t('Admin login only'),
        ));
    }

    // Standalone, deliberately independent of the module/theme rendering
    // pipeline - this class is used by entry points (admin/, update.php,
    // check.php) that must work before the app is fully loaded, the same
    // reasoning admin/AdminSmarty.php documents for its own instance.
    private static function render($template, array $vars) {
        static $smarty;
        if (!$smarty) {
            require_once('modules/Base/Theme/smarty/Smarty.class.php');
            $smarty = new Smarty();
            $smarty->template_dir = 'include/templates';
            $smarty->compile_dir = TEMP_DIR . '/SimpleLogin/compiled/';
            $smarty->compile_id = 'simple_login';
            if (!is_dir($smarty->compile_dir))
                mkdir($smarty->compile_dir, 0777, true);
        }
        foreach ($vars as $key => $value)
            $smarty->assign($key, $value);
        return $smarty->fetch($template);
    }

    private static function t($str)
    {
        if (function_exists('_V')) {
            return _V($str);
        }
        return $str;
    }
}
