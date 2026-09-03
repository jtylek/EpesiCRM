<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Janusz Tylek
 * @version 1.0
 * @license MIT
 * @package epesi-libs
 * @subpackage Ckeditor
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

// CKEditor -> Quill migration, 2026-08-11 (AI-shared/dont-reintroduce.md):
// replaced app-wide by modules/Libs/Quill. This module is kept installed - not
// uninstalled/deleted - purely so ModuleManager::uninstall() (which needs
// CKEditorInstall.php loadable to run its own uninstall() hook, and refuses to
// proceed if anything still requires it) has nothing to actually do here: no code
// requires Libs_CKEditorInstall anymore (Utils_Attachment/Base_Dashboard's
// requires() now list Libs_QuillInstall - see this module's own upgrade patches),
// so there's no correctness reason to force existing installs through an uninstall
// step, only a modest disk-space one. The vendored ckeditor/ tree, the 'ckeditor'
// QuickForm element class, and its ck.js lifecycle glue are all deleted; nothing
// registers the 'ckeditor' element type anymore.
class Libs_CKEditorCommon extends ModuleCommon {
}
?>
