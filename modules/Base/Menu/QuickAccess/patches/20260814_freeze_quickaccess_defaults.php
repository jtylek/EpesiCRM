<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

// Base_Menu_QuickAccessCommon::user_settings() used to default every menu
// item, from every module, to visible in both the Quick Access bar and the
// Launchpad - installing a new module (e.g. Premium_Warehouse, whose install
// added Items/Warehouses/Sales by Item/etc.) meant its menu items showed up
// for every user immediately, with no explicit opt-in. Freezes today's full
// set (whatever's already installed right now, Premium_Warehouse included)
// as the "grandfathered" baseline that keeps defaulting to visible - see
// QuickAccessCommon_0.php's user_settings()/get_grandfathered_items(). Any
// module installed after this patch runs starts hidden by default instead;
// existing per-user overrides in base_user_settings are untouched either way.
Base_Menu_QuickAccessCommon::freeze_current_items_as_grandfathered();
