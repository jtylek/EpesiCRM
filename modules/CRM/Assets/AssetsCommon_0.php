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
        /* computer */
        if($r['category']<3) {
            /* structure: variable_name => display_label */
            $k = array('host_name'=>'Host Name', 'processor'=>'CPU', 'ram'=>'RAM', 'hdd'=>'HDD', 'operating_system'=>'OS', 'optical_devices'=>'DRIVES', 'audio'=>'AUDIO', 'software'=>'SOFT');
            foreach($k as $var => $label) {
                $pos = Base_User_SettingsCommon::get('CRM/Assets', $var.'_pos');
                if($r[$var] && Base_User_SettingsCommon::get('CRM/Assets', $var)) $arr[$pos] = '['.Base_LangCommon::t($label).'] '.$r[$var];
            }
            /* laptop screen */
            if($r['category']==2) {
                $pos = Base_User_SettingsCommon::get('CRM/Assets', 'laptop_screen_pos');
                if($r['screen_size'] && Base_User_SettingsCommon::get('CRM/Assets', 'laptop_screen')) $arr[$pos] = '['.Base_LangCommon::t('Screen').'] '.$r['screen_size'];
            }
        }
        /* monitor */
        if($r['category']==3) {
            if(Base_User_SettingsCommon::get('CRM/Assets', 'display_type')) {
                $type = Utils_CommonDataCommon::get_translated_array('crm_assets_monitor_type');
                $pos = Base_User_SettingsCommon::get('CRM/Assets', 'display_type_pos');
                $arr[$pos] = '['.Base_LangCommon::t('Display Type').'] '.($r['display_type']!=null ? $type[$r['display_type']] : Base_LangCommon::t('Undefined'));
            }
            $pos = Base_User_SettingsCommon::get('CRM/Assets', 'screen_size_pos');
            if($r['screen_size'] && Base_User_SettingsCommon::get('CRM/Assets', 'screen_size')) $arr[$pos] = '['.Base_LangCommon::t('Screen Size').'] '.$r['screen_size'];
        }
        /* printer */
        if($r['category']==4) {
            if(Base_User_SettingsCommon::get('CRM/Assets', 'printer_type')) {
                $type = Utils_CommonDataCommon::get_translated_array('crm_assets_printer_type');
                $pos = Base_User_SettingsCommon::get('CRM/Assets', 'printer_type_pos');
                $arr[$pos] = '['.Base_LangCommon::t('Printer Type').'] '.($r['printer_type']!=null ? $type[$r['printer_type']] : Base_LangCommon::t('Undefined'));
            }
            if(Base_User_SettingsCommon::get('CRM/Assets', 'color_printing')) {
                $color = $r['color_printing'] ? 'Yes': 'No';
                $pos = Base_User_SettingsCommon::get('CRM/Assets', 'color_printing_pos');
                $arr[$pos] = '['.Base_LangCommon::t('Color printing').'] '.Base_LangCommon::t($color);
            }
        }
        if($r['category']<=4) {
            if(isset($arr)) ksort($arr);
            return isset($arr) ? implode(' ', $arr) : Base_LangCommon::t('No info');
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

    public static function search_format($id) {
        $row = Utils_RecordBrowserCommon::get_records('crm_assets',array('id'=>$id));
        if(!$row) return false;
        $row = array_pop($row);
        return Utils_RecordBrowserCommon::record_link_open_tag('crm_assets', $row['id']).Base_LangCommon::ts('CRM_Assets', 'Assets (attachment) #%d, %s (%s)', array($row['id'], $row['asset_name'], $row['asset_id'])).Utils_RecordBrowserCommon::record_link_close_tag();
    }

    public static function user_settings() {
        return array('Assets'=>array(
                array('name'=>'desc', 'label'=>'Check what should appear in General Info', 'type'=>'static', 'default'=>' and set order of appearance(smaller number -> earlier showed)'),

                array('name'=>'computer_header', 'label'=>'', 'type'=>'header', 'default'=>'Computer'),
                array('name'=>'processor', 'label'=>'CPU', 'type'=>'checkbox', 'default'=>true),
                    array('name'=>'processor_pos', 'label'=>'Position', 'type'=>'numeric', 'default'=>'1'),
                array('name'=>'ram', 'label'=>'RAM', 'type'=>'checkbox', 'default'=>true),
                    array('name'=>'ram_pos', 'label'=>'Position', 'type'=>'numeric', 'default'=>'2'),
                array('name'=>'hdd', 'label'=>'HDD', 'type'=>'checkbox', 'default'=>true),
                    array('name'=>'hdd_pos', 'label'=>'Position', 'type'=>'numeric', 'default'=>'3'),
                array('name'=>'operating_system', 'label'=>'OS', 'type'=>'checkbox', 'default'=>true),
                    array('name'=>'operating_system_pos', 'label'=>'Position', 'type'=>'numeric', 'default'=>'4'),
                array('name'=>'host_name', 'label'=>'Host Name', 'type'=>'checkbox', 'default'=>false),
                    array('name'=>'host_name_pos', 'label'=>'Position', 'type'=>'numeric', 'default'=>'5'),
                array('name'=>'optical_devices', 'label'=>'Optical Devices', 'type'=>'checkbox', 'default'=>false),
                    array('name'=>'optical_devices_pos', 'label'=>'Position', 'type'=>'numeric', 'default'=>'6'),
                array('name'=>'audio', 'label'=>'Audio', 'type'=>'checkbox', 'default'=>false),
                    array('name'=>'audio_pos', 'label'=>'Position', 'type'=>'numeric', 'default'=>'7'),
                array('name'=>'software', 'label'=>'Software', 'type'=>'checkbox', 'default'=>false),
                    array('name'=>'sofware_pos', 'label'=>'Position', 'type'=>'numeric', 'default'=>'8'),
                array('name'=>'laptop_screen', 'label'=>'Laptop Screen Size', 'type'=>'checkbox', 'default'=>true),
                    array('name'=>'laptop_screen_pos', 'label'=>'Position', 'type'=>'numeric', 'default'=>'9'),

                array('name'=>'monitor_header', 'label'=>'', 'type'=>'header', 'default'=>'Monitor'),
                array('name'=>'display_type', 'label'=>'Display Type', 'type'=>'checkbox', 'default'=>true),
                    array('name'=>'display_type_pos', 'label'=>'Position', 'type'=>'numeric', 'default'=>'1'),
                array('name'=>'screen_size', 'label'=>'Screen Size', 'type'=>'checkbox', 'default'=>true),
                    array('name'=>'screen_size_pos', 'label'=>'Position', 'type'=>'numeric', 'default'=>'2'),

                array('name'=>'printer_header', 'label'=>'', 'type'=>'header', 'default'=>'Printer'),
                array('name'=>'printer_type', 'label'=>'Printer Type', 'type'=>'checkbox', 'default'=>true),
                    array('name'=>'printer_type_pos', 'label'=>'Position', 'type'=>'numeric', 'default'=>'1'),
                array('name'=>'color_printing', 'label'=>'Color Printing', 'type'=>'checkbox', 'default'=>true),
                    array('name'=>'color_printing_pos', 'label'=>'Position', 'type'=>'numeric', 'default'=>'2')
            ));
    }

}

?>