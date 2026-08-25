<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

// Tools_SetDefaultsInstall::install() used to apply the curated Quick
// Access/Launchpad "keep enabled" defaults during module install - but on
// every install that already went through FirstRun (i.e. every existing
// install), that ran BEFORE Base_Menu_QuickAccessCommon::
// freeze_current_items_as_grandfathered() flips every item's default to
// visible, so Base_User_SettingsCommon::save_admin()'s value==default
// optimization silently discarded every one of those writes instead of
// storing a real override - "Dashboard is full of links again" (2026-08-25).
// See Tools_SetDefaultsCommon::apply_quickaccess_defaults() (now called
// post-freeze from FirstRun_0.php::done() for fresh installs) for the fixed
// logic; this patch re-applies the same curation here, now that
// grandfathering has already happened for this install, so '0' is a real
// departure from the (already 1) default and actually persists this time.
Tools_SetDefaultsCommon::apply_quickaccess_defaults();
