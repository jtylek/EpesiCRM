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

class CRM_AssetsCommon extends ModuleCommon {
    public static function menu() {
        return array('CRM'=>array('__submenu__'=>1,'Assets'=>array()));
    }

    public static function display_asset_id($r, $nolink) {
        return Utils_RecordBrowserCommon::create_linked_label_r('crm_assets', 'asset_id', $r, $nolink);
    }

    public static function watchdog_label($rid = null, $events = array(), $details = true) {
        return Utils_RecordBrowserCommon::watchdog_label(
        'crm_assets',
        Base_LangCommon::ts('CRM_Assets','Assets'),
        $rid,
        $events,
        'name',
        $details
        );
    }

    public static function generate_id($id) {
        if(is_array($id)) $id = $id['id'];
        return '#'.str_pad($id, 4, '0', STR_PAD_LEFT);
    }

    public static function process_request($data, $mode) {
        switch($mode) {
            case 'adding':
                $data['active']=true;
                break;
            case 'added':
                Utils_RecordBrowserCommon::update_record('crm_assets',$data['id'],array('asset_id'=>self::generate_id($data['id'])), false, null, true);
                break;
            default:
                break;
        }
        return $data;
    }

    public static function company_crits() {
        return array();
    }

    public static function QFfield_category(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
        load_js('modules/CRM/Assets/change.js');
        eval_js('change('.$default.');');

        $data = Utils_CommonDataCommon::get_translated_array('crm_assets_category');
        if($mode!='view') {
            ksort($data);
            $form->addElement('select', $field, $label, $data, array('onchange'=>'change(this.selectedIndex);','id'=>$field));
            $form->setDefaults(array($field=>$default));
        } else {
            $form->addElement('static', $field, $label, $data[$default]);
        }
    }

}

?>