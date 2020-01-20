<?php
/**
 * This module uses CKeditor editor released under
 * GNU Lesser General Public License Version 2.1 or later (the "LGPL")
 * CKeditor - The text editor for Internet - http://www.Ckeditor.net
 * Copyright (C) 2003-2007 Frederico Caldeira Knabben
 *
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2006-2020 Janusz Tylek
 * @version 1.9.0
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
	public static function QFfield_cb(&$form, $field, $label, $mode, $default, $desc, $rb_obj, $display_callbacks) {
        if ($mode=='add' || $mode=='edit') {
            $fck = $form->addElement('ckeditor', $field, $label);
            $fck->setFCKProps('99%','300',true);
            if ($mode=='edit') $form->setDefaults(array($field=>$default));
        } else {
        	if (isset($display_callbacks[$desc['name']]))
        		$callback = $display_callbacks[$desc['name']];
        	else
        		$callback = array('Libs_CKEditorCommon','display_cb');
        		
        	$form->addElement('static', $field, $label, call_user_func($callback, $rb_obj->record, false, $desc));
        }
    }

    public static function display_cb($r, $nolink=false, $desc=null) {
        return $r[$desc['id']];
    }

}
?> 