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

class CRM_Assets extends Module {
    private $rb;

    public function body() {
        Base_ActionBarCommon::add('delete', 'Reinstall', $this->create_callback_href(array($this,'reinstalltmp')));
        Base_ActionBarCommon::add('delete', 'Uninstall', $this->create_callback_href(array($this,'uninstalltmp')));
        Base_ActionBarCommon::add('add', 'Install', $this->create_callback_href(array($this,'installtmp')));
        if(Utils_RecordBrowserCommon::check_table_name('crm_assets',true)) {
            $this->rb = $this->init_module('Utils/RecordBrowser','crm_assets','crm_assets');
            $this->rb->set_filters_defaults(array('active'=>true));
            $this->display_module($this->rb);
        } else {
            print('Please install tables<br/>');
        }
    }

    public function caption() {
//        return $this->rb->caption();
        return 'Assets';
    }

    public function installtmp() {
        if(Utils_RecordBrowserCommon::check_table_name('crm_assets',true)) return false;

        Utils_CommonDataCommon::new_array('crm_assets_category', array('Desktop', 'Server', 'Notebook', 'Monitor', 'Printer', 'Other'), true, true);
        Utils_CommonDataCommon::new_array('crm_assets_monitor_type', array('CRT', 'LCD'));
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
                'param'=>array('field_type'=>'select', 'crits'=>array('CRM_AssetsCommon','company_crits'))
            ),
            array(
                'name'=>'Active',
                'type'=>'checkbox',
                'extra'=>false,
                'visible'=>false,
                'filter'=>true
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
                'name'=>'Network',
                'type'=>'text',
                'param'=>'128',
                'extra'=>false
            ),
            array(
                'name'=>'Other',
                'type'=>'long text',
                'extra'=>false
            ),
            /*************** COMPUTER ***************/
            array(
                'name'=>'Host Name',
                'type'=>'text',
                'param'=>'128'
            ),
            array(
                'name'=>'Operating System',
                'type'=>'text',
                'param'=>'128'
            ),
            array(
                'name'=>'Processor',
                'type'=>'text',
                'param'=>'128'
            ),
            array(
                'name'=>'Motherboard',
                'type'=>'text',
                'param'=>'128'
            ),
            array(
                'name'=>'RAM',
                'type'=>'text',
                'param'=>'128'
            ),
            array(
                'name'=>'HDD',
                'type'=>'text',
                'param'=>'128'
            ),
            array(
                'name'=>'Optical Devices',
                'type'=>'text',
                'param'=>'128'
            ),
            array(
                'name'=>'Audio',
                'type'=>'text',
                'param'=>'128'
            ),
            array(
                'name'=>'Modem',
                'type'=>'text',
                'param'=>'128'
            ),
            array(
                'name'=>'Ports',
                'type'=>'text',
                'param'=>'128'
            ),
            array(
                'name'=>'Software',
                'type'=>'long text'
            ),
            /*************** Monitor ***************/
            array(
                'name'=>'Display Type',
                'type'=>'commondata',
                'param'=>array('crm_assets_monitor_type')
            ),
            array(
                'name'=>'Screen Size',
                'type'=>'text',
                'param'=>'128'
            ),
            /*************** Printer ***************/
            array(
                'name'=>'Printer Type',
                'type'=>'commondata',
                'param'=>array('order_by_key'=>true, 'crm_assets_printer_type')
            ),
            array(
                'name'=>'Color Printing',
                'type'=>'checkbox'
            ),
        );

        Utils_RecordBrowserCommon::install_new_recordset('crm_assets', $fields);
        Utils_RecordBrowserCommon::set_recent('crm_assets', 10);
        Utils_RecordBrowserCommon::set_caption('crm_assets', 'Assets');
//        Utils_RecordBrowserCommon::set_icon('crm_assets', Base_ThemeCommon::get_template_filename('Custom/Projects', 'icon.png'));
        Utils_RecordBrowserCommon::set_processing_callback('crm_assets', array('CRM_AssetsCommon', 'process_request'));
//        Utils_RecordBrowserCommon::set_access_callback('crm_assets', array('CRM_AssetsCommon', 'access_equipment'));
        Utils_RecordBrowserCommon::enable_watchdog('crm_assets', array('CRM_AssetsCommon','watchdog_label'));
        DB::Execute('UPDATE crm_assets_field SET param = 1 WHERE field = %s', array('Details'));
        
        print('Installed Successfully<br/>');
        return true;
    }

    public function uninstalltmp() {
        Utils_CommonDataCommon::remove('crm_assets_category');
        Utils_CommonDataCommon::remove('crm_assets_monitor_type');
        Utils_CommonDataCommon::remove('crm_assets_printer_type');
        if( Utils_RecordBrowserCommon::uninstall_recordset('crm_assets') ) print('Uninstalled<br/>');
        return true;
    }

    public function reinstalltmp() {
        if($this->is_back()) return false;
        $this->uninstalltmp();
        $this->installtmp();
        print('<a '.$this->create_back_href().'> Return </a>');
        eval_js('setTimeout(\''.escapeJS($this->create_back_href_js(),false).'\',1000)');
        return true;
    }
}

?>