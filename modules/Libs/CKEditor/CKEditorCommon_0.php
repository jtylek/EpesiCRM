<?php

use Epesi\Module\Libs\CKEditor\CKEditorQuickFormElement;

/**
 * This module uses CKeditor editor released under
 * GNU Lesser General Public License Version 2.1 or later (the "LGPL")
 * CKeditor - The text editor for Internet - http://www.Ckeditor.net
 * Copyright (C) 2003-2007 Frederico Caldeira Knabben
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Janusz Tylek
 * @version 1.0
 * @license MIT
 * @package epesi-libs
 * @subpackage Ckeditor
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

if(!MOBILE_DEVICE && class_exists('HTML_Quickform')) {
	$GLOBALS['HTML_QUICKFORM_ELEMENT_TYPES']['ckeditor'] = CKEditorQuickFormElement::class;
	
	load_css('modules/Libs/CKEditor/frontend.css');
}

class Libs_CKEditorCommon extends ModuleCommon {
	public static function QFfield_cb(&$form, $field, $label, $mode, $default, $desc, $rb_obj, $display_callbacks) {
		if (Utils_RecordBrowserCommon::QFfield_static_display($form, $field, $label, $mode, $default, $desc, $rb_obj)) return;
		
        if ($mode=='add' || $mode=='edit') {
        	$form->addElement('ckeditor', $field, $label)->setFCKProps('99%','300',true);

            if ($mode=='edit') $form->setDefaults([$field=>$default]);
        }
    }
}


?> 