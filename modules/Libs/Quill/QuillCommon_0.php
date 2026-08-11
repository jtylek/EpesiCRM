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
}
?>
