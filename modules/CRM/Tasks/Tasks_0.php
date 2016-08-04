<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage tasks
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Tasks extends Module
{
    private $rb = null;

    public function body()
    {
        $this->help('Tasks Help', 'main');

        $this->rb = $this->init_module(Utils_RecordBrowser::module_name(), 'task', 'task');
        $me = CRM_ContactsCommon::get_my_record();
        CRM_CommonCommon::status_filter($this->rb);
        $this->rb->set_filters_defaults(array('employees' => $this->rb->crm_perspective_default(), 'status' => '__NO_CLOSED__'));
        $this->rb->set_custom_filter('longterm', array('type' => 'select', 'label' => __('Display tasks marked as'), 'args' => array('__NULL__' => __('Both'), 1 => __('Short-term'), 2 => __('Long-term')), 'trans' => array('__NULL__' => array(), 1 => array('!longterm' => 1), 2 => array('longterm' => 1))));
        $this->rb->set_defaults(array('employees' => array($me['id']), 'status' => 0, 'permission' => 0, 'priority' => CRM_CommonCommon::get_default_priority()));
        $this->rb->set_default_order(array('deadline' => 'ASC', 'longterm' => 'ASC', 'priority' => 'DESC', 'title' => 'ASC'));
        $this->display_module($this->rb);
    }

    public function applet($conf, & $opts)
    {
        $opts['go'] = true;
        $opts['title'] = __('Tasks');
        if (isset($conf['subtitle']) && $conf['subtitle']) {
            $opts['title'] .= ' - ' . $conf['subtitle'];
        }
        $short = ($conf['term'] == 's' || $conf['term'] == 'b');
        $long = ($conf['term'] == 'l' || $conf['term'] == 'b');
        $rb = $this->init_module(Utils_RecordBrowser::module_name(), 'task', 'task');
        $status = array();
        foreach (Utils_CommonDataCommon::get_array('CRM/Status')
                 as $status_id => $label) {
            if (isset($conf['status_' . $status_id]) &&
                $conf['status_' . $status_id]
            ) {
                $status[] = $status_id;
            }
        }
        $crits = array();
        $crits['status'] = $status;
        if ($short && !$long) $crits['!longterm'] = 1;
        if (!$short && $long) $crits['longterm'] = 1;

        if (isset($conf['crits']) && $conf['crits']) {
            $crits = Utils_RecordBrowserCommon::merge_crits($crits, $conf['crits']);
        }

        $conds = array(
            array(array('field' => 'title', 'width' => 20, 'callback' => array('CRM_TasksCommon', 'display_title_with_mark')),
                array('field' => 'deadline', 'width' => 10),
                array('field' => 'status', 'width' => 6),
            ),
            $crits,
            array('deadline' => 'ASC', 'status' => 'ASC', 'priority' => 'DESC'),
            array('CRM_TasksCommon', 'applet_info_format'),
            15,
            $conf,
            & $opts
        );
        $defaults = array('status' => 0, 'permission' => 0, 'priority' => CRM_CommonCommon::get_default_priority());
        $me = CRM_ContactsCommon::get_my_record();
        if ($me['id'] != -1) {
            $defaults['employees'] = array($me['id']);
        }
        $opts['actions'][] = Utils_RecordBrowserCommon::applet_new_record_button('task', $defaults);
        $this->display_module($rb, $conds, 'mini_view');
    }

    public function messanger_addon($arg)
    {
        $emp = array();
        $ret = CRM_ContactsCommon::get_contacts(array('id' => $arg['employees']), array(), array('last_name' => 'ASC', 'first_name' => 'ASC'));
        foreach ($ret as $c_id => $data)
            if (is_numeric($data['login'])) {
                $emp[$data['login']] = CRM_ContactsCommon::contact_format_no_company($data);
            }

        $mes = $this->init_module('Utils/Messenger', array('CRM_Tasks:' . $arg['id'], array('CRM_TasksCommon', 'get_alarm'), array($arg['id']), strtotime($arg['deadline']), $emp));
//		$mes->set_inline_display();
        $this->display_module($mes);
    }

    public function caption()
    {
        if (isset($this->rb)) return $this->rb->caption();
    }

    public function addon($r, $rb_parent)
    {
        $rb = $this->init_module(Utils_RecordBrowser::module_name(), 'task');
        $params = array(
            array(
                'related' => $rb_parent->tab . '/' . $r['id'],
            ),
            array(
                'related' => false,
            ),
            array(
                'status' => 'ASC',
                'deadline' => 'DESC'
            ),
        );

        //look for customers
        $customers = array();
        if (isset($r['customers'])) $customers = $r['customers'];
        elseif (isset($r['customer'])) $customers = $r['customer'];
        if (!is_array($customers)) $customers = array($customers);
        foreach ($customers as $i => &$customer) {
            if (preg_match('/^(C\:|company\/)([0-9]+)$/', $customer, $req)) {
                $customer = 'C:' . $req[2];
            } elseif (is_numeric($customer)) $customer = 'C:' . $customer;
            else unset($customers[$i]);
        }

        $me = CRM_ContactsCommon::get_my_record();
        $rb->set_defaults(array('related' => $rb_parent->tab . '/' . $r['id'], 'employees' => array($me['id']), 'status' => 0, 'permission' => 0, 'priority' => CRM_CommonCommon::get_default_priority(), 'customers' => $customers));
        $this->display_module($rb, $params, 'show_data');
    }

    public function admin()
    {
        if ($this->is_back()) {
            $this->parent->reset();
            return;
        }
        $rb = $this->init_module(Utils_RecordBrowser::module_name(), 'task_related', 'task_related');
        $this->display_module($rb);
        Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
    }

}

?>