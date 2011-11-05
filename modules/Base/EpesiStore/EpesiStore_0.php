<?php

/**
 * 
 * @author Adam Bukowski <abukowski@telaxus.com>
 * @copyright Copyright &copy; 2011, Telaxus LLC
 * @license MIT
 * @version 0.1
 * @package epesi-Base
 * @subpackage EpesiStore
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_EpesiStore extends Module {
    // actionbar buttons
    const button_cart = 'Cart';
    const button_clear_list = 'Clear list';
    const button_downloaded_modules = 'Check updates';
    const button_downloads = 'Downloads';
    const button_instant_download_modules = 'Instant download all new';
    const button_my_modules = 'My modules';
    const button_orders = 'Orders';
    const button_proceed_download = 'Proceed download';
    const button_redownload = 'Re-Download all modules';
    const button_update_all_modules = 'Update all';
    // text in forms
    const text_refresh_info = 'Data is stored until close or refresh of browser\'s Epesi window or tab';
    const text_modules_changed_on_server = 'Some modules has changed on server. This is updated list.';
    const text_not_registered = 'Some error occured. Probably you are not registered client. Go to Admin / Register Epesi';
    const text_empty = 'Empty';
    const text_cart_is_empty = 'Cart is empty!';
    const text_price_summary = 'Total price';
    const text_buy = 'Buy!';
    const text_my_modules_list_empty = 'You haven\'t bought any modules';
    const text_order_success = 'Ordered';
    const text_order_failure = 'Not ordered';
    const text_orders_list_empty = 'You don\'t have any orders';
    const text_download_process_success = 'Download process succeed!';
    const text_download_process_new_modules = 'New modules:';
    const text_download_process_new_other_files = 'Other files downloaded:';
    const text_redownload_modules_header = 'Re-Download modules';
    const text_redownload_modules_confirm_question = 'Are you sure?';
    const text_redownload_modules_count_extracted = 'modules extracted from files';
    const text_redownload_modules_count_downloaded = 'modules downloaded from server';
    const text_downloaded_modules_header = '<h3>This is the list of downloaded modules:</h3>';
    // colors
    const color_success = 'green';
    const color_failure = 'gray';

    protected $banned_columns_module = array('id', 'owner_id', 'path', 'files', 'active');
    protected $banned_columns_order = array('id', 'installation_id');

    public function body() {
        
    }

    public function admin() {
        if (!Base_AclCommon::i_am_sa())
            return;
        if ($this->is_back()) {
            return $this->parent->reset();
        }
        Base_ActionBarCommon::add('back', 'Back', $this->create_back_href());

        if (Base_EssClientCommon::get_license_key() == "") {
            // push main
            $m = $this->init_module('Base/EssClient');
            $this->display_module($m, null, 'register_form');
        } else {
            $this->cart_button();
            Base_ActionBarCommon::add('search', $this->t(self::button_downloaded_modules), $this->create_callback_href(array($this, 'navigate'), array('downloaded_modules_form')));
            Base_ActionBarCommon::add('view', $this->t(self::button_orders), $this->create_callback_href(array($this, 'navigate'), array('orders_form')));
            Base_ActionBarCommon::add('view', $this->t(self::button_my_modules), $this->create_callback_href(array($this, 'navigate'), array('my_modules_form')));
            $this->store_form();
        }
    }

    public function store_form() {
        $total = Base_EpesiStoreCommon::modules_total_amount();
        if ($total === false) {
            print($this->t(self::text_not_registered));
        }
        if ($total) {
            /* @var $gb Utils_GenericBrowser */
            $gb = $this->init_module('Utils/GenericBrowser', null, 'moduleslist');
            $x = $gb->get_limit($total);
            // fetch data
            $t = Base_EpesiStoreCommon::modules_list($x['offset'], $x['numrows']);
            $gb = $this->GB_module($gb, $t, array($this, 'GB_row_additional_actions_store'));
            $this->display_module($gb);
        }
    }

    public function cart_button() {
        $cart = Base_EpesiStoreCommon::get_cart();
        $amount = count($cart);
        if ($amount == 0)
            $amount = $this->t(self::text_empty);
        $label = $this->t(self::button_cart);
        Base_ActionBarCommon::add('folder', "$label ($amount)", $this->create_callback_href(array($this, 'navigate'), array('cart_form')), $this->t(self::text_refresh_info));
    }

    public function cart_form() {
        $this->back_button();

        $items = Base_EpesiStoreCommon::get_cart();
        if (count($items) == 0) {
            print($this->t(self::text_cart_is_empty));
            return;
        }
        // sum total price
        $total_price = 0;
        foreach ($items as $x) {
            $total_price += $x['price'];
        }
        // buy form
        $f = $this->init_module('Libs/QuickForm');
        $f->addElement('static', 'price', null, $this->t(self::text_price_summary) . ': ' . $total_price);
        $f->addElement('submit', 'submit', $this->t(self::text_buy));
        // if buy clicked check for any changes on server
        $buy = false;
        $changed = false;
        if ($f->validate() && $f->exportValue('submited')) {
            $items2 = array();
            foreach ($items as $r) {
                $items2[] = Base_EpesiStoreCommon::get_module_info($r['id'], true);
            }
            if ($items == $items2) {
                $buy = true;
            } else {
                // mark that something has changed on server
                $changed = true;
                $items = $items2;
                Base_EpesiStoreCommon::set_cart($items2);
            }
        }
        // everything matches on server - buy
        if ($buy) {
            $modules = array();
            $module_names = array();
            foreach ($items as $r) {
                $modules[] = $r['id'];
                $module_names[$r['id']] = $r['name'];
            }
            $ret = Base_EssClientCommon::server()->order_submit($modules);
            foreach ($ret as $id => $info) {
                $success = $info === true ? true : false;
                $message = is_string($info) ? ' (' . $this->t($info) . ')' : "";
                print("$module_names[$id] - <span style=\"color: " . ($success ? self::color_success : self::color_failure) . "\">" . $this->t($success ? self::text_order_success : self::text_order_failure) . "$message</span><br/>");
            }
            Base_EpesiStoreCommon::empty_cart();
        } else {
            $gb = $this->init_module('Utils/GenericBrowser', null, 'cartlist');
            $gb = $this->GB_module($gb, $items, array($this, 'GB_row_additional_actions_cart'));
            if ($changed) {
                print('<span style="color:red">' . $this->t(self::text_modules_changed_on_server) . '</span>');
            }
            $this->display_module($gb);
            $f->display();
        }
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
        $items[] = $r;
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

    public function orders_form() {
        $this->back_button();

        $orders = Base_EssClientCommon::server()->orders_list();
        if (count($orders) == 0) {
            print($this->t(self::text_orders_list_empty));
            return;
        }

        $gb = $this->init_module('Utils/GenericBrowser', null, 'orderslist');
        $this->GB_order($gb, $orders);
        $this->display_module($gb);
    }

    public function my_modules_form() {
        $this->back_button();
        $this->download_button();

        $modules = Base_EssClientCommon::server()->bought_modules_list();
        if (count($modules) == 0) {
            print($this->t(self::text_my_modules_list_empty));
            return;
        }
        $dd = array();
        $to_download = array();
        foreach (Base_EpesiStoreCommon::get_downloaded_modules() as $d) {
            $dd[$d['order_id']] = true;
        }
        foreach ($modules as & $m) {
            $m['downloaded'] = isset($dd[$m['id']]);
            if (!isset($dd[$m['id']]))
                $to_download[] = $m;
        }
        if (count($to_download)) {
            Base_ActionBarCommon::add('favorites', self::button_instant_download_modules, $this->create_callback_href(array($this, 'download_modules'), array($to_download)));
        }
        $gb = $this->init_module('Utils/GenericBrowser', null, 'mymoduleslist');
        $this->GB_bought_modules($gb, $modules, array($this, 'GB_row_additional_actions_bought_modules'));
        $this->display_module($gb);
    }

    /**
     * Navigate to direct download of specified modules
     * @param array $modules array of bought modules data arrays
     */
    public function download_modules($modules) {
        Base_EpesiStoreCommon::empty_download_queue();
        foreach ($modules as $m) {
            $this->download_queue_item($m);
        }
        $this->navigate('download_process');
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

    /**
     * @param boolean $display_empty set to false to not display button when list is empty.
     */
    public function download_button($display_empty = true) {
        $download = $this->t(self::button_downloads);
        $count = count(Base_EpesiStoreCommon::get_download_queue());

        if ($display_empty || $count) {
            if ($count == 0)
                $count = $this->t('Empty');
            Base_ActionBarCommon::add('clone', "$download ($count)", $this->create_callback_href(array($this, 'navigate'), array('download_form')));
        }
    }

    public function download_form() {
        $this->back_button();
        $downloads = Base_EpesiStoreCommon::get_download_queue();
        if (count($downloads) == 0) {
            print($this->t('No items'));
            return;
        } else {
            Base_ActionBarCommon::add('delete', self::button_clear_list, $this->create_callback_href(array('Base_EpesiStoreCommon', 'empty_download_queue')));
            Base_ActionBarCommon::add('clone', self::button_proceed_download, $this->create_callback_href(array($this, 'navigate'), array('download_process')));
            $gb = $this->init_module('Utils/GenericBrowser', null, 'downloadslist');
            $gb = $this->GB_bought_modules($gb, $downloads, array($this, 'GB_row_additional_actions_downloads'));
            $this->display_module($gb);
        }
    }

    /**
     * Process download of items from download queue and empty queue.
     */
    public function download_process() {
        $this->back_button();
        $modules = Base_EpesiStoreCommon::get_download_queue();
        if (!count($modules)) {
            return;
        }
        $modules_ids = array();
        foreach ($modules as $o) {
            $modules_ids[] = $o['id'];
        }
        // download
        $file = null;
        $ret = Base_EpesiStoreCommon::download_package($modules_ids, $file);
        if (is_string($ret)) {
            print($this->t($ret));
            return;
        }
        if ($file === null)
            return;
        // extract
        $ret = Base_EpesiStoreCommon::extract_package($file);
        if (is_string($ret)) {
            print($this->t($ret));
            return;
        }
        // show download status
        $this->back_button(2);
        print($this->t(self::text_download_process_success) . '<br/><br/>');
        // list all files
        $all_files = array();
        foreach ($modules as $d) {
            $mod = Base_EpesiStoreCommon::get_module_info($d['module']);
            $all_files = array_merge($all_files, $mod['files']);
            // store info about module in db
            Base_EpesiStoreCommon::add_downloaded_module($d['module'], $mod['version'], $d['id'], $file);
        }
        // check for epesi modules
        $modules = array();
        $string = 'modules/';
        $str_length = strlen($string);
        foreach ($all_files as $f) {
            if (is_dir($f) && substr_compare($f, $string, 0, $str_length) == 0) {
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
            $modxx = 'modules/' . $mod;
            foreach ($all_files as $k => $v) {
                if (strstr($v, $modxx)) {
                    unset($all_files[$k]);
                }
            }
        }
        // print info
        if (count($modules)) {
            print($this->t(self::text_download_process_new_modules) . '<br/>');
            print(implode('<br/>', $modules));
            print('<br/><br/>');
        }
        if (count($all_files)) {
            print($this->t(self::text_download_process_new_other_files) . '<br/>');
            print(implode('<br/>', $all_files));
        }
        Base_EpesiStoreCommon::empty_download_queue();
    }

    /**
     * Extract modules from archives already downloaded and download modules if needed.
     * Show status message after.
     */
    public function redownload_modules() {
        $ret = Base_EpesiStoreCommon::download_all_downloaded();
        // print status
        print('<h3>' . $this->t(self::text_redownload_modules_header) . '</h3>');
        if (count($ret['old']))
            print('<p>' . count($ret['old']) . ' ' . $this->t(self::text_redownload_modules_count_extracted) . '</p>');
        if (count($ret['new']))
            print('<p>' . count($ret['new']) . ' ' . $this->t(self::text_redownload_modules_count_downloaded) . '</p>');
        if (count($ret['error'])) {
            print($this->t('<p>Errors:<br/>'));
            foreach ($ret['error'] as $msg => $modules) {
                print($msg . ' - ' . count($modules) . 'modules<br/>');
            }
        }
    }

    public function downloaded_modules_form() {
        $this->back_button();
        $this->download_button(false);
        Base_ActionBarCommon::add('clone', self::button_redownload, $this->create_confirm_callback_href($this->t(self::text_redownload_modules_confirm_question), array($this, 'redownload_modules')));
        // get bought modules list
        $bought_modules = Base_EssClientCommon::server()->bought_modules_list();
        // get downloaded modules list
        $modules = Base_EpesiStoreCommon::get_downloaded_modules();
        $items = array();
        foreach ($modules as $m) {
            $mod = Base_EpesiStoreCommon::get_module_info($m['module_id']);
            $tooltip = Utils_TooltipCommon::ajax_open_tag_attrs(array('Base_EpesiStoreCommon', 'module_format_info'), array($mod));
            $it = array(
                'bought_module' => isset($bought_modules[$m['order_id']]) && $bought_modules[$m['order_id']]['active'] && $bought_modules[$m['order_id']]['paid'] ? $bought_modules[$m['order_id']] : null,
                'module' => "<a $tooltip>{$mod['repository']} :: {$mod['name']}</a>",
                'installed_version' => $m['version'],
                'recent_version' => $mod['version']);
            if (($it['installed_version'] < $it['recent_version']) && $it['bought_module'] != null) {
                $to_update[] = $it['bought_module'];
            }
            $items[] = $it;
        }
        if (isset($to_update) && count($to_update)) {
            Base_ActionBarCommon::add('all', $this->t(self::button_update_all_modules), $this->create_callback_href(array($this, 'update_all_modules'), array($to_update)));
        }
        print($this->t(self::text_downloaded_modules_header));
        $gb = $this->init_module('Utils/GenericBrowser', null, 'downloadedmoduleslist');
        $gb = $this->GB_generic($gb, $items, array('bought_module'), null, array($this, 'GB_row_additional_actions_downloaded_modules'));
        $this->display_module($gb);
    }

    public function update_all_modules($bought_modules) {
        foreach ($bought_modules as $m) {
            $this->download_queue_item($m);
        }
        $this->navigate('download_form');
    }

    protected function GB_module(Utils_GenericBrowser $gb, array $items, $row_additional_actions_callback) {
        return $this->GB_generic($gb, $items, $this->banned_columns_module, array($this, 'GB_row_data_transform_module'), $row_additional_actions_callback);
    }

    protected function GB_order(Utils_GenericBrowser $gb, array $items, $row_additional_actions_callback = null) {
        return $this->GB_generic($gb, $items, $this->banned_columns_order, null, $row_additional_actions_callback);
    }

    protected function GB_bought_modules(Utils_GenericBrowser $gb, array $items, $row_additional_actions_callback) {
        return $this->GB_generic($gb, $items, $this->banned_columns_order, array($this, 'GB_row_data_transform_bought_modules'), $row_additional_actions_callback);
    }

    protected function GB_row_data_transform_module(array $data) {
        if ($data['price'] == 0)
            $data['price'] = $this->t('Free');
        if (isset($data['active']))
            unset($data['active']);
        return $data;
    }

    protected function GB_row_data_transform_bought_modules(array $data) {
        // module name
        if (isset($data['module'])) {
            $mi = Base_EpesiStoreCommon::get_module_info($data['module']);
            $data['module'] = $mi['repository'] . ' :: ' . $mi['name'];
        }
        // paid
        if (isset($data['paid']))
            $data['paid'] = $this->t($data['paid'] ? 'Yes' : 'No');
        // active
        if (isset($data['active']))
            $data['active'] = $this->t($data['active'] ? 'Yes' : 'No');
        // downloaded
        if (isset($data['downloaded']))
            $data['downloaded'] = $this->t($data['downloaded'] ? 'Yes' : 'No');
        // module info
//        $module = Base_EpesiStoreCommon::get_module_info($data['module_id']);
//        $tooltip = Utils_TooltipCommon::ajax_open_tag_attrs(array('Base_EpesiStoreCommon', 'module_format_info'), array($module));
//        $data['module'] = "<a $tooltip>{$module['name']}</a>";
        unset($data['module_id']);

        return $data;
    }

    protected function GB_row_additional_actions_store($row, $data) {
        $row->add_action($this->create_callback_href(array($this, 'cart_add_item'), array($data)), '+', $this->t('Add to cart'));
    }

    protected function GB_row_additional_actions_cart($row, $data) {
        $row->add_action($this->create_callback_href(array($this, 'cart_remove_item'), array($data)), 'delete', $this->t('Remove from cart'));
    }

    protected function GB_row_additional_actions_bought_modules($row, $data) {
        if ($data['paid'] && $data['active'] && !$data['downloaded'])
            $row->add_action($this->create_callback_href(array($this, 'download_queue_item'), array($data)), '+', $this->t('Queue download'));
    }

    protected function GB_row_additional_actions_downloads($row, $data) {
        $row->add_action($this->create_callback_href(array($this, 'download_dequeue_item'), array($data)), 'delete');
    }

    protected function GB_row_additional_actions_downloaded_modules($row, $data) {
        if ($data['installed_version'] < $data['recent_version'])
            $row->add_action($this->create_callback_href(array($this, 'download_queue_item'), array($data['bought_module'])), '+', $this->t('Queue download'));
    }

    protected function GB_generic(Utils_GenericBrowser $gb, array $items, $banned_columns = array(), $row_data_transform_callback = null, $row_additional_actions_callback = null) {
        if (count($items)) {
            // add column headers
            $first_el = reset($items);
            if ($row_data_transform_callback != null)
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
                if ($row_data_transform_callback != null)
                    $r_modified = call_user_func($row_data_transform_callback, $r);
                foreach ($r_modified as $k => $x) {
                    if (in_array($k, $banned_columns))
                        continue;
                    $v[] = $x;
                }
                /* @var $row Utils_GenericBrowser_Row_Object */
                $row = $gb->get_new_row();
                $row->add_data_array($v);
                if ($row_additional_actions_callback != null)
                    call_user_func($row_additional_actions_callback, $row, $r);
            }
        }
        return $gb;
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
        Base_ActionBarCommon::add('back', 'Back', $this->create_back_href($i));
    }

}

?>