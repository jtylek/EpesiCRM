<?php

/**
 * 
 * @author abukowski@telaxus.com
 * @copyright Telaxus LLC
 * @license MIT
 * @version 0.1
 * @package epesi-Base
 * @subpackage EssClient
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_EssClient extends Module {

    public function body() {

    }

    /**
     * for tests only.
     */
    public function clear_license_key() {
        Variable::set("license_key", '');
    }

    public function admin() {
        if (!Base_AclCommon::i_am_sa())
            return;
        if ($this->is_back()) {
            $this->parent->reset();
        }
        Base_ActionBarCommon::add('back', 'Back', $this->create_back_href());
//        Base_ActionBarCommon::add('delete', 'Clear license key', $this->create_callback_href(array($this, 'clear_license_key')));

        if (Base_EssClientCommon::get_license_key() == "") {
            $this->register_form();
            Base_EssClientCommon::server(true);
        }
        if (Base_EssClientCommon::get_license_key()) {
            print($this->t('Your installation is registered.') . '<br/>');
            $data = Base_EssClientCommon::server()->installation_registered_data();
            $data['license_key'] = Base_EssClientCommon::get_license_key();
            $data['status'] = Base_EssClientCommon::server()->installation_status();
            // handle different status messages
            if (strcasecmp($data['status'], "new") == 0 || strcasecmp($data['status'], "updated") == 0) {
                print($this->t('<div style="color: red">Wait for your company data validation by our service and come back here to confirm installation!</div>'));
            }
            if (strcasecmp($data['status'], "validated") == 0) {
                print('<a class="button" ' . $this->create_callback_href(array($this, 'confirm_installation')) . '>' . $this->t('Confirm Installation') . '</a><br/>');
            }
            Base_ActionBarCommon::add('edit', 'Edit company details', $this->create_callback_href(array($this, 'navigate'), array('register_push_main', array($data))));
            $this->register_form(false, $data);
        }
    }

    public function confirm_installation() {
        $r = Base_EssClientCommon::server()->register_installation_confirm();
        $color = $r ? 'green' : 'red';
        $text = $r ? 'Installation confirmed!' : 'Confirmation error!';
        print('<div style="color: ' . $color . '">' . $this->t($text) . '</div>');
    }

    public function edit_data() {
        $data = Base_EssClientCommon::server()->installation_registered_data();
        $this->navigate('register', array($data));
    }

    protected function add_static_field($form, $variable, $label, $data) {
        $value = array_key_exists($variable, $data) ? $data[$variable] : '';
        $form->addElement('static', $variable, $this->t($label), $value);
    }

    protected function register_form($edit = true, $data = null) {
        $f = $this->init_module('Libs/QuickForm');

        $admin_email_tooltip = '<img ' .
                Utils_TooltipCommon::open_tag_attrs($this->t("This email will be used to send registation link and to contact Administator directly."))
                . ' src="' . Base_ThemeCommon::get_icon('info') . '"/> ';

        if ($edit) {
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
        } else {
            $this->add_static_field($f, 'status', 'Installation status', $data);
            $this->add_static_field($f, 'license_key', 'License key', $data);
            $this->add_static_field($f, 'company_name', 'Company name', $data);
            $this->add_static_field($f, 'short_name', 'Short name', $data);
            $this->add_static_field($f, 'phone', 'Phone', $data);
            $this->add_static_field($f, 'fax', 'Fax', $data);
            $this->add_static_field($f, 'email', 'Company email', $data);
            $this->add_static_field($f, 'web_address', 'Web address', $data);
            $this->add_static_field($f, 'address_1', 'Address 1', $data);
            $this->add_static_field($f, 'address_2', 'Address 2', $data);
            $this->add_static_field($f, 'city', 'City', $data);
            $f->addElement('static', 'country', $this->t('Country'), isset($data['country']) ? Utils_CommonDataCommon::get_value('Countries/' . $data['country'], true) : '');
            $f->addElement('static', 'zone', $this->t('Zone'), isset($data['country']) && isset($data['zone']) ? Utils_CommonDataCommon::get_value('Countries/' . $data['country'] . '/' . $data['zone'], true) : '');
            $this->add_static_field($f, 'postal_code', 'Postal Code', $data);
            $this->add_static_field($f, 'admin_first_name', 'Administator first name', $data);
            $this->add_static_field($f, 'admin_last_name', 'Administator last name', $data);
            $f->addElement('static', 'admin_email', $admin_email_tooltip . $this->t('Administrator\'s email'), isset($data['admin_email']) ? $data['admin_email'] : '');
        }

        if ($edit) {
            if ($f->validate()) {
                $ret = $f->exportValues();

                $ret = Base_EssClientCommon::server()->register_installation_request($ret);

                $text = '';
                $color = 'black';
                if ($ret) {
                    if (is_string($ret))
                        Base_EssClientCommon::set_license_key($ret);

                    $text = ($data ? 'Update' : 'Registration') . ' successful';
                    $color = 'green';
                } else {
                    $text = 'Some kind of error!';
                    $color = 'red';
                }
                print('<div style="color: ' . $color . '">' . $this->t($text) . '</div>');
                Base_StatusBarCommon::message($this->t($text));
            } else {
                // set defaults
                if ($data) {
                    $f->setDefaults($data);
                } else {
                    print($this->t('<span style="color:gray">Data below was auto-filled from Main Company\'s and first Super administrator\'s data.<br/>Make sure that data is correct and change if necessary.</span>'));
                    $defaults = array_merge(CRM_ContactsCommon::get_company(CRM_ContactsCommon::get_main_company()), Base_EssClientCommon::get_possible_admin());
                    $f->setDefaults($defaults);
                }
                Base_ActionBarCommon::add('send', $data ? 'Update' : 'Register', $f->get_submit_form_href());
                if ($data && isset($data['status']) && $data['status'] == 'Confirmed')
                    print($this->t('<div style="color: red">If you update company data, you need to confirm your installation once again!</div>'));
                $f->display();
            }
        } else {
            $f->display();
        }
    }

    public function register_push_main($data = null) {
        if ($this->is_back()) {
            $this->pop_main();
        }
        Base_ActionBarCommon::add('back', 'Back', $this->create_back_href());
        $this->register_form(true, $data);
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