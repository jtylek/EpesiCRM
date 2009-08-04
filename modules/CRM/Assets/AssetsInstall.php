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
        Utils_CommonDataCommon::new_array('crm_assets_category', array('Desktop', 'Server', 'Notebook', 'Monitor', 'Printer', 'Other'), true, true);
        Utils_CommonDataCommon::new_array('crm_assets_monitor_type', array('CRT', 'LCD', 'Other'));
        Utils_CommonDataCommon::new_array('crm_assets_printer_type', array('Ink', 'Laser', 'Other'));

        $fields = array(
            array(
                'name'=>'Asset ID',
                'type'=>'calculated',
                'param'=>Utils_RecordBrowserCommon::actual_db_type('text', 16),
                'extra'=>false,
                'visible'=>true,
                'display_callback'=>array('CRM_AssetsCommon', 'display_asset_id')
            ),
            array(
                'name'=>'Active',
                'type'=>'checkbox',
                'extra'=>false,
                'visible'=>true,
                'filter'=>true
            ),
            array(
                'name'=>'Category',
                'type'=>'commondata',
                'param'=>array('crm_assets_category'),
                'extra'=>false,
                'visible'=>true,
                'filter'=>true,
                'required'=>true,
                'QFfield_callback'=>array('CRM_AssetsCommon', 'QFfield_category')
            ),
            array(
                'name'=>'Asset Name',
                'type'=>'text',
                'param'=>'128',
                'extra'=>false,
                'visible'=>true,
                'required'=>true
            ),
            array(
                'name'=>'Asset Tag',
                'type'=>'text',
                'param'=>'128',
                'extra'=>false
            ),
            array(
                'name'=>'Company',
                'type'=>'crm_company',
                'extra'=>false,
                'visible'=>true,
                'param'=>array('field_type'=>'select', 'crits'=>array('CRM_AssetsCommon','company_crits')),
                'filter'=>true
            ),
            array(
                'name'=>'Date Purchased',
                'type'=>'date',
                'extra'=>false
            ),
            /*************** COMMON ***************/
            array(
                'name'=>'Serial Number',
                'type'=>'text',
                'param'=>'128',
                'extra'=>false
            ),
            array(
                'name'=>'IP Address',
                'type'=>'text',
                'param'=>'128',
                'extra'=>false
            ),
            array(
                'name'=>'General Info',
                'type'=>'calculated',
                'extra'=>false,
                'visible'=>true,
                'display_callback'=>array('CRM_AssetsCommon', 'display_info'),
                'QFfield_callback'=>array('CRM_AssetsCommon', 'QFfield_info')
            ),
            /*************** COMPUTER ***************/
            array(
                'name'=>'Host Name',
                'type'=>'text',
                'param'=>'128',
                'extra'=>false
            ),
            array(
                'name'=>'Operating System',
                'type'=>'text',
                'param'=>'128',
                'extra'=>false
            ),
            array(
                'name'=>'Processor',
                'type'=>'text',
                'param'=>'128',
                'extra'=>false
            ),
            array(
                'name'=>'RAM',
                'type'=>'text',
                'param'=>'128',
                'extra'=>false
            ),
            array(
                'name'=>'HDD',
                'type'=>'text',
                'param'=>'128',
                'extra'=>false
            ),
            array(
                'name'=>'Optical Devices',
                'type'=>'text',
                'param'=>'128',
                'extra'=>false
            ),
            array(
                'name'=>'Audio',
                'type'=>'text',
                'param'=>'128',
                'extra'=>false
            ),
            array(
                'name'=>'Software',
                'type'=>'long text',
                'extra'=>false
            ),
            /*************** Monitor ***************/
            array(
                'name'=>'Display Type',
                'type'=>'commondata',
                'extra'=>false,
                'param'=>array('crm_assets_monitor_type')
            ),
            array(
                'name'=>'Screen Size',
                'type'=>'text',
                'param'=>'128',
                'extra'=>false
            ),
            /*************** Printer ***************/
            array(
                'name'=>'Printer Type',
                'type'=>'commondata',
                'extra'=>false,
                'param'=>array('order_by_key'=>true, 'crm_assets_printer_type')
            ),
            array(
                'name'=>'Color Printing',
                'type'=>'checkbox',
                'extra'=>false
            ),
        );

        Utils_RecordBrowserCommon::install_new_recordset('crm_assets', $fields);
        Utils_RecordBrowserCommon::set_recent('crm_assets', 10);
        Utils_RecordBrowserCommon::set_favorites('crm_assets', true);
        Utils_RecordBrowserCommon::set_caption('crm_assets', 'Assets');
        Utils_RecordBrowserCommon::set_quickjump('crm_assets', 'Asset Name');
        Utils_RecordBrowserCommon::set_icon('crm_assets', Base_ThemeCommon::get_template_filename('CRM/Assets', 'icon.png'));
        Utils_RecordBrowserCommon::set_processing_callback('crm_assets', array('CRM_AssetsCommon', 'process_request'));
    //        Utils_RecordBrowserCommon::set_access_callback('crm_assets', array('CRM_AssetsCommon', 'access_assets'));
        Utils_RecordBrowserCommon::enable_watchdog('crm_assets', array('CRM_AssetsCommon','watchdog_label'));

        Utils_RecordBrowserCommon::new_addon('company', 'CRM/Assets', 'assets_addon', 'Assets');
        Utils_RecordBrowserCommon::new_addon('crm_assets', 'CRM/Assets', 'assets_attachment_addon', 'Notes');

        return true;
    }

    public function uninstall() {
        Utils_CommonDataCommon::remove('crm_assets_category');
        Utils_CommonDataCommon::remove('crm_assets_monitor_type');
        Utils_CommonDataCommon::remove('crm_assets_printer_type');
        Utils_RecordBrowserCommon::delete_addon('company', 'CRM/Assets', 'assets_addon');
        Utils_RecordBrowserCommon::delete_addon('crm_assets', 'CRM/Assets', 'assets_attachment_addon');
        Utils_AttachmentCommon::persistent_mass_delete('CRM/Assets');
        Utils_RecordBrowserCommon::uninstall_recordset('crm_assets');
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
        return true;
    }

}

?>