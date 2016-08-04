<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage meetings
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_MeetingInstall extends ModuleInstall {

    public function install() {
        Base_ThemeCommon::install_default_theme(CRM_MeetingInstall::module_name());
        //addons table
        $fields = array(
            array(
                'name'  => _M('Recordset'),
                'type'  => 'text',
                'param' => 64,
                'display_callback' => array(
                    $this->get_type() . 'Common',
                    'display_recordset',
                ),
                'QFfield_callback' => array(
                    $this->get_type() . 'Common',
                    'QFfield_recordset',
                ),
                'required' => true,
                'extra'    => false,
                'visible'  => true,
            ),
        );
        Utils_RecordBrowserCommon::install_new_recordset('crm_meeting_related', $fields);
        Utils_RecordBrowserCommon::set_caption('crm_meeting_related', _M('Meeting Related Recordsets'));
        Utils_RecordBrowserCommon::register_processing_callback('crm_meeting_related', array('CRM_MeetingCommon', 'processing_related'));
        Utils_RecordBrowserCommon::add_access('crm_meeting_related', 'view', 'ACCESS:employee');
        Utils_RecordBrowserCommon::add_access('crm_meeting_related', 'add', 'ADMIN');
        Utils_RecordBrowserCommon::add_access('crm_meeting_related', 'edit', 'SUPERADMIN');
        Utils_RecordBrowserCommon::add_access('crm_meeting_related', 'delete', 'SUPERADMIN');
        //meeting recordset
        $fields = array(
            array(
                'name'     => _M('Title'),
                'type'     => 'text',
                'required' => true,
                'param'    => '255',
                'extra'    => false,
                'visible'  => true,
                'display_callback' => array(
                    'CRM_MeetingCommon',
                    'display_title',
                ),
            ),
            array(
                'name'    => _M('Description'),
                'type'    => 'long text',
                'extra'   => false,
                'param'   => '255',
                'visible' => false,
            ),
            array(
                'name'     => _M('Date'),
                'type'     => 'date',
                'required' => true,
                'extra'    => false,
                'visible'  => true,
                'display_callback' => array(
                    'CRM_MeetingCommon',
                    'display_date',
                ),
            ),
            array(
                'name'     => _M('Time'),
                'type'     => 'time',
                'required' => true,
                'extra'    => false,
                'visible'  => true,
                'param'    => '5',
            ),
            array(
                'name'    => _M('Duration'),
                'type'    => 'integer',
                'extra'   => false,
                'param'   => '255',
                'visible' => false,
                'QFfield_callback' => array(
                    'CRM_MeetingCommon',
                    'QFfield_duration',
                ),
            ),
            array(
                'name' => _M('Employees'),
                'type' => 'crm_contact',
                'param' => array(
                    'field_type' => 'multiselect',
                    'crits' => array(
                        'CRM_MeetingCommon',
                        'employees_crits',
                    ),
                    'format' => array(
                        'CRM_ContactsCommon',
                        'contact_format_no_company',
                    ),
                ),
                'display_callback' => array(
                    'CRM_MeetingCommon',
                    'display_employees',
                ),
                'required' => true,
                'extra'    => false,
                'visible'  => true,
                'filter'   => true,
            ),
            array(
                'name' => _M('Customers'),
                'type' => 'crm_company_contact',
                'param' => array(
                    'field_type' => 'multiselect',
                    'crits' => array(
                        'CRM_MeetingCommon',
                        'customers_crits',
                    ),
                ),
                'extra' => false,
                'visible' => true,
            ),
            array(
                'name'     => _M('Status'),
                'type'     => 'commondata',
                'required' => true,
                'visible'  => true,
                'filter'   => true,
                'param' => array(
                    'order_by_key' => true,
                    'CRM/Status',
                ),
                'extra' => false,
                'visible' => true,
                'display_callback' => array(
                    'CRM_MeetingCommon',
                    'display_status',
                ),
            ),
            array(
                'name'     => _M('Priority'),
                'type'     => 'commondata',
                'required' => true,
                'visible'  => true,
                'param' => array(
                    'order_by_key' => true,
                    'CRM/Priority',
                ),
                'extra' => false,
            ),
            array(
                'name'     => _M('Permission'),
                'type'     => 'commondata',
                'required' => true,
                'param' => array(
                    'order_by_key' => true,
                    'CRM/Access',
                ),
                'extra' => false,
            ),
            array(
                'name'     => _M('Recurrence type'),
                'type'     => 'integer',
                'required' => false,
                'extra'    => false,
                'visible'  => false,
                'QFfield_callback' => array(
                    'CRM_MeetingCommon',
                    'QFfield_recurrence',
                ),
            ),
            array(
                'name'     => _M('Recurrence end'),
                'type'     => 'date',
                'required' => false,
                'extra'    => false,
                'QFfield_callback' => array(
                    'CRM_MeetingCommon',
                    'QFfield_recurrence_end',
                ),
                'visible' => false,
            ),
            array(
                'name'     => _M('Recurrence hash'),
                'type'     => 'text',
                'required' => false,
                'param'    => '16',
                'extra'    => false,
                'QFfield_callback' => array(
                    'CRM_MeetingCommon',
                    'QFfield_recurrence_hash',
                ),
                'visible' => false,
            ),
            array(
                'name'     => _M('Related'),
                'type'     => 'multiselect',
                'QFfield_callback' => array(
                    'CRM_MeetingCommon',
                    'QFfield_related',
                ),
                'param'    => '__RECORDSETS__::;CRM_MeetingCommon::related_crits',
                'extra'    => false,
                'required' => false,
                'visible'  => true,
            ),
        );
        Utils_RecordBrowserCommon::install_new_recordset('crm_meeting', $fields);
        Utils_RecordBrowserCommon::set_tpl('crm_meeting', Base_ThemeCommon::get_template_filename(CRM_MeetingInstall::module_name(), 'default'));
        Utils_RecordBrowserCommon::register_processing_callback('crm_meeting', array('CRM_MeetingCommon', 'submit_meeting'));
        Utils_RecordBrowserCommon::set_icon('crm_meeting', Base_ThemeCommon::get_template_filename(CRM_MeetingInstall::module_name(), 'icon.png'));
        Utils_RecordBrowserCommon::set_recent('crm_meeting', 10);
        Utils_RecordBrowserCommon::set_caption('crm_meeting', _M('Meetings'));
        Utils_RecordBrowserCommon::enable_watchdog('crm_meeting', array('CRM_MeetingCommon', 'watchdog_label'));
        // ************ addons ************** //
        Utils_AttachmentCommon::new_addon('crm_meeting');
        Utils_RecordBrowserCommon::new_addon('crm_meeting', CRM_MeetingInstall::module_name(), 'messanger_addon', _M('Alerts'));
        // ************ other ************** //
        CRM_CalendarCommon::new_event_handler(_M('Meetings'), array('CRM_MeetingCommon', 'crm_calendar_handler'));
        CRM_MailCommon::new_addon('crm_meeting');
        Utils_BBCodeCommon::new_bbcode('meeting', 'CRM_MeetingCommon', 'meeting_bbcode');
        if (ModuleManager::is_installed('Premium_SalesOpportunity') >= 0) 
            Utils_RecordBrowserCommon::new_record_field('crm_meeting', _M('Opportunity'), 'select', true, false, 'premium_salesopportunity::Opportunity Name;Premium_SalesOpportunityCommon::crm_opportunity_reference_crits', '', false);
        Utils_RecordBrowserCommon::add_access('crm_meeting', 'view', 'ACCESS:employee', array('(!permission' => 2, '|employees' => 'USER'));
        Utils_RecordBrowserCommon::add_access('crm_meeting', 'add', 'ACCESS:employee');
        Utils_RecordBrowserCommon::add_access('crm_meeting', 'edit', 'ACCESS:employee', array('(permission' => 0, '|employees' => 'USER', '|customers' => 'USER'));
        Utils_RecordBrowserCommon::add_access('crm_meeting', 'delete', 'ACCESS:employee', array(':Created_by' => 'USER_ID'));
        Utils_RecordBrowserCommon::add_access('crm_meeting', 'delete', array('ACCESS:employee', 'ACCESS:manager'));
        Utils_RecordBrowserCommon::set_search('crm_meeting', 2, 0);
        return true;
    }

    public function uninstall() {
        Utils_AttachmentCommon::delete_addon('crm_meeting');
        Utils_RecordBrowserCommon::delete_addon('crm_meeting', CRM_MeetingInstall::module_name(), 'messanger_addon');
        CRM_MailCommon::delete_addon('crm_meeting');
        CRM_CalendarCommon::delete_event_handler('Meetings');
        Base_ThemeCommon::uninstall_default_theme(CRM_MeetingInstall::module_name());
        Utils_RecordBrowserCommon::uninstall_recordset('crm_meeting');
        Utils_RecordBrowserCommon::unregister_processing_callback('crm_meeting', array('CRM_MeetingCommon', 'submit_meeting'));
        return true;
    }

    public function version() {
        return array("1.0");
    }

    public function requires($v) {
        return array(
            array('name' => Utils_RecordBrowserInstall::module_name(), 'version' => 0),
            array('name' => Utils_AttachmentInstall::module_name(), 'version' => 0),
            array('name' => CRM_CommonInstall::module_name(), 'version' => 0),
            array('name' => CRM_ContactsInstall::module_name(), 'version' => 0),
            array('name' => CRM_RoundcubeInstall::module_name(), 'version' => 0),
            array('name' => CRM_CalendarInstall::module_name(), 'version' => 0),
            array('name' => CRM_FollowupInstall::module_name(), 'version' => 0),
            array('name' => Base_LangInstall::module_name(), 'version' => 0),
            array('name' => Base_AclInstall::module_name(), 'version' => 0),
            array('name' => Utils_ChainedSelectInstall::module_name(), 'version' => 0),
            array('name' => Data_CountriesInstall::module_name(), 'version' => 0),
            array('name' => CRM_FiltersInstall::module_name(), 'version' => 0),
            array('name' => Libs_QuickFormInstall::module_name(), 'version' => 0),
            array('name' => Base_ThemeInstall::module_name(), 'version' => 0)
        );
    }

    public static function info() {
        return array('Author' => '<a href="mailto:abisaga@telaxus.com">Arkadiusz Bisaga</a> (<a href="http://www.telaxus.com">Telaxus LLC</a>)', 'License' => 'TL', 'Description' => 'Meeting schedule module.');
    }

    public static function simple_setup() {
        return 'CRM';
    }
}
?>
