<?php
/**
 * EPESI Compatibility check.
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @license MIT
 * @package epesi-base
 */
$fullscreen = !defined("_VALID_ACCESS");
!$fullscreen || define("_VALID_ACCESS", true);

define('CID', false);
require_once('include/data_dir.php');
$config = file_exists(DATA_DIR . '/config.php');
if ($config) {
    include_once('include.php');
    ModuleManager::load_modules();
}
if ($config && class_exists('Base_AclCommon')) {
    // Base_AclCommon::i_am_sa() unconditionally returns true whenever the
    // site's "anonymous_setup" convenience mode is on, for ANY visitor -
    // logged in or not - which both silently let a real non-admin account
    // through below and let SimpleLogin::form() skip the login form
    // entirely for an anonymous one, exposing this diagnostic page (DB
    // permissions, PHP settings, etc) with no gate at all. get_admin_level()
    // hits the DB directly with no such bypass, and force_login_form() is
    // the same login form minus it.
    if (Base_AclCommon::is_user()) {
        if (Base_AclCommon::get_admin_level() < 2) {
            die('Only super admin can access this page');
        }
    } else {
        $auth = SimpleLogin::force_login_page('EPESI Compatibility Check');
        if ($auth) {
            print($auth);
            die();
        }
    }
}

require_once('setuptheme/SetupSmarty.php');
require_once('include/compatibility_check.php');

$checks = array();

// Mutating (creates/drops a scratch table) - only meaningful once a live DB
// connection exists, so it's kept separate from the read-only environment
// checks that ConfigInfo.php (admin panel) also uses.
if ($config) {
	$checks[] = CompatibilityCheck::database_permission_check();
	$db_settings = CompatibilityCheck::database_settings_check();
	if ($db_settings) $checks[] = $db_settings;
}

$checks = array_merge($checks, CompatibilityCheck::environment_checks());

// §59 — advisory: modules recorded in the DB whose code is missing from this build
// (premium/custom). Read-only; blocks nothing here — the real gate is in update.php.
$orphaned = array();
if ($config && class_exists('ModuleManager') && method_exists('ModuleManager', 'get_orphaned_modules')) {
	$orphaned = ModuleManager::get_orphaned_modules();
}

$html = SetupSmarty::render('check_results.tpl', array(
	'checks' => $checks,
	'orphaned' => $orphaned,
));

if ($fullscreen) {
	if (class_exists('Utils_FrontPageCommon'))
		Utils_FrontPageCommon::display('EPESI Compatibility check', $html);
	else
		// $config is false here (app not installed yet) - __() isn't guaranteed
		// defined this early unless a caller (setup.php) already required
		// LangCommon_0.php itself, so this stays a plain literal.
		print(SetupSmarty::render_page('EPESI Compatibility check', $html));
} else {
	print($html);
}

?>
