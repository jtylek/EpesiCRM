<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

// Tools_SetDefaultsInstall::install() used to zero out Shoutbox's Launchpad
// column along with its Dashboard/ActionBar column, but Shoutbox was only
// ever meant to be limited on the Dashboard column - see install() for the
// current (corrected) logic. Existing installs from before that fix have an
// explicit '0' stored for Shoutbox's Launchpad quick-access default; reset it
// so it falls back to the schema default (on) like every other Launchpad-only
// item.
foreach (Base_Menu_QuickAccessCommon::get_options() as $opt) {
    if ($opt['label'] !== 'Shoutbox') continue;
    Base_User_SettingsCommon::save_admin(Base_Menu_QuickAccessInstall::module_name(), $opt['name'] . '_l', '1');
}
