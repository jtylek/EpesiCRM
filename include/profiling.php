<?php
/**
 * Runtime switches for the SQL / module-time debug panel.
 *
 * MODULE_TIMES and SQL_TIMES used to be read directly at every measurement point, so the
 * only way to profile anything was to edit data/config.php, reload, and remember to put
 * it back - a global change, for every user of the install, that outlives the session
 * that wanted it. On a machine where more than one person (or more than one agent
 * session) works against the same tree, that is not a private act. See
 * AI-shared/optimization-plan-opus.md item B3 / 2.3.
 *
 * The constants stay exactly as they were and remain the *default*: an install that sets
 * SQL_TIMES=1 in config.php behaves as it always did, including for requests that have no
 * session at all (CLI, cron). What is new is that a super-admin can turn either panel on
 * for their **own session only**, from Administration -> PHP & SQL Errors, and it goes
 * away when they log out.
 *
 * Read these as Profiling::$sql / Profiling::$modules, never the constants - a constant
 * read would silently ignore the session override.
 *
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Profiling {
	/** Collect per-query timings (the "SQL queries" panel). */
	public static $sql = false;
	/** Collect per-module render timings (the "Modules load times" panel). */
	public static $modules = false;

	/** Session key. Deliberately not under $_SESSION['client'] - this is a property of
	 *  the browser session, not of one client id / tab. */
	const SESSION_KEY = 'epesi_profiling';

	public static function init() {
		self::$sql = (bool) SQL_TIMES;
		self::$modules = (bool) MODULE_TIMES;
	}

	/**
	 * Apply the per-session override, if any. Called once from include.php after the
	 * session exists. A session can only ever turn a panel *on* that config.php left off,
	 * or off that config.php left on - both directions are useful, and neither escapes
	 * the session.
	 */
	public static function apply_session_override() {
		if (empty($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) return;
		$o = $_SESSION[self::SESSION_KEY];
		if (isset($o['sql'])) self::$sql = (bool) $o['sql'];
		if (isset($o['modules'])) self::$modules = (bool) $o['modules'];
	}

	/**
	 * Store the override for this session. Passing null for a flag drops the override,
	 * falling back to the config constant.
	 *
	 * Deliberately does NOT touch the request in flight. Both panels time things in pairs -
	 * `if (flag) $t = microtime(true)` early, `if (flag) ... - $t` later (module.php:1067 and
	 * :1124, epesi.php:275/287) - so flipping the flag between the two halves leaves the
	 * second one reading a variable the first never set. That is an E_WARNING, and under
	 * error.php's REPORT_ALL_ERRORS the first warning of a request blanks the whole module's
	 * output. Turning profiling on is not worth a chance of blanking the screen that turned
	 * it on. It takes effect from the next request, which is what the admin form says.
	 */
	public static function set_session_override($sql, $modules) {
		$o = array();
		if ($sql !== null) $o['sql'] = (bool) $sql;
		if ($modules !== null) $o['modules'] = (bool) $modules;
		if ($o) $_SESSION[self::SESSION_KEY] = $o;
		else unset($_SESSION[self::SESSION_KEY]);
	}

	/** What config.php asked for, ignoring any session override - for the admin form. */
	public static function config_defaults() {
		return array('sql' => (bool) SQL_TIMES, 'modules' => (bool) MODULE_TIMES);
	}
}

Profiling::init();
