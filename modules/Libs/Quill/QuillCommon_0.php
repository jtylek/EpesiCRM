<?php
/**
 * Quill Editor - https://quilljs.com
 * Copyright (c) 2017-2024, Slab. Copyright (c) 2014, Jason Chen. Copyright (c) 2013, salesforce.com
 * Released under the BSD 3-Clause License.
 *
 * @license MIT
 * @package epesi-libs
 * @subpackage Quill
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

// The 'quill' QuickForm element type is registered eagerly in
// include/epesi.php's register_custom_qf_types() (mirroring 'ckeditor's old
// entry there), and modules/Libs/Quill/quill.php's own constructor loads its
// JS *and* CSS - see the comment there for why. Nothing needed at this
// Common-file's top level.
class Libs_QuillCommon extends ModuleCommon {

	/**
	 * RecordBrowser QFfield callback turning a 'long text' field into a rich-text editor.
	 *
	 * Replaces Libs_CKEditorCommon::QFfield_cb, which went with the rest of that class
	 * when CKEditor was stripped to an empty shell (AI-shared/dont-reintroduce.md). Core
	 * itself never used the callback form - it only ever called addElement('ckeditor')
	 * directly - but modules outside this repo did, and a recordset stores the callback
	 * as a string in its <tab>_callback table, so switching one over needs a patch as
	 * well as an *Install.php edit.
	 */
	public static function QFfield_cb(&$form, $field, $label, $mode, $default, $desc, $rb_obj, $display_callbacks) {
		if ($mode == 'add' || $mode == 'edit') {
			$fck = & $form->addElement('quill', $field, $label);
			// No explicit width: Quill's toolbar is inserted as the container div's
			// preceding *sibling*, so it has no width of its own and only lines up
			// when neither element has one - see Utils_AttachmentCommon::QFfield_note()
			// for the longer version of this note.
			$fck->setQuillProps(null, '300', true);
			if ($mode == 'edit') $form->setDefaults(array($field => $default));
		} else {
			if (isset($display_callbacks[$desc['name']]))
				$callback = $display_callbacks[$desc['name']];
			else
				$callback = array('Libs_QuillCommon', 'display_cb');

			$form->addElement('static', $field, $label, call_user_func($callback, $rb_obj->record, false, $desc));
		}
	}

	/**
	 * Returns the stored value untouched, so RecordBrowser renders it as the HTML it
	 * is rather than escaping it. Pairs with QFfield_cb above.
	 */
	public static function display_cb($r, $nolink = false, $desc = null) {
		return $r[$desc['id']];
	}

}
?>
