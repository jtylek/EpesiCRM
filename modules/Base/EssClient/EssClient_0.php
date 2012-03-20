<?php

/**
 * @author Adam Bukowski <abukowski@telaxus.com>
 * @copyright Telaxus LLC
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
            $this->pop_main();
        }
        $this->admin();
    }

    public function admin() {
        if (!Base_AclCommon::i_am_sa())
            return;
        if ($this->is_back()) {
            $this->parent->reset();
        }
        Base_ActionBarCommon::add('back', 'Back', $this->create_back_href());

        print Base_EssClientCommon::client_messages_frame();
        
        if (Base_EssClientCommon::get_license_key() == "") {
            $this->terms_and_conditions();
            Base_EssClientCommon::server(true);
        }
        try {
            if (Base_EssClientCommon::get_license_key()) {
                $data = Base_EssClientCommon::server()->installation_registered_data();
                if ($data) {
                    print('<div class="important_notice">');
                    print($this->t('Thank you for registering your epesi installation.') . '<br/>');
                    $data['license_key'] = Base_EssClientCommon::get_license_key();
                    $data['status'] = Base_EssClientCommon::get_installation_status();
                    // handle different status messages
                    if (strcasecmp($data['status'], "new") == 0 || strcasecmp($data['status'], "updated") == 0) {
                        print('<div class="important_notice_frame">');
                        print('<span style="font-weight:bold;">' .
                                $this->t('Status:') .
                                '</span> ' .
                                $this->t($data['status']) . ', ' . $this->t('requires e-mail confirmation'));
                        print('</div>');
                        print($this->t('You need to verify your e-mail address. An e-mail was sent to the Administrator\'s e-mail address with a link to confirm the e-mail address.'));
                    }
                    if (strcasecmp($data['status'], "confirmed") == 0 || strcasecmp($data['status'], "confirmed (update)") == 0 || strcasecmp($data['status'], "new_confirmed") == 0 || strcasecmp($data['status'], "updated_confirmed") == 0) {
                        print('<div class="important_notice_frame">');
                        print('<span style="font-weight:bold;">' .
                                $this->t('Status:') .
                                '</span> ' .
                                $this->t($data['status']) . ', ' . $this->t('awaiting verification'));
                        print('</div>');
                        print($this->t('Epesi team representative will verify the data you submited to avoid processing invalid information.'));
                    }
                    if (strcasecmp($data['status'], "validated") == 0) {
                        print('<div class="important_notice_frame">');
                        print('<span style="font-weight:bold;">' .
                                $this->t('Status:') .
                                '</span> ' .
                                $this->t($data['status']));
                        print('</div>');
                        print($this->t('The registration process is complete.'));
                        print('</div>');
                    }
                    print('</div>');
                    Base_ActionBarCommon::add('edit', 'Edit company details', $this->create_callback_href(array($this, 'register_form'), array($data)));
                } else {
                    $email = Base_EssClientCommon::get_support_email();

                    print('<div class="important_notice">' . $this->t('Your epesi ID is not recognized by Epesi Store Server. Please contact epesi team at %s.', array($email)) . '</div>');
                    Base_ActionBarCommon::add('delete', 'Revoke License Key', $this->create_confirm_callback_href($this->t('Are you sure you want to revoke your Epesi License Key?'), array('Base_EssClientCommon', 'clear_license_key')));
                }
            }
        } catch (Exception $e) {
            print('<div class="important_notice">' . $this->t('There was an error while trying to connect to Epesi Store Server. Please try again later.') . '<br>');
            print($this->t('If the problem persists, please contact us at %s', array('<a href="http://forum.epesibim.com/" target="_blank">http://forum.epesibim.com/</a>')) . '<br>');
            print('<br>');
            print($this->t('Error message: ') . '<br>');
            print('<div class="important_notice_frame">' . $e->getMessage());
            print('</div></div>');
            Base_ActionBarCommon::add('retry', 'Retry', $this->create_href(array()));
            return;
        }
        Base_EssClientCommon::client_messages_load_by_js();
    }

    private function terms_and_conditions() {
        if ($this->get_module_variable('t_and_c_accepted')) {
            $this->register_form();
            return;
        }

        $form = $this->init_module('Libs_QuickForm');
        $form->addElement('checkbox', 'agree', $this->t('Agree to Terms and Conditions'));
        $form->addRule('agree', $this->t('You must accept Terms and Conditions to proceed'), 'required');
        $form->addElement('submit', 'submit', $this->t('Obtain Epesi License Key'), array('style' => 'width:200px'));
        if ($form->validate()) {
            $this->set_module_variable('t_and_c_accepted', true);
            location(array());
            return;
        }
		
        print('<div class="important_notice" style="-moz-border-radius: 10px; -webkit-border-radius: 10px; border-radius: 10px;">');
		print('<center><H1>');
		print($this->t('epesiBIM Registration'));
		print('</H1></center><br>');
        print($this->t('Registration of your epesi installation with '));
		print('<a href="http://www.telaxus.com" target="_blank">Telaxus LLC </a>');
		print($this->t('will allow you to browse and make purchases in <strong>Epesi Store</strong> and receive notifications via e-mail about important updates.<br> Once the registration is complete you will receive a <strong>License Key</strong>. '));
		print($this->t('This unique License Key will be used to identify your installation and allow you to download and use modules you purchase. Please note that <strong>Epesi License Key</strong> can not be copied to any other epesi installation. '));
		print($this->t('All purchases and downloads you make using your Epesi License Key can be used for this installation only.'));
        print('<br><br>');
        print($this->t('If necessary, you can move your installation to another server and keep your Epesi License Key, but at any given time no two installations can use the same Epesi License Key. '));
        print($this->t('Sharing your license key with unauthorized users is a violation of this agreement and will result in revoking the License Key.'));
		print('<br><br>');
        print($this->t('Full Terms and Conditions are available here:'));
		$url = get_epesi_url().'/modules/Base/EssClient/tos/tos.php';
        print('<br><a target="_blank" href="'.$url.'">'.$url.'</a>');
        print('<center>');
        $form->display();
        print('</center>');
        print('</div>');
        return;
    }

    public function register_form($data = null) {
        if ($this->is_back()) {
            return false;
        }
        Base_ActionBarCommon::add('back', 'Back', $this->create_back_href());

        $f = $this->init_module('Libs/QuickForm');

        $admin_email_tooltip = '<img ' .
                Utils_TooltipCommon::open_tag_attrs($this->t("This email will be used to send registation link and to contact Administator directly."), false)
                . ' src="' . Base_ThemeCommon::get_icon('info') . '"/> ';

        $reseller_tooltip = '<img ' .
                Utils_TooltipCommon::open_tag_attrs($this->t("If you don't have Epesi Reseller login leave this field empty."), false)
                . ' src="' . Base_ThemeCommon::get_icon('info') . '"/> ';

        $f->addElement('text', 'company_name', $this->t('Company name'), array('maxlength' => 128));
        $f->addRule('company_name', $this->t('Field required'), 'required');
        $f->addRule('company_name', $this->t('Max length exceeded'), 'maxlength', 128);

        $f->addElement('text', 'short_name', $this->t('Short name'), array('maxlength' => 64));
        $f->addRule('short_name', $this->t('Max length exceeded'), 'maxlength', 64);

        $f->addElement('text', 'phone', $this->t('Phone'), array('maxlength' => 64));
        $f->addRule('phone', $this->t('Field required'), 'required');
        $f->addRule('phone', $this->t('Max length exceeded'), 'maxlength', 64);

        $f->addElement('text', 'fax', $this->t('Fax'), array('maxlength' => 64));
        $f->addRule('fax', $this->t('Max length exceeded'), 'maxlength', 64);

        $f->addElement('text', 'email', $this->t('Company email'), array('maxlength' => 128));
        $f->addRule('email', $this->t('Field required'), 'required');
        $f->addRule('email', $this->t('Max length exceeded'), 'maxlength', 128);
        $f->addRule('email', $this->t('Invalid e-mail address'), 'email');

        $f->addElement('text', 'web_address', $this->t('Web address'), array('maxlength' => 64));
        $f->addRule('web_address', $this->t('Max length exceeded'), 'maxlength', 64);

        $f->addElement('text', 'address_1', $this->t('Address 1'), array('maxlength' => 64));
        $f->addRule('address_1', $this->t('Field required'), 'required');
        $f->addRule('address_1', $this->t('Max length exceeded'), 'maxlength', 64);

        $f->addElement('text', 'address_2', $this->t('Address 2'), array('maxlength' => 64));
        $f->addRule('address_2', $this->t('Max length exceeded'), 'maxlength', 64);

        $f->addElement('text', 'city', $this->t('City'), array('maxlength' => 64));
        $f->addRule('city', $this->t('Field required'), 'required');
        $f->addRule('city', $this->t('Max length exceeded'), 'maxlength', 64);

        $f->addElement('commondata', 'country', $this->t('Country'), 'Countries');
        $f->addRule('country', $this->t('Field required'), 'required');
        $f->addElement('commondata', 'zone', $this->t('Zone'), array('Countries', 'country'), array('empty_option' => true));

        $f->addElement('text', 'postal_code', $this->t('Postal Code'), array('maxlength' => 64));
        $f->addRule('postal_code', $this->t('Field required'), 'required');
        $f->addRule('postal_code', $this->t('Max length exceeded'), 'maxlength', 64);

        $f->addElement('text', 'admin_first_name', $this->t('Administrator\'s first name'), array('maxlength' => 64));
        $f->addRule('admin_first_name', $this->t('Field required'), 'required');
        $f->addRule('admin_first_name', $this->t('Max length exceeded'), 'maxlength', 64);

        $f->addElement('text', 'admin_last_name', $this->t('Administrator\'s last name'), array('maxlength' => 64));
        $f->addRule('admin_last_name', $this->t('Field required'), 'required');
        $f->addRule('admin_last_name', $this->t('Max length exceeded'), 'maxlength', 64);

        $f->addElement('text', 'admin_email', $admin_email_tooltip . $this->t('Administrator\'s email'), array('maxlength' => 128));
        $f->addRule('admin_email', $this->t('Field required'), 'required');
        $f->addRule('admin_email', $this->t('Max length exceeded'), 'maxlength', 128);
        $f->addRule('admin_email', $this->t('Invalid e-mail address'), 'email');

        $f->addElement('text', 'reseller', $reseller_tooltip . $this->t('Epesi Reseller login'), array('maxlength' => 32));
        $f->addRule('admin_first_name', $this->t('Max length exceeded'), 'maxlength', 32);

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
        print Base_EssClientCommon::client_messages_frame(false);
        // set defaults
        print('<div class="important_notice">');
        print($this->t('Enter Company and Administrator details. This data will be sent to Epesi Store Server to provide us with contact information. The data sent to Epesi Store Server is limited only to the data you enter using this form and what modules are being purchased and downloaded.'));
        print('<br>');
        if ($data) {
            $f->setDefaults($data);
        } else {
            if (ModuleManager::is_installed('CRM_Contacts') > -1) {
                print('<span style="color:gray;font-size:10px;">' . $this->t('Data below was auto-filled based on Main Company and first Super administrator. Make sure that the data is correct and change it if necessary.') . '</span>');
                $defaults = array_merge(CRM_ContactsCommon::get_company(CRM_ContactsCommon::get_main_company()), Base_EssClientCommon::get_possible_admin());
                $f->setDefaults($defaults);
            }
        }
        //Base_ActionBarCommon::add('send', $data ? 'Update' : 'Register', $f->get_submit_form_href());
        if ($data) {
            if (isset($data['status']) && strcasecmp($data['status'], 'Confirmed') == 0)
                print($this->t('<div style="color:gray;font-size:10px;">Updating Company data will required re-validation by our representative.</div>'));
            print($this->t('<div style="color:tomato;font-size:10px;">Changing Administrator e-mail address will require e-mail confirmation.</div>'));
        }
        print('<center>');

        $f->addElement('submit', 'submit', $data ? 'Update' : 'Register');

        $f->display();
        print('</center>');
        print('</div>');
        return true;
    }

    public function navigate($func, $params = array()) {
        $x = ModuleManager::get_instance('/Base_Box|0');
        if (!$x)
            trigger_error('There is no base box module instance', E_USER_ERROR);
        $x->push_main('Base/EssClient', $func, $params);
        return false;
    }

    public function pop_main() {
        $x = ModuleManager::get_instance('/Base_Box|0');
        if (!$x)
            trigger_error('There is no base box module instance', E_USER_ERROR);
        $x->pop_main();
    }

}

?>