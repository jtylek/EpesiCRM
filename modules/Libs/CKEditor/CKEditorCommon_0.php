<?php
/**
 * This module uses CKeditor editor released under
 * GNU Lesser General Public License Version 2.1 or later (the "LGPL")
 * CKeditor - The text editor for Internet - http://www.Ckeditor.net
 * Copyright (C) 2003-2007 Frederico Caldeira Knabben
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-libs
 * @subpackage Ckeditor
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

if(!MOBILE_DEVICE && class_exists('HTML_Quickform')) {
	HTML_Quickform::registerElementType('ckeditor','modules/Libs/CKEditor/ckeditor.php'
                                            ,'HTML_Quickform_ckeditor');
/*	load_js('modules/Libs/CKEditor/onsubmit.js');*/
	load_css('modules/Libs/CKEditor/frontend.css');
/*	Libs_QuickFormCommon::add_on_submit_action("if(typeof(ckeditor_onsubmit)!='undefined')ckeditor_onsubmit(this)");*/
}
class Libs_CKEditorCommon extends ModuleCommon {
    public static function QFfield_cb(&$form, $field, $label, $mode, $default) {
        if ($mode=='add' || $mode=='edit') {
            $fck = $form->addElement('ckeditor', $field, $label);
            $fck->setFCKProps('99%','300',true);
            if ($mode=='edit') $form->setDefaults(array($field=>$default));
        } else {
            $form->addElement('static', $field, $label);
            $form->setDefaults(array($field=>html_entity_decode($default)));
        }
    }

    public static function display_cb($r, $nolink=false, $desc=null) {
        return html_entity_decode(html_entity_decode($r[$desc['id']]));
    }

}
?> 