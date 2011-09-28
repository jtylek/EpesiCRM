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
                    $data['status'] = Base_EssClientCommon::server()->installation_status();
                    // handle different status messages
					//print($data['status'].'<br>');
                    if (strcasecmp($data['status'], "new") == 0 || strcasecmp($data['status'], "updated") == 0) {
						print('<div class="important_notice_frame">');
                        print('<span style="font-weight:bold;">'.
							$this->t('Status:').
							'</span> '.
							$this->t($data['status']).', '.$this->t('requires e-mail confirmation'));
						print('</div>');
						print($this->t('You need to verify your e-mail address. An e-mail was sent to the Administrator\'s e-mail address with a link to confirm the e-mail address.'));
                    }
                    if (strcasecmp($data['status'], "confirmed") == 0 || strcasecmp($data['status'], "confirmed (update)") == 0) {
						print('<div class="important_notice_frame">');
                        print('<span style="font-weight:bold;">'.
							$this->t('Status:').
							'</span> '.
							$this->t($data['status']).', '.$this->t('awaiting verification'));
						print('</div>');
						print($this->t('Epesi team representative will verify the data you submited to avoid processing invalid information.'));
                    }
					if (strcasecmp($data['status'], "validated") == 0) {
						print('<div class="important_notice_frame">');
                        print('<span style="font-weight:bold;">'.
							$this->t('Status:').
							'</span> '.
							$this->t($data['status']));
						print('</div>');
						print($this->t('The registration process is complete.'));
						print('</div>');
                    }
					print('</div>');
                    Base_ActionBarCommon::add('edit', 'Edit company details', $this->create_callback_href(array($this, 'register_form'), array(true, $data)));
                } else {
					$email = Base_EssClientCommon::get_support_email();

                    print('<div class="important_notice">'.$this->t('Your epesi ID is not recognized by Epesi Service Server. Please contact epesi team at %s.', array($email)).'</div>');
                    Base_ActionBarCommon::add('delete', 'Revoke License Key', $this->create_confirm_callback_href($this->t('Are you sure you want to revoke your Epesi License Key?'),array($this, 'clear_license_key')));
                }
            }
        } catch (Exception $e) {
			print('<div class="important_notice">'.$this->t('There was an error while trying to connect to Epesi Service Server. Please try again later.').'<br>');
			print($this->t('If the problem persists, please contact us at %s', array('<a href="http://forum.epesibim.com/" target="_blank">http://forum.epesibim.com/</a>')).'<br>');
			print('<br>');
			print($this->t('Error message: ').'<br>');
            print('<div class="important_notice_frame">'.$e->getMessage());
			print('</div></div>');
			Base_ActionBarCommon::add('retry', 'Retry', $this->create_href(array()));
            return;
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
	
	protected function terms_and_conditions() {
		if ($this->get_module_variable('t_and_c_accepted')) {
			$this->register_form();
			return;
		}

		$form = $this->init_module('Libs_QuickForm');
		$form->addElement('checkbox', 'agree', $this->t('Agree to Terms and Conditions'));
		$form->addRule('agree', $this->t('You must accept Terms and Conditions to proceed'), 'required');
		$form->addElement('submit', 'submit', $this->t('Obtain Epesi License Key'), array('style'=>'width:200px'));
		if ($form->validate()) {
			$this->set_module_variable('t_and_c_accepted', true);
			location(array());
			return;
		}
		print('<div class="important_notice">');
		print($this->t('Here you can register your epesi installation with epesi team, allowing you to browse and make purchases in Epesi Store. Your License Key will be used to identify your installation and allow you to download and use modules you purchase. Please understand that Epesi License Key shouldn\'t be copied to any other epesi installation and purchases and downloads you make using your Epesi License Key should be used for only this installation.'));
		print('<br><br>');
		print($this->t('If necessary, you can move your installation to another server and keep your Epesi License Key, but at any given time no two installations should use single Epesi License Key.'));
		print('<br><br>');
		print($this->t('Full Terms and Conditions are available here:')); // FIXME
		print('<center>');
		$form->display();
		print('</center>');
		print('</div>');
		return;
	}

    protected function register_form($edit = true, $data = null) {
        if ($this->is_back()) {
            return false;
        }
        Base_ActionBarCommon::add('back', 'Back', $this->create_back_href());

        $f = $this->init_module('Libs/QuickForm');

        $admin_email_tooltip = '<img ' .
                Utils_TooltipCommon::open_tag_attrs($this->t("This email will be used to send registation link and to contact Administator directly."), false)
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

                if (!$ret) {
					$email = Base_EssClientCommon::get_support_email();
					print('<div class="important_notice">');
                    print('<div style="color:red">'.$this->t('There was an error processing your request.').'</div>'.
							'<br>'.
							$this->t(' Please try again later. If the problem persists, please contact us at %s.', array($email)));
					print('</div>');
					return true;
                }
				if (is_string($ret))
					Base_EssClientCommon::set_license_key($ret);
					
				location(array());
				return false;
            }
			// set defaults
			print('<div class="important_notice">');
			print($this->t('Enter Company and Administrator details. This data will be sent to Epesi Service Server to provide us with contact information. The data sent to Epesi Service Server is limited only to the data you enter using this form and what modules are being purchased and downloaded.'));
			print('<br>');
			if ($data) {
				$f->setDefaults($data);
			} else {
				print('<span style="color:gray;font-size:10px;">'.$this->t('Data below was auto-filled based on Main Company and first Super administrator. Make sure that the data is correct and change it if necessary.').'</span>');
				$defaults = array_merge(CRM_ContactsCommon::get_company(CRM_ContactsCommon::get_main_company()), Base_EssClientCommon::get_possible_admin());
				$f->setDefaults($defaults);
			}
			//Base_ActionBarCommon::add('send', $data ? 'Update' : 'Register', $f->get_submit_form_href());
			if ($data && isset($data['status']) && $data['status'] == 'Confirmed')
				print($this->t('<div style="color:gray;font-size:10px;">Updating Company data will required re-validation by our representative.</div>'));
				print($this->t('<div style="color:tomato;font-size:10px;">Changing Administrator e-mail address will require e-mail confirmation.</div>'));
			print('<center>');

			$f->addElement('submit', 'submit', $data ? 'Update' : 'Register');

			$f->display();
			print('</center>');
			print('</div>');
        } else {
            $f->display();
        }
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