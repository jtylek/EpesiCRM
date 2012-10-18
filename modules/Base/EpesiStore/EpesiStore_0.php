<?php

/**
 * 
 * @author Adam Bukowski <abukowski@telaxus.com>
 * @copyright Copyright &copy; 2011,2012 Telaxus LLC
 * @license MIT
 * @version 20121013
 * @package epesi-Base
 * @subpackage EpesiStore
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_EpesiStore extends Module {
    // colors

    const color_success = 'green';
    const color_failure = 'gray';

    protected $banned_columns_module = array('id', 'owner_id', 'path', 'files', 'active');
    protected $banned_columns_order = array('id', 'installation_id');

    public function body() {
        
    }

    public function admin() {
        if ($this->is_back()) {
            $this->parent->reset();
            return;
        }
        $this->back_button();
		$this->manage();
	}

    public function manage() {
        if (!Base_EpesiStoreCommon::admin_access())
            return;
        $button_label = Base_EssClientCommon::has_license_key()
                ? __('License Key') : __('Register EPESI!');
		Base_ActionBarCommon::add(
                Base_ThemeCommon::get_template_file('Base_EpesiStore','icon.png'),
                $button_label,
                $this->create_callback_href(array($this,'display_registration_form')));

		Base_ActionBarCommon::add('view', __('Invoices'), $this->create_callback_href(array($this,'display_invoices')));

        $setup = $this->init_module('Base_Setup');
        if (Base_SetupCommon::is_simple_setup()) {
			if (!$this->isset_module_variable('filter_set')) {
				eval_js('base_setup__last_filter="'.(!Base_EssClientCommon::has_license_key()?'':(Base_EpesiStoreCommon::is_update_available()?'updates':'store')).'";');
				$this->set_module_variable('filter_set', true);
			}
            $this->display_module($setup, array(true), 'admin');
            return;
        }

        Base_ActionBarCommon::add('settings', __('Simple view'), $this->create_callback_href(array($this, 'switch_simple'), true));
        $tb = $this->init_module('Utils_TabbedBrowser');
		if (TRIAL_MODE)
			$tb->set_tab('Modules Setup', array($this, 'setup_admin'), array($setup));
		$tb->set_tab('Epesi Store', array($this, 'form_main_store'), array());
		if (!TRIAL_MODE)
			$tb->set_tab('Modules Setup', array($this, 'setup_admin'), array($setup));
        $tb->tag();
        $this->display_module($tb);
    }
	
	public function display_invoices() {
        if ($this->is_back())
            return false;
		Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());

        $action_url = Base_EssClientCommon::get_invoices_url();
		$params = array('key'=>Base_EssClientCommon::get_license_key(), 'noheader'=>1);
        print create_html_form($form_name, $action_url, $params, 'invoices');
        print('<iframe name="invoices" width="800px" style="border: none;" height="600px"></iframe>');
        eval_js("document.{$form_name}.submit();");
		return true;
	}
	
	public function setup_admin($setup) {
		$this->display_module($setup, array(true), 'admin');
	}

    public function switch_simple($value) {
        Base_SetupCommon::set_simple_setup($value);
        location(array());
    }

    private function client_messages() {
        print(Base_EssClientCommon::client_messages_frame());
    }

    private function navigation_buttons() {
        $this->navigation_button_cart();
        $this->navigation_button_your_modules();
        $this->navigation_button_orders();
        $this->navigation_button_downloads(false);
    }

    private function navigation_button_cart($display_empty = false) {
        $count = count(Base_EpesiStoreCommon::get_cart());
        if ($display_empty || $count) {
            if ($count == 0)
                $count = __('Empty');
            Base_ActionBarCommon::add('folder', __('Cart').' ('.$count.')', $this->href_navigate('form_cart'), __('Data is stored until close or refresh of browser\'s EPESI window or tab'));
        }
    }

    private function navigation_button_downloads($display_empty = false) {
        $count = count(Base_EpesiStoreCommon::get_download_queue());
        if ($display_empty || $count) {
            if ($count == 0)
                $count = __('Empty');
            $download = __('Downloads');
            Base_ActionBarCommon::add('clone', "$download ($count)", $this->href_navigate('form_downloads'));
        }
    }

    private function navigation_button_orders() {
        Base_ActionBarCommon::add('view', __('Orders'), $this->href_navigate('form_orders'), __('Here you can pay for ordered modules'));
    }

    private function navigation_button_your_modules() {
        Base_ActionBarCommon::add('search', __('Your modules'), $this->href_navigate('form_your_modules'), __('Install bought modules, check for updates'));
    }

    public function form_main_store() {
        if (Base_EssClientCommon::has_license_key()) {
            $this->navigation_buttons();
            $this->display_modules();
        } else {
            if (TRIAL_MODE) {
                print('<span class="important_notice">EPESI store is unavailable during the trial.</span>');
            } else {
                $this->display_registration_form();
            }
        }
        $this->client_messages();
    }

    private function display_modules() {
        /* @var $gb Utils_GenericBrowser */
        $gb = $this->init_module('Utils/GenericBrowser', null, 'moduleslist');
        // We need total amount of available modules to set GB paging.
        // It's returned with each request for modules list
        // so with first request we use only get_limit to retrieve
        // 'per_page' (numrows) value, then again get_limit to set proper
        // total amount value.
        $total = $this->get_module_variable('modules_total');
        $x = $gb->get_limit($total === null ? 500 : $total);
        // fetch data
        $ret = Base_EpesiStoreCommon::modules_list($x['offset'], $x['numrows']);
        if ($total === null)
            $x = $gb->get_limit($ret['total']);
        $this->set_module_variable('modules_total', $ret['total']);
        if (!$ret['total'])
            print(__('Unfortunately there are no modules available for you.'));
        else {
            $gb = $this->GB_module($gb, $ret['modules'], array($this, 'GB_row_additional_actions_store'));
            $this->display_module($gb);
        }
    }

	static $return = true;
    public function reset() {
		self::$return = false;
		location(array());
	}
    public function display_registration_form() {
        $m = $this->init_module('Base/EssClient');
        $this->display_module($m, array(true), 'admin');
		return self::$return;
    }

    public function form_your_modules() {
        $this->back_button();
        $this->navigation_button_downloads();

        $module_licenses = Base_EssClientCommon::server()->module_licenses_list();
        $this->client_messages();
        $this->display_your_modules($module_licenses);
    }

    private function display_your_modules($module_licenses) {
        if (count($module_licenses) == 0) {
            print(__('You haven\'t bought any modules'));
            return;
        }
        $this->_module_licenses_add_module_versions($module_licenses);
        $to_download = $this->_modules_to_download_and_update($module_licenses);
        if ($to_download)
            Base_ActionBarCommon::add('favorites', __('Download newer'), $this->create_callback_href(array($this, 'download_modules'), array($to_download)));
        $gb = $this->init_module('Utils/GenericBrowser', null, 'mymoduleslist');
        $gb = $this->GB_module_licenses($gb, $module_licenses, array($this, 'GB_row_additional_actions_your_modules'));
        $this->display_module($gb);
    }

    private function _module_licenses_add_module_versions(& $module_licenses) {
        $downloaded = $this->_get_downloaded_modules_versions();
        foreach ($module_licenses as & $ml) {
            $modinfo = Base_EpesiStoreCommon::get_module_info($ml['module']);
            $ml['downloaded_version'] = isset($downloaded[$ml['module']]) ? $downloaded[$ml['module']] : '';
            $ml['current_version'] = $modinfo['version'];
        }
    }

    private function _get_downloaded_modules_versions() {
        $downloaded_modules = Base_EpesiStoreCommon::get_downloaded_modules();
        $downloaded = array();
        foreach ($downloaded_modules as $d) {
            $downloaded[$d['module_id']] = $d['version'];
        }
        return $downloaded;
    }

    private function _modules_to_download_and_update($module_licenses) {
        $to_download = array();
        foreach ($module_licenses as $ml) {
            if ($this->_module_license_needs_download_or_update($ml))
                $to_download[] = $ml;
        }
        return $to_download;
    }

    private function _module_license_needs_download_or_update($module_license) {
        return !$module_license['downloaded_version']
                || !Base_EpesiStoreCommon::is_module_downloaded($module_license['module'])
                || $this->_version_is_newer($module_license['downloaded_version'], $module_license['current_version']);
    }

    private function _version_is_newer($old_version, $new_version) {
        return Base_EpesiStoreCommon::version_compare($old_version, $new_version) < 0;
    }

    public function form_cart() {
        $this->back_button();

        $items = Base_EpesiStoreCommon::get_cart();

        $this->display_cart($items);
    }

    private function display_cart_items($items) {
        Base_ActionBarCommon::add('delete', __('Clear cart'), $this->create_callback_href(array('Base_EpesiStoreCommon', 'empty_cart')));
        $gb = $this->init_module('Utils/GenericBrowser', null, 'cartlist');
        $gb = $this->GB_module($gb, $items, array($this, 'GB_row_additional_actions_cart'));
        $this->display_module($gb);
    }

    private function display_cart($items) {
        if (count($items) == 0) {
            print(__('Cart is empty!'));
            return;
        }

        $f = $this->init_module('Libs/QuickForm');
        $show_cart = true;
        if ($f->validate() && $f->exportValue('submited')) {
            $recent_items = $this->_cart_items_on_server($items);
            if ($recent_items == $items) {
                $show_cart = false;
                $this->form_buy_items($items);
            } else {
                print('<span style="color:red">' . __('Some modules has changed on server. This is updated list.') . '</span>');
                Base_EpesiStoreCommon::set_cart($recent_items);
                $items = $recent_items;
            }
        } 
        if ($show_cart) {
            $this->display_cart_items($items);
            Base_ActionBarCommon::add('folder', __('Buy'), $f->get_submit_form_href());
            $f->display();
        }
    }

    private function _cart_items_on_server($items) {
        $ids = array();
        foreach ($items as $r)
            $ids[] = $r['id'];
        return Base_EpesiStoreCommon::get_module_info($ids);
    }

    private function form_buy_items($items) {
        $this->navigation_button_orders();
        $this->navigation_button_your_modules();

        $server_response = $this->_order_submit($items);
        $this->client_messages();
        $this->display_order_submit_response($items, $server_response);
    }

    private function display_order_submit_response($ordered_items, $server_response) {
        foreach ($ordered_items as $r) {
            $info = & $server_response[$r['id']];
            $success = $info === true ? true : false;
            $message = is_string($info) ? ' (' . $info . ')' : "";
            print("{$r['name']} - <span style=\"color: " . ($success ? self::color_success : self::color_failure) . "\">" . $success ? __('Ordered') : __('Not ordered') . "$message</span><br/>");
        }
    }

    private function _order_submit($items) {
        $modules_ids = array();
        foreach ($items as $r)
            $modules_ids[] = $r['id'];
        Base_EpesiStoreCommon::empty_cart();
        return Base_EssClientCommon::server()->order_submit($modules_ids);
    }

    /**
     * Add module to cart
     * @param array $r modules data
     */
    public function cart_add_item($r) {
        $items = Base_EpesiStoreCommon::get_cart();
        // user module id to compare orders in cart
        if (!isset($r['id']))
            return;
        foreach ($items as $it) {
            if (isset($it['id']) && $it['id'] == $r['id'])
                return;
        }
        $items[$r['id']] = $r;
        Base_EpesiStoreCommon::set_cart($items);
    }

    /**
     * Remove module from cart
     * @param array $r modules data
     */
    public function cart_remove_item($r) {
        $items = Base_EpesiStoreCommon::get_cart();
        $key = array_search($r, $items);
        if ($key !== false) {
            unset($items[$key]);
            Base_EpesiStoreCommon::set_cart($items);
        }
    }

    public function form_orders() {
        $this->back_button();
        $this->navigation_button_your_modules();
        $this->payments_data_button();

        $orders = Base_EssClientCommon::server()->orders_list();
        $this->client_messages();
        $this->display_orders($orders);
    }

    private function display_orders($items) {
        if (count($items) == 0) {
            print(__('You don\'t have any orders'));
        } else {
            $gb = $this->init_module('Utils/GenericBrowser', null, 'orderslist');
            $this->GB_order($gb, $items);
            $this->display_module($gb);
        }
    }

    /**
     * Navigate to direct download of specified modules
     * @param array $module_licenses array of module licenses data arrays
     */
    public function download_modules($module_licenses) {
        Base_EpesiStoreCommon::empty_download_queue();
        foreach ($module_licenses as $m) {
            $this->download_queue_item($m);
        }
        $this->process_downloading();
    }

    /**
     * @param array $r order data
     */
    public function download_queue_item($r) {
        $q = Base_EpesiStoreCommon::get_download_queue();
        // use order id to compare is it in queue already
        if (!isset($r['id']))
            return;
        foreach ($q as $x) {
            if (isset($x['id']) && $x['id'] == $r['id'])
                return;
        }
        $q[] = $r;
        Base_EpesiStoreCommon::set_download_queue($q);
    }

    /**
     * @param array $r order data
     */
    public function download_dequeue_item($r) {
        $q = Base_EpesiStoreCommon::get_download_queue();
        $k = array_search($r, $q);
        if ($k !== false) {
            unset($q[$k]);
            Base_EpesiStoreCommon::set_download_queue($q);
        }
    }

    public function form_downloads() {
        $this->back_button();
        $downloads = Base_EpesiStoreCommon::get_download_queue();
        if (count($downloads)) {
            $this->navigation_button_process_downloading();
            $this->navigation_button_download_file_locally();
        }
        $this->display_downloads($downloads);
    }

    private function navigation_button_process_downloading() {
        Base_ActionBarCommon::add('clone', __('Proceed with download'), $this->create_callback_href(array($this, 'process_downloading')));
    }

    private function display_downloads($download_items) {
        if (count($download_items) == 0) {
            print(__('No items'));
            return;
        }
        Base_ActionBarCommon::add('delete', __('Clear list'), $this->create_callback_href(array('Base_EpesiStoreCommon', 'empty_download_queue')));
        $gb = $this->init_module('Utils/GenericBrowser', null, 'downloadslist');
        $gb = $this->GB_module_licenses($gb, $download_items, array($this, 'GB_row_additional_actions_downloads'));
        $this->display_module($gb);
    }

    public function download_as_zip($module_license) {
        $hash_or_url = Base_EssClientCommon::server()->download_prepare($module_license['id']);
        $this->client_messages();
        if (!$hash_or_url)
            return;
        $post_data = Base_EssClientCommon::server()->get_module_as_file_post_data_array($hash_or_url);
        $str = '<form method="post" id = "' . $hash_or_url . '" action="' . Base_EssClientCommon::get_server_url() . '">';
        foreach ($post_data as $key => $value) {
            $key = htmlspecialchars($key);
            $value = htmlspecialchars($value);
            $str .= '<input type="hidden" name="' . $key . '" value="' . $value . '"/>';
        }
        print($str);
        eval_js('$("' . $hash_or_url . '").submit();');
    }

    public function process_downloading() {
        $module_licenses = Base_EpesiStoreCommon::get_download_queue();
        $status = $this->_download_modules($module_licenses);
        foreach ($module_licenses as $ml) {
            if ($status[$ml['id']] === true) {
                $this->download_dequeue_item($ml);
            }
        }
        Base_SetupCommon::refresh_available_modules();
        $this->navigate('form_downloaded_status', array($module_licenses, $status));
    }

    private function _download_modules($module_licenses) {
        if (!count($module_licenses))
            return array();

        $status = array();
        foreach ($module_licenses as $ml) {
            $status[$ml['id']] = Base_EpesiStoreCommon::download_module($ml);
        }
        return $status;
    }

    public function form_downloaded_status($module_licenses, $status) {
        $this->client_messages();
        $times_back = count(Base_EpesiStoreCommon::get_download_queue()) == 0 ? 2 : 1;
        $this->back_button($times_back);
        foreach ($module_licenses as $ml) {
            $this->display_download_status_info($ml, $status[$ml['id']]);
        }
    }

    private function display_download_status_info($module_license, $status_info) {
        $module_info = Base_EpesiStoreCommon::get_module_info($module_license['module']);
        if ($status_info === true)
            $status_info = __('Success!');
        print("<b>{$module_info['name']}</b> - $status_info<br/>");
        $all_files = $module_info['files'];
        $modules = $this->_extract_modules_names($all_files);
        $this->_print_module_list($modules);
        $this->_print_other_files_list($all_files);
    }

    private function _extract_modules_names(& $all_files) {
        $modules = array();
        $module_prefix = 'modules/';
        $str_length = strlen($module_prefix);
        foreach ($all_files as $f) {
            if (is_dir($f) && substr_compare($f, $module_prefix, 0, $str_length) == 0) {
                $module_dir = substr($f, $str_length);
                // module path with slashes Test/Module
                $module_path = trim($module_dir, DIRECTORY_SEPARATOR);
                if (ModuleManager::exists(str_replace(DIRECTORY_SEPARATOR, '_', $module_path))) {
                    $modules[] = $module_path;
                }
            }
        }
        // remove each file under module path
        foreach ($modules as $mod) {
            $modxx = $module_prefix . $mod;
            foreach ($all_files as $k => $v) {
                if (strpos($v, $modxx) === 0) {
                    unset($all_files[$k]);
                }
            }
        }
        return $modules;
    }

    private function _print_module_list($modules) {
        if (!count($modules))
            return;

        print(__('Modules') . ':<br/>');
        foreach ($modules as $mod) {
            $this->display_module_entry($mod);
        }
    }

    private function display_module_entry($module) {
        $installed = (ModuleManager::is_installed($module) >= 0);
        $install_href = $installed ? '' : $this->create_callback_href(array($this, '_install_module'), array($module));
        $install_link = " - " . ($install_href ? "<a $install_href>" . __('Install module') . "</a>" : 'Module already installed');
        print(htmlspecialchars($module) . "$install_link<br/>");
    }

    public function _install_module($module) {
        $module = str_replace('/', '_', $module);
        ModuleManager::install($module);
    }

    private function _print_other_files_list($other_files) {
        if (!count($other_files))
            return;

        print(__('Other files:') . '<br/>');
        foreach ($other_files as $file) {
            print(htmlspecialchars($file) . '<br/>');
        }
    }

    private function payments_data_button() {
        $href = $this->create_callback_href(array($this, 'navigate'), array('payments_show_user_settings'));
        Base_ActionBarCommon::add('settings', __('Payment data'), $href, __('Here you can edit your default credentials used to payments'));
    }

    public function payments_show_user_settings() {
        $this->back_button();
        $module_to_show = $this->init_module('Base/User/Settings');
        $this->display_module($module_to_show, array('EPESI Store'));
    }

    public function form_payment_frame($order_id, $value, $curr_code, $modules = null) {
        $this->back_button();
        $this->payments_data_button();

        $payment_url = Base_EssClientCommon::get_payments_url();
        $description = $modules ? "Payment for: $modules" : "Order id: $order_id";
        
        $data = array(
            'action_url' => $payment_url,
            'record_id' => $order_id,
            'record_type' => 'ess_orders',
            'amount' => $value,
            'currency' => $curr_code,
            'description' => $description,
            'auto_process' => '1',
            'lang' => Base_LangCommon::get_lang_code(),
            'hide_page_banner' => '1'
        );

        $credentials = Base_EpesiStoreCommon::get_payment_credentials();
        foreach (array('first_name', 'last_name', 'address_1', 'address_2',
            'city', 'postal_code', 'country', 'email', 'phone') as $key) {
            if (isset($credentials[$key]))
                $data[$key] = $credentials[$key];
        }
        
        print('<iframe name="payments" width="800px" style="border: none;" height="600px"></iframe>');
        $html = create_html_form($form_name, $payment_url, $data, 'payments');
        print $html;
        $open_js = "document.{$form_name}.submit()";
        eval_js($open_js);
    }

    protected function GB_module(Utils_GenericBrowser $gb, array $items, $row_additional_actions_callback) {
        return $this->GB_generic($gb, $items, $this->banned_columns_module, array($this, 'GB_row_data_transform_module'), $row_additional_actions_callback);
    }

    protected function GB_order(Utils_GenericBrowser $gb, array $items, $row_additional_actions_callback = null) {
        return $this->GB_generic($gb, $items, $this->banned_columns_order, array($this, 'GB_row_data_transform_order'), $row_additional_actions_callback);
    }

    protected function GB_module_licenses(Utils_GenericBrowser $gb, array $items, $row_additional_actions_callback) {
        return $this->GB_generic($gb, $items, array('installation_id', 'id'), array($this, 'GB_row_data_transform_module_licenses'), $row_additional_actions_callback);
    }

    protected function GB_row_data_transform_order(array $data) {
        static $module_licenses = null;
        if ($module_licenses === null) {
            $module_licenses = Base_EssClientCommon::server()->module_licenses_list();
        }
        // change module ids to names
        foreach ($data['modules'] as & $m) { // $m is module_license
            $mod_id = isset($module_licenses[$m]) ? $module_licenses[$m]['module'] : null;
            $m = __('[license not found]');
            if($mod_id !== null) {
                $mi = Base_EpesiStoreCommon::get_module_info($mod_id);
                $m = $mi['name'];
            }
        }
        $data['modules'] = implode(', ', $data['modules']);
        // handle prices
        $total = array();
        $to_pay = array();
        foreach ($data['price'] as $curr_code => $amount) {
            $total[] = $amount['display_total'];
            if ($amount['to_pay']) {
                $href = $this->href_navigate('form_payment_frame', $data['id'], $amount['to_pay'], $curr_code, $data['modules']);
                $pay_button = "<button $href>Pay {$amount['display_to_pay']}</button>";
                $to_pay[] = $pay_button;
            } else {
                $to_pay[] = __('Paid');
            }
        }
        unset($data['price']);
        $data['total_price'] = implode('<br/>', $total);
        $data['to_pay'] = implode('<br/>', $to_pay);
        return $data;
    }

    protected function GB_row_data_transform_module(array $data) {
        if (isset($data['active']))
            unset($data['active']);
        if (isset($data['icon_url']) && $data['icon_url'])
            $data['name'] = "<img style=\"max-height: 30px; float: right\" src=\"{$data['icon_url']}\" alt=\"{$data['name']} icon\"/>" . $data['name'];
        unset($data['icon_url']);

        if (isset($data['description_url']) && $data['description_url'])
            $data['description'] = "<a target=\"_blank\" href=\"{$data['description_url']}\">{$data['description']}</a>";
        unset($data['description_url']);
        
        $required_modules = & $data['required_modules'];
        unset($data['needed_modules']);
		if (!is_array($required_modules)) $required_modules = explode(', ',$required_modules);
        if (isset($required_modules)) {
            foreach($required_modules as $k => & $m) {
                $mi = Base_EpesiStoreCommon::get_module_info($m);
                if($mi)
                    $m = "{$mi['repository']}::{$mi['name']}";
                else
                    $m = '(' . __('Unrecognized module') . ')';
            }
            $required_modules = implode(', ', $required_modules);
        }
        if (!isset($data['bought']))
            $data['bought'] = 0;
        if (!isset($data['paid']))
            $data['paid'] = 0;

        return $data;
    }

    private function module_info_tooltip($module_id) {
        $mi = Base_EpesiStoreCommon::get_module_info($module_id);
        $tooltip = Utils_TooltipCommon::ajax_open_tag_attrs(array('Base_EpesiStoreCommon', 'module_format_info'), array($mi));
        return "<a $tooltip>{$mi['name']}</a>";
    }

    private function _module_is_active($module_id) {
        $mi = Base_EpesiStoreCommon::get_module_info($module_id);
        return $mi['active'];
    }

    protected function GB_row_data_transform_module_licenses(array $data) {
        // module name
        if (isset($data['module']))
            $data['module'] = $this->module_info_tooltip($data['module']);
        // paid
        if (isset($data['paid'])) {
            if (!$data['paid']) {
                $this->navigation_button_orders();
            }
            $data['paid'] = $data['paid'] ? __('Yes') : __('No - Go to orders to pay');
        }
        // active
        if (isset($data['active'])) {
            $text = $data['active'] ? __('Yes') : __('No');
            $tip = $data['active'] ? __('You can download newer version if it\'s available') : __('You cannot download newer version');
            $data['active'] = "<a " . Utils_TooltipCommon::open_tag_attrs($tip) . ">$text</a>";
        }
        return $data;
    }

    protected function GB_row_additional_actions_store($row, $data) {
        $row->add_action($this->create_callback_href(array($this, 'cart_add_item'), array($data)), '+', __('Add to cart'));
    }

    protected function GB_row_additional_actions_cart($row, $data) {
        $row->add_action($this->create_callback_href(array($this, 'cart_remove_item'), array($data)), 'delete', __('Remove from cart'));
    }

    protected function GB_row_additional_actions_your_modules($row, $data) {
        if ($data['paid'] && $data['active'] && $this->_module_is_active($data['module'])
                && $this->_module_license_needs_download_or_update($data))
            $row->add_action($this->create_callback_href(array($this, 'download_queue_item'), array($data)), '+', __('Queue download'));
    }

    protected function GB_row_additional_actions_downloads($row, $data) {
        $row->add_action($this->create_callback_href(array($this, 'download_dequeue_item'), array($data)), 'delete');
        $row->add_action($this->create_callback_href(array($this, 'download_as_zip'), array($data)), 'append data', 'Download as zip file');
    }

    protected function GB_generic(Utils_GenericBrowser $gb, array $items, $banned_columns = array(), $row_data_transform_callback = null, $row_additional_actions_callback = null) {
        if (count($items)) {
            // add column headers
            $first_el = reset($items);
            if ($row_data_transform_callback != null && is_callable($row_data_transform_callback))
                $first_el = call_user_func($row_data_transform_callback, $first_el);
            $columns = array();
            foreach ($first_el as $k => $v) {
                if (in_array($k, $banned_columns))
                    continue;
                $columns[] = array('name' => ucwords(str_replace('_', ' ', $k)));
            }
            $gb->set_table_columns($columns);
            // add elements
            foreach ($items as $r) {
                $v = array();
                $r_modified = $r;
                if ($row_data_transform_callback != null && is_callable($row_data_transform_callback))
                    $r_modified = call_user_func($row_data_transform_callback, $r);
                foreach ($r_modified as $k => $x) {
                    if (in_array($k, $banned_columns))
                        continue;
                    $v[] = $x;
                }
                /* @var $row Utils_GenericBrowser_Row_Object */
                $row = $gb->get_new_row();
                $row->add_data_array($v);
                if ($row_additional_actions_callback != null && is_callable($row_additional_actions_callback))
                    call_user_func($row_additional_actions_callback, $row, $r);
            }
        }
        return $gb;
    }

    private function href_navigate($func) {
        $args = func_get_args();
        $func = array_shift($args);
        if (!$func)
            throw new ErrorException("Function to navigate not defined.");
        return $this->create_callback_href(array($this, 'navigate'), array($func, $args));
    }

    public function navigate($func, $params = array()) {
        $x = ModuleManager::get_instance('/Base_Box|0');
        if (!$x)
            trigger_error('There is no base box module instance', E_USER_ERROR);
        $x->push_main($this->get_type(), $func, $params);
        return false;
    }

    public function pop_main($i = 1) {
        $x = ModuleManager::get_instance('/Base_Box|0');
        if (!$x)
            trigger_error('There is no base box module instance', E_USER_ERROR);
        $x->pop_main($i);
    }

    public function back_button($i = 1) {
        $x = 0;
        while ($this->is_back())
            $x++;
        if ($x > 0)
            return $this->pop_main($x);
        Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href($i));
    }

}

?>