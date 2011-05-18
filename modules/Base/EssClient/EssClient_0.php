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

    public function admin() {
        if ($this->is_back()) {
            $this->parent->reset();
        }
        Base_ActionBarCommon::add('back', 'Back', $this->create_back_href());
//        Variable::set("license_key", '');

        if (Base_EssClientCommon::get_license_key() == "") {
            $this->navigate('register');
        } else {
            print($this->t('Your installation is registered.') . '<br/>');
            $status = Base_EssClientCommon::server()->get_installation_status();
            print($this->t('Installation status is: ') . $this->t($status) . '<br/>');
            if(strcasecmp($status, "validated") == 0) {
                print('<a ' . $this->create_callback_href(array($this, 'confirm_installation')) . '>' . $this->t('Confirm Installation') . '</a></br>');
            }
            print('<a ' . $this->create_callback_href(array($this, 'edit_data')) . '>' . $this->t('Edit registered company details') . '</a>');
        }
    }

    public function confirm_installation() {
        $r = Base_EssClientCommon::server()->register_client_id_confirm();
        $color = $r ? 'green' : 'red';
        $text = $r ? 'Installation confirmed!' : 'Confirmation error!';
        print('<div style="color: ' . $color . '">' . $this->t($text) . '</div>');
    }

    public function edit_data() {
        $data = Base_EssClientCommon::server()->get_registered_data();
        $this->navigate('register', array($data));
    }

    public function register($data = null) {
        if ($this->is_back()) {
            $this->pop_main();
        }
        Base_ActionBarCommon::add('back', 'Back', $this->create_back_href());

        $f = $this->init_module('Libs/QuickForm');

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

        $f->addElement('text', 'email', $this->t('Email'), array('maxlength' => 128));
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

        $f->addElement('text', 'tax_id', $this->t('Tax ID'), array('maxlength' => 64));
        $f->addRule('tax_id', $this->t('Field required'), 'required');
        $f->addRule('tax_id', $this->t('Max length exceeded'), 'maxlength', 64);

        if($data) {
            $f->setDefaults($data);
        } else {
            $f->setDefaults(CRM_ContactsCommon::get_company(CRM_ContactsCommon::get_main_company()));
        }

        Base_ActionBarCommon::add('send', 'Register', $f->get_submit_form_href());

        if ($f->validate()) {
            $ret = $f->exportValues();

            $ret = Base_EssClientCommon::server()->register_client_id_request($ret);

            if($ret) {
                if(is_string($ret))
                    Base_EssClientCommon::set_license_key($ret);

                Base_StatusBarCommon::message($this->t('Registered successfully'));
                $this->pop_main();
            } else {
                print($this->t("Some kind of error! :("));
                Base_StatusBarCommon::message($this->t('Registration error'));
            }
        }
        $f->display();
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