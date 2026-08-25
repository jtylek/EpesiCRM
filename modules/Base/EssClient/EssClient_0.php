<?php

/**
 * @author Adam Bukowski <abukowski@telaxus.com>
 * @copyright Janusz Tylek
 * @license MIT
 * @version 20111207
 * @package epesi-Base
 * @subpackage EssClient
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_EssClient extends Module {

    public function body() {
        // When user gets here from Menu/Help we need pop_main
        // When from admin panel we need parent->reset()
        if ($this->is_back()) {
            Base_BoxCommon::pop_main();
        }
        $this->admin();
    }

    public function admin($store=false) {
        if (!Base_AclCommon::i_am_sa())
            return;
        if ($this->is_back()) {
            $this->parent->reset();
            return;
        }
        if(!$store) Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
        if (Base_EssClientCommon::is_no_ssl_allowed())
            Base_ActionBarCommon::add('settings', __('SSL settings'), $this->create_callback_href(array('Base_BoxCommon', 'push_module'), array('Base_EssClient', 'no_ssl_settings')));

        if (Base_EssClientCommon::has_license_key() == false) {
            $this->terms_and_conditions();
            Base_EssClientCommon::server(true);
        }
        try {
            if (Base_EssClientCommon::has_license_key()) {
                $data = Base_EssClientCommon::server()->installation_registered_data();
                if ($data) {
                    $data['license_key'] = Base_EssClientCommon::get_license_key();
                    $data['status'] = Base_EssClientCommon::get_installation_status();
                    ///////// Status ////////
                    $confirmed = stripos($data['status'], 'confirmed') !== false || stripos($data['status'], 'validated') !== false;
                    if ($confirmed) {
                        $status_description = __('registration done');
                        $verbose_description = __('The registration process is complete.');
                    } else {
                        $status_description = __('waiting for e-mail confirmation');
                        $verbose_description = __('You need to verify your e-mail address. An e-mail was sent to the Administrator\'s e-mail address with a link to confirm the e-mail address.');
                    }
                    if (Base_ThemeCommon::is_adminlte_family()) {
                        print('<div class="d-flex justify-content-center py-4">');
                        print('<div class="card ' . ($confirmed ? 'border-success' : 'border-warning') . '" style="max-width:600px;width:100%;">');
                        print('<div class="card-body text-center">');
                        print('<i class="bi ' . ($confirmed ? 'bi-check-circle-fill text-success' : 'bi-hourglass-split text-warning') . '" style="font-size:3rem;"></i>');
                        print('<h3 class="mt-3 mb-3">' . __('Thank you for registering your Epesi installation.') . '</h3>');
                        print('<div class="text-start bg-body-tertiary rounded p-3 mb-3">');
                        print('<div class="mb-2"><span class="fw-bold">' . __('License Key') . ':</span> <code class="user-select-all">' . htmlspecialchars($data['license_key']) . '</code></div>');
                        print('<div><span class="fw-bold">' . __('Status') . ':</span> <span class="badge ' . ($confirmed ? 'text-bg-success' : 'text-bg-warning') . '">' . $status_description . '</span></div>');
                        print('</div>');
                        print('<p class="text-muted mb-0">' . $verbose_description . '</p>');
                        print('</div></div></div>');
                    } else {
                        print('<div class="important_notice">');
                        print('<div style="margin: 5px">' . __('Thank you for registering your Epesi installation.') . '</div>');
                        print('<div class="important_notice_frame"><span style="font-weight:bold;">' . __('License Key') . ': ' .
                                '</span>' . $data['license_key'] . '<br/>');
                        print('<span style="font-weight:bold;">' . __('Status') . ': ' .
                                '</span>' . $status_description . '</div>');
                        print('<div style="margin: 5px">' . $verbose_description . '</div>');
                        print('</div>');
                    }
                    Base_ActionBarCommon::add('edit', __('Edit company details'), $this->create_callback_href($this->register_form(...), array($data)));
                } else {
                    $email = Base_EssClientCommon::get_support_email();
                    $not_recognized_message = __('Your Epesi license key is not recognized by Epesi Store Server. Please contact Epesi support team at %s.', array($email));

                    if (Base_ThemeCommon::is_adminlte_family()) {
                        print('<div class="d-flex justify-content-center py-4">');
                        print('<div class="card border-danger" style="max-width:600px;width:100%;">');
                        print('<div class="card-body text-center">');
                        print('<i class="bi bi-exclamation-octagon-fill text-danger" style="font-size:3rem;"></i>');
                        print('<h3 class="text-danger mt-3 mb-3">' . __('License key not recognized') . '</h3>');
                        print('<p class="mb-0">' . $not_recognized_message . '</p>');
                        print('</div></div></div>');
                    } else {
                        print('<div class="important_notice">' . $not_recognized_message . '</div>');
                    }
                    Base_ActionBarCommon::add('delete', __('Revoke license key'), $this->create_confirm_callback_href(__('Are you sure you want to revoke your Epesi License Key?'), array('Base_EssClientCommon', 'clear_license_key')));
                }
                $url = get_epesi_url() . '/modules/Base/EssClient/tos/tos.php';
                Base_ActionBarCommon::add('search', __('Terms & Conditions'), 'target="_blank" href="' . $url . '"');
                Base_ActionBarCommon::add('settings', __('Edit license key'), $this->create_callback_href($this->license_key_form(...)));
            }
        } catch (Exception $e) {
            print('<div class="important_notice">' . __('There was an error while trying to connect to Epesi Store Server. Please try again later.') . '<br>');
            print(__('If the problem persists, please contact us at %s', array('<a href="http://forum.epesibim.com/" target="_blank">http://forum.epesibim.com/</a>')) . '<br>');
            print('<br>');
            print(__('Error message: ') . '<br>');
            print('<div class="important_notice_frame">' . $e->getMessage());
            print('</div></div>');
            Base_ActionBarCommon::add('retry', __('Retry'), $this->create_href(array()));
            return;
        }
        print Base_EssClientCommon::client_messages_frame();
    }

    private function connection_problem_form()
    {
        $help_url = "http://epesi.org/ESS_Connection_Issues";
        $help_link = "<a href=\"$help_url\" target=\"_blank\">$help_url</a>";
        if (Base_ThemeCommon::is_adminlte_family()) {
            print('<div class="d-flex justify-content-center py-4">');
            print('<div class="card border-danger" style="max-width:600px;width:100%;">');
            print('<div class="card-body text-center">');
            print('<i class="bi bi-wifi-off text-danger" style="font-size:3rem;"></i>');
            print('<h3 class="text-danger mt-3 mb-3">' . __('Connection problem') . '</h3>');
            print('<p class="mb-3">' . __('Cannot estabilish connection to registration server. Please read error information in the bottom right corner.') . '</p>');
            print('<p class="text-muted mb-0"><i class="bi bi-info-circle me-1"></i>' . __('For more information please visit this page: %s', array($help_link)) . '</p>');
            print('</div></div></div>');
            return;
        }
        print('<div class="important_notice">');
        print('<h1 style="color:red; text-align: center">');
        print(__('Connection problem'));
        print('</h1><br>');
        print(__('Cannot estabilish connection to registration server. Please read error information in the bottom right corner.'));
        print('<br><br>');
        print(__('For more information please visit this page: %s', array($help_link)));
        print('</div>');

    }

    private function terms_and_conditions() {
        if ($this->get_module_variable('t_and_c_accepted')) {
            $this->register_form();
            return;
        }

        if (!Base_EssClientCommon::test_connection()) {
            $this->connection_problem_form();
            return;
        }
        $form = $this->init_module('Libs_QuickForm');
        $form->addElement('checkbox', 'agree', __('I agree to Terms and Conditions'), null, array('class' => 'form-check-input'));
        $form->addRule('agree', __('You must accept Terms and Conditions to proceed'), 'required');
        $submit_attrs = Base_ThemeCommon::is_adminlte_family()
            ? array('class' => 'btn btn-primary rounded-pill px-4')
            : array('style' => 'width:200px');
        $form->addElement('submit', 'submit', __('Obtain Epesi License Key'), $submit_attrs);
        if ($form->validate()) {
            $this->set_module_variable('t_and_c_accepted', true);
            location(array());
            return;
        }

        $url = get_epesi_url() . '/modules/Base/EssClient/tos/tos.php';

        if (Base_ThemeCommon::is_adminlte_family()) {
            $theme = $this->pack_module(Base_Theme::module_name());
            $form->assign_theme('form', $theme);
            $theme->assign('title', __('Epesi Registration'));
            $theme->assign('intro', __('Registration of your Epesi installation will allow you to browse and make purchases in %sEpesi Store%s and receive notifications via e-mail about important updates.', array('<strong>', '</strong>')));
            $theme->assign('license_key_intro', __('Once the registration is complete you will receive a %sLicense Key%s.', array('<strong>', '</strong>')) . ' ' . __('This unique License Key will be used to identify your installation and allow you to download and use modules you purchase. Please note that %sEpesi License Key%s can not be copied to any other Epesi installation.', array('<strong>', '</strong>')) . ' ' . __('All purchases and downloads you make using your Epesi License Key can be used for this installation only.'));
            $theme->assign('license_key_move', __('If necessary, you can move your installation to another server and keep your Epesi License Key, but at any given time no two installations can use the same Epesi License Key.') . ' ' . __('Sharing your License Key with unauthorized users is a violation of this agreement and will result in revoking the License Key.'));
            $theme->assign('enter_license_key_label', __('If you already have a License Key for this installation, you can enter it here:'));
            $theme->assign('enter_license_key_href', $this->create_callback_href($this->license_key_form(...)));
            $theme->assign('enter_license_key_text', __('enter License Key'));
            $theme->assign('tos_label', __('Full Terms and Conditions are available here:'));
            $theme->assign('tos_href', $url);
            $theme->assign('tos_text', __('Terms and Conditions'));
            $theme->display('terms_and_conditions');
            return;
        }

        print('<div class="important_notice">');
        print('<center><H1>');
        print(__('Epesi Registration'));
        print('</H1></center><br>');
        print(__('Registration of your Epesi installation will allow you to browse and make purchases in %sEpesi Store%s and receive notifications via e-mail about important updates.', array('<strong>','</strong>')));
		print('<br>');
		print(__('Once the registration is complete you will receive a %sLicense Key%s.', array('<strong>','</strong>')).' ');
        print(__('This unique License Key will be used to identify your installation and allow you to download and use modules you purchase. Please note that %sEpesi License Key%s can not be copied to any other Epesi installation.', array('<strong>','</strong>')).' ');
        print(__('All purchases and downloads you make using your Epesi License Key can be used for this installation only.'));
        print('<br><br>');
        print(__('If necessary, you can move your installation to another server and keep your Epesi License Key, but at any given time no two installations can use the same Epesi License Key.').' ');
        print(__('Sharing your License Key with unauthorized users is a violation of this agreement and will result in revoking the License Key.'));
        print('<br><br>');
        print('<strong>'.__('If you already have a License Key for this installation, you can enter it here:') . ' <a ' . $this->create_callback_href($this->license_key_form(...)) . '>' . __('enter License Key') . '</a></strong>');
        print('<br><br>');
        print(__('Full Terms and Conditions are available here:'));
        print(' <a target="_blank" href="' . $url . '">' . __('Terms and Conditions') . '</a>');
        print('<center>');
        $form->display_as_column();
        print('</center>');
        print('</div>');
        return;
    }

    public function register_form($data = null) {
        if ($this->is_back()) {
            return false;
        }
        Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());

        $f = $this->init_module(Libs_QuickForm::module_name());

        $admin_email_tooltip = Utils_TooltipCommon::icon(__('This email will be used to send registation link and to contact Administator directly.'), false) . ' ';

        $tax_id_tooltip = Utils_TooltipCommon::icon(__('Your company Tax ID for invoices.'), false) . ' ';

        $f->addElement('text', 'company_name', __('Company Name'), array('maxlength' => 128));
        $f->addRule('company_name', __('Field required'), 'required');
        $f->addRule('company_name', __('Max length exceeded'), 'maxlength', 128);

        $f->addElement('text', 'short_name', __('Short Name'), array('maxlength' => 64));
        $f->addRule('short_name', __('Max length exceeded'), 'maxlength', 64);

        $f->addElement('text', 'phone', __('Phone'), array('maxlength' => 64));
        $f->addRule('phone', __('Max length exceeded'), 'maxlength', 64);

        $f->addElement('text', 'fax', __('Fax'), array('maxlength' => 64));
        $f->addRule('fax', __('Max length exceeded'), 'maxlength', 64);

        $f->addElement('text', 'email', __('Company email'), array('maxlength' => 128));
        $f->addRule('email', __('Max length exceeded'), 'maxlength', 128);
        $f->addRule('email', __('Invalid e-mail address'), 'email');

        $f->addElement('text', 'web_address', __('Web address'), array('maxlength' => 64));
        $f->addRule('web_address', __('Max length exceeded'), 'maxlength', 64);

        $f->addElement('text', 'address_1', __('Address 1'), array('maxlength' => 64));
        $f->addRule('address_1', __('Field required'), 'required');
        $f->addRule('address_1', __('Max length exceeded'), 'maxlength', 64);

        $f->addElement('text', 'address_2', __('Address 2'), array('maxlength' => 64));
        $f->addRule('address_2', __('Max length exceeded'), 'maxlength', 64);

        $f->addElement('text', 'city', __('City'), array('maxlength' => 64));
        $f->addRule('city', __('Field required'), 'required');
        $f->addRule('city', __('Max length exceeded'), 'maxlength', 64);

        $f->addElement('commondata', 'country', __('Country'), 'Countries');
        $f->addRule('country', __('Field required'), 'required');
        $f->addElement('commondata', 'zone', __('Zone'), array('Countries', 'country'), array('empty_option' => true));

        $f->addElement('text', 'postal_code', __('Postal Code'), array('maxlength' => 64));
        $f->addRule('postal_code', __('Field required'), 'required');
        $f->addRule('postal_code', __('Max length exceeded'), 'maxlength', 64);

        $f->addElement('text', 'tax_id', $tax_id_tooltip . __('Tax ID'), array('maxlength' => 64));
        $f->addRule('tax_id', __('Max length exceeded'), 'maxlength', 64);

        $f->addElement('text', 'admin_first_name', __('Administrator\'s first name'), array('maxlength' => 64));
        $f->addRule('admin_first_name', __('Field required'), 'required');
        $f->addRule('admin_first_name', __('Max length exceeded'), 'maxlength', 64);

        $f->addElement('text', 'admin_last_name', __('Administrator\'s last name'), array('maxlength' => 64));
        $f->addRule('admin_last_name', __('Field required'), 'required');
        $f->addRule('admin_last_name', __('Max length exceeded'), 'maxlength', 64);

        $f->addElement('text', 'admin_email', $admin_email_tooltip . __('Administrator\'s email'), array('maxlength' => 128));
        $f->addRule('admin_email', __('Field required'), 'required');
        $f->addRule('admin_email', __('Max length exceeded'), 'maxlength', 128);
        $f->addRule('admin_email', __('Invalid e-mail address'), 'email');

        if ($f->validate()) {
            $ret = $f->exportValues();

            $ret = Base_EssClientCommon::server()->register_installation_request($ret);

            if ($ret) {
                if (is_string($ret))
                    Base_EssClientCommon::set_license_key($ret);

                location(array());
                return false;
            }
        }
        // set defaults
        $intro = __('Enter Company and Administrator details. This data will be sent to Epesi Store Server to provide us with contact information. The data sent to Epesi Store Server is limited only to the data you enter using this form and what modules are being purchased and downloaded.');
        $auto_filled_notice = '';
        if ($data) {
            $f->setDefaults($data);
        } else {
            if (ModuleManager::is_installed('CRM_Contacts') > -1) {
                $auto_filled_notice = __('Data below was auto-filled based on Main Company and first Super administrator. Make sure that the data is correct and change it if necessary.');
				$defaults = Base_EssClientCommon::get_possible_admin();
				$mc = CRM_ContactsCommon::get_main_company();
                if ($mc > 0) {
                    $company = CRM_ContactsCommon::get_company($mc);
                    if ($company) {
                        $defaults = array_merge($company, $defaults);
                    }
                }
                $f->setDefaults($defaults);
            }
        }
        $revalidation_notice = '';
        $email_change_notice = '';
        if ($data) {
            if (isset($data['status']) && strcasecmp($data['status'], 'Confirmed') == 0)
                $revalidation_notice = __('Updating Company data will require re-validation by our representative.');
            $email_change_notice = __('Changing Administrator e-mail address will require e-mail confirmation.');
        }

        $f->addElement('submit', 'submit', $data ? __('Update') : __('Register'));

        if (Base_ThemeCommon::is_adminlte_family()) {
            $theme = $this->pack_module(Base_Theme::module_name());
            $f->assign_theme('form', $theme);
            $theme->assign('intro', $intro);
            $theme->assign('auto_filled_notice', $auto_filled_notice);
            $theme->assign('revalidation_notice', $revalidation_notice);
            $theme->assign('email_change_notice', $email_change_notice);
            $theme->display('register_form');
            return true;
        }

        print('<div class="important_notice">');
        print($intro);
        print('<br>');
        if ($auto_filled_notice) {
            print('<span style="color:gray;font-size:10px;">' . $auto_filled_notice . '</span>');
        }
        if ($revalidation_notice) {
            print('<div style="color:gray;font-size:10px;">'.$revalidation_notice.'</div>');
        }
        if ($email_change_notice) {
            print('<div style="color:red;font-size:10px;">'.$email_change_notice.'</div>');
        }
        print('<center>');
        $f->display_as_column();
        print('</center>');
        print('</div>');
        return true;
    }

    public function license_key_form() {
        if ($this->is_back()) {
            return false;
        }
        Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());

        $f = $this->init_module(Libs_QuickForm::module_name());

        $license_key_attrs = Base_ThemeCommon::is_adminlte_family()
            ? array('maxlength' => 64, 'size' => 64)
            : array('maxlength' => 64, 'size' => 64, 'style' => 'width:395px;');
        $f->addElement('text', 'license_key', __('License Key'), $license_key_attrs);
        if ($f->validate()) {
            $x = $f->exportValues();
            Base_EssClientCommon::set_license_key($x['license_key']);
            return false;
        }

        $f->setDefaults(array('license_key' => Base_EssClientCommon::get_license_key()));
        Base_ActionBarCommon::add('save', __('Save'), $f->get_submit_form_href());

        $notice = __('On this screen you can manually set your License Key for this installation. This feature should only be used in case of system recovery or migration. If you are uncertain how to use this feature, it\'s best to leave this screen immediately.');

        if (Base_ThemeCommon::is_adminlte_family()) {
            $theme = $this->pack_module(Base_Theme::module_name());
            $f->assign_theme('form', $theme);
            $theme->assign('notice', $notice);
            $theme->display('license_key_form');
            return true;
        }

        print('<span class="important_notice"><center>');
        print($notice . '<br><br>');
        $f->display_as_column();
        print('</center></span>');
        return true;
    }

    public function no_ssl_settings() {
        $f = $this->init_module("Libs/QuickForm");
        $f->addElement('checkbox', 'allow', 'Allow unsecure connection', null, array('class'=>'epesi-switch'));
        Base_ActionBarCommon::add('back', __('Back'), Base_BoxCommon::pop_main_href());
        Base_ActionBarCommon::add('save', __('Save'), $f->get_submit_form_href());
        if ($f->validate()) {
            $x = $f->exportValues();
            $allow = false;
            if (isset($x['allow']) && $x['allow'])
                $allow = true;
            Base_EssClientCommon::set_no_ssl_allow($allow);
            Base_BoxCommon::pop_main();
            return;
        }
        $f->setDefaults(array('allow' => Base_EssClientCommon::is_no_ssl_allowed()));
        
        print('<div class="important_notice">');
        print(__('Allowing unsecure connection will cause all the data to be transferred without encryption. This creates opportunity for third parties to capture the data being transmitted, including your License Key. Please note that License Key should be kept confidential and that using the same License Key on several Epesi installations is a direct violation of Terms of Service and will result in termination of the License Key.'));
        print('<center>');
        $f->display();
        print('</center>');
        print('</div>');
    }

}

?>