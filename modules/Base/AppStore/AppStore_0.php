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

    protected $banned_columns = array();

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
            $this->store_form();
        }
    }

    public function store_form() {
        $total = Base_EssClientCommon::server()->get_list_of_modules_total_amount();
        if ($total) {
            /* @var $gb Utils_GenericBrowser */
            $gb = $this->init_module('Utils/GenericBrowser', null, 'moduleslist');
            $x = $gb->get_limit($total);
            // fetch data
            $t = Base_EssClientCommon::server()->get_list_of_modules($x['offset'], $x['numrows']);
            if ($t) {
                $first = true;
                foreach ($t as $r) {
                    if ($first) {
                        /* set table columns names */
                        $columns = array();
                        foreach ($r as $k => $v) {
                            if (array_key_exists($k, $this->banned_columns))
                                continue;
                            $columns[] = array('name' => ucwords($k));
                        }
                        $gb->set_table_columns($columns);
                        $first = false;
                    }
                    $v = array();
                    foreach ($r as $k => $x) {
                        if (array_key_exists($k, $this->banned_columns))
                            continue;
                        $v[] = $x;
                    }
                    /* @var $row Utils_GenericBrowser_Row_Object */
                    $row = $gb->get_new_row();
                    $row->add_data_array($v);
                }
            }
            $this->display_module($gb);
        }
    }

}

?>