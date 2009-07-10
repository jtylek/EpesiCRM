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

    public static function display_info($r, $nolink) {
        /* monitor */
        if($r['category']<3) {
            $str = Base_LangCommon::t('Computer');
            if($r['category']==3) $str .= '('.Base_LangCommon::t('Laptop').')';
/* TODO complete summary for comp
 */
            $str .= ', more info later';
            return $str;
        }
        if($r['category']==3) {
            $type = Utils_CommonDataCommon::get_translated_array('crm_assets_monitor_type');
            $str = Base_LangCommon::t('Display type').': '.$type[$r['display_type']];
            $str .= ', '.Base_LangCommon::t('Screen size').': '.$r['screen_size'];
            return $str;
        }
        if($r['category']==4) {
            $type = Utils_CommonDataCommon::get_translated_array('crm_assets_printer_type');
            $str = Base_LangCommon::t('Printer type').': '.$type[$r['printer_type']];
            $color = $r['color_printing'] ? 'Yes': 'No';
            $str .= ', '.Base_LangCommon::t('Color printing').': '.Base_LangCommon::t($color);
            return $str;
        }
        return Base_LangCommon::t('This is non-categorized asset.');
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

    public static function QFfield_info(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
    }
    
}

?>