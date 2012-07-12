<?php
/**
 * 
 * @author Adam Bukowski <abukowski@telaxus.com>
 * @copyright Telaxus LLC
 * @license MIT
 * @version 0.1
 * @package epesi-CRM
 * @subpackage Assets
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_AssetsInstall extends ModuleInstall {

    public function install() {
        Utils_CommonDataCommon::new_array('crm_assets_category', array(_M('Desktop'), _M('Server'), _M('Notebook'), _M('Monitor'), _M('Printer'), _M('Other')), true, true);
        Utils_CommonDataCommon::new_array('crm_assets_monitor_type', array(_M('CRT'), _M('LCD'), _M('Other')));
        Utils_CommonDataCommon::new_array('crm_assets_printer_type', array(_M('Ink'), _M('Laser'), _M('Other')));

        $fields = array(
            array(
                'name' => _M('Asset ID'),
                'type'=>'calculated',
                'param'=>Utils_RecordBrowserCommon::actual_db_type('text', 16),
                'extra'=>false,
                'visible'=>true,
                'display_callback'=>array('CRM_AssetsCommon', 'display_asset_id')
            ),
            array(
                'name' => _M('Active'),
                'type'=>'checkbox',
                'extra'=>false,
                'visible'=>true,
                'filter'=>true
            ),
            array(
                'name' => _M('Category'),
                'type'=>'commondata',
                'param'=>array('crm_assets_category'),
                'extra'=>false,
                'visible'=>true,
                'filter'=>true,
                'required'=>true,
                'QFfield_callback'=>array('CRM_AssetsCommon', 'QFfield_category')
            ),
            array(
                'name' => _M('Asset Name'),
                'type'=>'text',
                'param'=>'128',
                'extra'=>false,
                'visible'=>true,
                'required'=>true
            ),
            array(
                'name' => _M('Asset Tag'),
                'type'=>'text',
                'param'=>'128',
                'extra'=>false
            ),
            array(
                'name' => _M('Company'),
                'type'=>'crm_company',
                'extra'=>false,
                'visible'=>true,
                'param'=>array('field_type'=>'select', 'crits'=>array('CRM_AssetsCommon','company_crits')),
                'filter'=>true
            ),
            array(
                'name' => _M('Date Purchased'),
                'type'=>'date',
                'extra'=>false
            ),
            /*************** COMMON ***************/
            array(
                'name' => _M('Serial Number'),
                'type'=>'text',
                'param'=>'128',
                'extra'=>false
            ),
            array(
                'name' => _M('IP Address'),
                'type'=>'text',
                'param'=>'128',
                'extra'=>false
            ),
            array(
                'name' => _M('General Info'),
                'type'=>'calculated',
                'extra'=>false,
                'visible'=>true,
                'display_callback'=>array('CRM_AssetsCommon', 'display_info'),
                'QFfield_callback'=>array('CRM_AssetsCommon', 'QFfield_info')
            ),
            /*************** COMPUTER ***************/
            array(
                'name' => _M('Host Name'),
                'type'=>'text',
                'param'=>'128',
                'extra'=>false
            ),
            array(
                'name' => _M('Operating System'),
                'type'=>'text',
                'param'=>'128',
                'extra'=>false
            ),
            array(
                'name' => _M('Processor'),
                'type'=>'text',
                'param'=>'128',
                'extra'=>false
            ),
            array(
                'name' => _M('RAM'),
                'type'=>'text',
                'param'=>'128',
                'extra'=>false
            ),
            array(
                'name' => _M('HDD'),
                'type'=>'text',
                'param'=>'128',
                'extra'=>false
            ),
            array(
                'name' => _M('Optical Devices'),
                'type'=>'text',
                'param'=>'128',
                'extra'=>false
            ),
            array(
                'name' => _M('Audio'),
                'type'=>'text',
                'param'=>'128',
                'extra'=>false
            ),
            array(
                'name' => _M('Software'),
                'type'=>'long text',
                'extra'=>false
            ),
            /*************** Monitor ***************/
            array(
                'name' => _M('Display Type'),
                'type'=>'commondata',
                'extra'=>false,
                'param'=>array('crm_assets_monitor_type')
            ),
            array(
                'name' => _M('Screen Size'),
                'type'=>'text',
                'param'=>'128',
                'extra'=>false
            ),
            /*************** Printer ***************/
            array(
                'name' => _M('Printer Type'),
                'type'=>'commondata',
                'extra'=>false,
                'param'=>array('order_by_key'=>true, 'crm_assets_printer_type')
            ),
            array(
                'name' => _M('Color Printing'),
                'type'=>'checkbox',
                'extra'=>false
            ),
        );

        Utils_RecordBrowserCommon::install_new_recordset('crm_assets', $fields);
        Utils_RecordBrowserCommon::set_recent('crm_assets', 10);
        Utils_RecordBrowserCommon::set_favorites('crm_assets', true);
        Utils_RecordBrowserCommon::set_caption('crm_assets', _M('Assets'));
        Utils_RecordBrowserCommon::set_quickjump('crm_assets', 'Asset Name');
        Utils_RecordBrowserCommon::set_icon('crm_assets', Base_ThemeCommon::get_template_filename('CRM/Assets', 'icon.png'));
        Utils_RecordBrowserCommon::register_processing_callback('crm_assets', array('CRM_AssetsCommon', 'process_request'));
        Utils_RecordBrowserCommon::enable_watchdog('crm_assets', array('CRM_AssetsCommon','watchdog_label'));

		Utils_RecordBrowserCommon::add_default_access('crm_assets');

        Utils_RecordBrowserCommon::new_addon('company', 'CRM/Assets', 'assets_addon', _M('Assets'));
		Utils_AttachmentCommon::new_addon('crm_assets');

        return true;
    }

    public function uninstall() {
        Utils_CommonDataCommon::remove('crm_assets_category');
        Utils_CommonDataCommon::remove('crm_assets_monitor_type');
        Utils_CommonDataCommon::remove('crm_assets_printer_type');
        Utils_RecordBrowserCommon::delete_addon('company', 'CRM/Assets', 'assets_addon');
		Utils_AttachmentCommon::delete_addon('crm_assets');
        Utils_AttachmentCommon::persistent_mass_delete('crm_assets');
        Utils_RecordBrowserCommon::uninstall_recordset('crm_assets');
        Utils_RecordBrowserCommon::unregister_processing_callback('crm_assets', array('CRM_AssetsCommon', 'process_request'));
        return true;
    }

    public function version() {
        return array("0.9");
    }

    public function requires($v) {
        return array(
            array('name'=>'Base/Lang','version'=>0),
            array('name'=>'Utils/RecordBrowser','version'=>0),
            array('name'=>'CRM/Contacts','version'=>0));
    }

    public static function info() {
        return array(
            'Description'=>'This module helps you to manage assets',
            'Author'=>'Adam Bukowski <abukowski@telaxus.com>',
            'License'=>'MIT');
    }

    public static function simple_setup() {
        return array('package'=>__('CRM'), 'option'=>__('Assets'));
    }

}

?>