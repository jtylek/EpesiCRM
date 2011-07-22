<?php

/**
 * 
 * @author pbukowski@telaxus.com
 * @copyright Telaxus LLC
 * @license MIT
 * @version 0.1
 * @package epesi-Base
 * @subpackage AppStore
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_AppStore extends Module {
    const refresh_info_text = 'Data is stored until close or refresh of browser\'s Epesi window or tab';

    protected $banned_columns_module = array('id', 'owner_id', 'path');
    protected $banned_columns_order = array('id', 'installation_id');

    public function body() {

    }

    public function admin() {
        if ($this->is_back()) {
            return $this->parent->reset();
        }
        Base_ActionBarCommon::add('back', 'Back', $this->create_back_href());

        if (Base_EssClientCommon::get_license_key() == "") {
            // push main
            $m = $this->init_module('Base/EssClient');
            $this->display_module($m, null, 'register');
        } else {
            $this->cart_button();
            Base_ActionBarCommon::add('history', $this->t('Orders'), $this->create_callback_href(array($this, 'navigate'), array('orders_form')));
            $this->store_form();
        }
    }

    public function store_form() {
        $total = Base_AppStoreCommon::modules_total_amount();
        if ($total) {
            /* @var $gb Utils_GenericBrowser */
            $gb = $this->init_module('Utils/GenericBrowser', null, 'moduleslist');
            $x = $gb->get_limit($total);
            // fetch data
            $t = Base_AppStoreCommon::modules_list($x['offset'], $x['numrows']);
//            $t = Base_EssClientCommon::server()->modules_list($x['offset'], $x['numrows']);
            $gb = $this->GB_module($gb, $t, array($this, 'GB_row_additional_actions_store'));
            $this->display_module($gb);
        }
    }

    public function cart_button() {
        $cart = Base_AppStoreCommon::get_cart();
        $amount = count($cart);
        if ($amount == 0)
            $amount = $this->t('Empty');
        $label = $this->t('Cart');
        Base_ActionBarCommon::add('folder', "$label ($amount)", $this->create_callback_href(array($this, 'navigate'), array('cart_form')), $this->t(self::refresh_info_text));
    }

    public function cart_form() {
        $this->back_button();

        $items = Base_AppStoreCommon::get_cart();
        $total_price = 0;
        if (count($items) == 0) {
            print($this->t('Cart is empty!'));
            return;
        }
        $gb = $this->init_module('Utils/GenericBrowser', null, 'cartlist');
        $gb = $this->GB_module($gb, $items, array($this, 'GB_row_additional_actions_cart'));
        foreach ($items as $x) {
            $total_price += $x['price'];
        }

        // handle buy
        $f = $this->init_module('Libs/QuickForm');
        $f->addElement('static', 'price', null, $this->t('Total price') . ': ' . $total_price);
        $f->addElement('submit', 'submit', $this->t('Buy!'));
        if ($f->validate() && $f->exportValue('submited')) {
            $modules = array();
            $module_names = array();
            foreach ($items as $r) {
                $modules[] = $r['id'];
                $module_names[$r['id']] = $r['name'];
            }
            $ret = Base_EssClientCommon::server()->order_submit($modules);
            foreach ($ret as $id => $success) {
                print("$module_names[$id] - <span style=\"color: " . ($success ? "green" : "gray") . "\">" . $this->t($success ? 'Ordered' : 'Not ordered') . "</span><br/>");
            }
            Base_AppStoreCommon::empty_cart();
        } else {
            $this->display_module($gb);
            $f->display();
        }
    }

    public function cart_add_item($r) {
        $items = Base_AppStoreCommon::get_cart();
        $items[] = $r;
        Base_AppStoreCommon::set_cart($items);
    }

    public function cart_remove_item($r) {
        $items = Base_AppStoreCommon::get_cart();
        $key = array_search($r, $items);
        if ($key !== false) {
            unset($items[$key]);
            Base_AppStoreCommon::set_cart($items);
        }
    }

    public function orders_form() {
        $this->back_button();
        $this->download_button();

        $orders = Base_EssClientCommon::server()->orders_list();
        if (count($orders) == 0) {
            print($this->t('You don\'t have any orders'));
            return;
        }

        $gb = $this->init_module('Utils/GenericBrowser', null, 'orderslist');
        $this->GB_order($gb, $orders, array($this, 'GB_row_additional_actions_orders'));
        $this->display_module($gb);
    }

    public function download_queue_item($r) {
        $q = Base_AppStoreCommon::get_download_queue();
        $q[] = $r;
        Base_AppStoreCommon::set_download_queue($q);
    }

    public function download_dequeue_item($r) {
        $q = Base_AppStoreCommon::get_download_queue();
        $k = array_search($r, $q);
        if ($k !== false) {
            unset($q[$k]);
            Base_AppStoreCommon::set_download_queue($q);
        }
    }

    public function download_button() {
        $download = $this->t('Downloads');
        $count = count(Base_AppStoreCommon::get_download_queue());
        if ($count == 0)
            $count = $this->t('Empty');
        Base_ActionBarCommon::add('clone', "$download ($count)", $this->create_callback_href(array($this, 'navigate'), array('download_form')));
    }

    public function download_form() {
        $this->back_button();
        Base_ActionBarCommon::add('delete', 'Clear list', $this->create_callback_href(array('Base_AppStoreCommon', 'empty_download_queue')));
        $downloads = Base_AppStoreCommon::get_download_queue();
        if (count($downloads) == 0) {
            print($this->t('No items'));
            return;
        }

        $gb = $this->init_module('Utils/GenericBrowser', null, 'downloadslist');
        $gb = $this->GB_order($gb, $downloads, array($this, 'GB_row_additional_actions_downloads'));
        $this->display_module($gb);
    }

    protected function GB_module(Utils_GenericBrowser $gb, array $items, $row_additional_actions_callback) {
        return $this->GB_generic($gb, $items, $this->banned_columns_module, array($this, 'GB_row_data_transform_module'), $row_additional_actions_callback);
    }

    protected function GB_order(Utils_GenericBrowser $gb, array $items, $row_additional_actions_callback) {
        return $this->GB_generic($gb, $items, $this->banned_columns_order, array($this, 'GB_row_data_transform_order'), $row_additional_actions_callback);
    }

    protected function GB_row_data_transform_module(array $data) {
        if ($data['price'] == 0)
            $data['price'] = $this->t('Free');

        return $data;
    }

    protected function GB_row_data_transform_order(array $data) {
        // price
        if ($data['pay_price'] == 0)
            $data['pay_price'] = $this->t('Free');
        // paid
        $data['paid'] = $this->t($data['paid'] ? 'Yes' : 'No');
        // module info
        $module = Base_EssClientCommon::server()->module_get_info($data['module_id']);
        $tooltip = Utils_TooltipCommon::ajax_open_tag_attrs(array('Base_AppStoreCommon', 'module_format_info'), array($module));
        $data['module'] = "<a $tooltip>{$module['name']}</a>";
        unset($data['module_id']);

        return $data;
    }

    protected function GB_row_additional_actions_store($row, $data) {
        $row->add_action($this->create_callback_href(array($this, 'cart_add_item'), array($data)), $this->t('Add to cart'));
    }

    protected function GB_row_additional_actions_cart($row, $data) {
        $row->add_action($this->create_callback_href(array($this, 'cart_remove_item'), array($data)), $this->t('Remove from cart'));
    }

    protected function GB_row_additional_actions_orders($row, $data) {
        if ($data['paid'])
            $row->add_action($this->create_callback_href(array($this, 'download_queue_item'), array($data)), $this->t('Queue download'));
    }

    protected function GB_row_additional_actions_downloads($row, $data) {
        $row->add_action($this->create_callback_href(array($this, 'download_dequeue_item'), array($data)), 'delete');
    }

    protected function GB_generic(Utils_GenericBrowser $gb, array $items, $banned_columns, $row_data_transform_callback, $row_additional_actions_callback) {
        if (count($items)) {
            // add column headers
            $first_el = call_user_func($row_data_transform_callback, reset($items));
            $columns = array();
            foreach ($first_el as $k => $v) {
                if (in_array($k, $banned_columns))
                    continue;
                $columns[] = array('name' => ucwords($k));
            }
            $gb->set_table_columns($columns);
            // add elements
            foreach ($items as $r) {
                $v = array();
                $r_modified = call_user_func($row_data_transform_callback, $r);
                foreach ($r_modified as $k => $x) {
                    if (in_array($k, $banned_columns))
                        continue;
                    $v[] = $x;
                }
                /* @var $row Utils_GenericBrowser_Row_Object */
                $row = $gb->get_new_row();
                $row->add_data_array($v);
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

    public function pop_main() {
        $x = ModuleManager::get_instance('/Base_Box|0');
        if (!$x)
            trigger_error('There is no base box module instance', E_USER_ERROR);
        $x->pop_main();
    }

    public function back_button() {
        if ($this->is_back()) {
            return $this->pop_main();
        }
        Base_ActionBarCommon::add('back', 'Back', $this->create_back_href());
    }

}

?>