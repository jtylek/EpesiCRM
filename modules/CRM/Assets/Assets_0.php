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
        $this->rb = $this->init_module('Utils/RecordBrowser','crm_assets','crm_assets');
        $this->rb->set_filters_defaults(array('active'=>true));
        $this->rb->set_header_properties(array(
                'asset_id'=>array('width'=>10),
                'category'=>array('width'=>10),
                'asset_name'=>array('width'=>15),
                'company'=>array('width'=>15),
                'general_info'=>array('width'=>70)
            ));
        $this->display_module($this->rb, array(array('asset_name'=>'ASC'),array(),array('active'=>false)));
    }

    public function assets_addon($arg) {
        $rb = $this->init_module('Utils/RecordBrowser','crm_assets','crm_assets_addon');
        $rb->set_header_properties(array(
                'asset_id'=>array('width'=>10),
                'category'=>array('width'=>10),
                'asset_name'=>array('width'=>15),
                'active'=>array('width'=>7),
                'general_info'=>array('width'=>70)
            ));
        $rb->set_button($this->create_callback_href(array($this,'assets_addon_new_asset'), array($arg['id'])));
        $this->display_module($rb, array(array('company'=>array($arg['id'])), array('company'=>false, 'active'=>true), array('asset_name'=>'ASC')), 'show_data');
    }

    public function assets_addon_new_asset($company) {
        $x = ModuleManager::get_instance('/Base_Box|0');
        if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
        $x->push_main('CRM/Assets', 'new_asset', $company, array());
        return false;
    }

    public function new_asset($company) {
        $rb = $this->init_module('Utils/RecordBrowser','crm_assets','crm_assets');
        $rb->view_entry('add',null,array('company'=>$company));
    }

    public function assets_attachment_addon($arg) {
        $a = $this->init_module('Utils/Attachment',array('CRM/Assets/'.$arg['id']));
        $a->set_view_func(array('CRM_AssetsCommon','search_format'), array($arg['id']));
        $a->additional_header($arg['asset_name'].' ('.$arg['asset_id'].')');
        $this->display_module($a);
    }

    public function caption() {
        if(isset($this->rb)) return $this->rb->caption();
    }

}

?>