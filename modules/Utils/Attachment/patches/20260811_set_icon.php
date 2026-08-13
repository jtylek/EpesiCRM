<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

// Was never set at install time, so the "Note: New record" screen's
// MainModuleIndicator fell all the way through Base_BootstrapIcons::resolve()'s
// module-attribution chain to the generic 'bi-app-indicator' fallback instead
// of Utils_AttachmentCommon::bootstrap_icon() - see AttachmentInstall.php.
Utils_RecordBrowserCommon::set_icon('utils_attachment', Base_ThemeCommon::get_template_filename(Utils_AttachmentInstall::module_name(), 'icon.png'));
