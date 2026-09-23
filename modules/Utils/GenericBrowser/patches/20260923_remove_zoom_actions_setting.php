<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

// The "Zoom 'Actions' buttons" user setting (zoom_actions) was removed from
// Utils_GenericBrowserCommon::user_settings(), along with the actions-cell hover
// preview it switched on in GenericBrowser_0.php. Nothing reads the variable any
// more, so drop the per-user values and any admin-set default left behind.
DB::Execute('DELETE FROM base_user_settings WHERE module=%s AND variable=%s', array('Utils_GenericBrowser', 'zoom_actions'));
DB::Execute('DELETE FROM base_user_settings_admin_defaults WHERE module=%s AND variable=%s', array('Utils_GenericBrowser', 'zoom_actions'));
