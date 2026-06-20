<?php
/**
 * Config file.
 * 
 * All commented out defines are default values as they were
 * during the installation process. Default values may change after an update,
 * but your config file will remain as it was. If you want to know
 * current default values please look at file include/config.php
 *
 * This file contains database configuration.
 */
defined('_VALID_ACCESS') || die('Direct access forbidden');

/**
 * Address of SQL server.
 */
define('DATABASE_HOST','localhost');

/**
 * User to log in to SQL server.
 */
define('DATABASE_USER','epesi82_test');

/**
 * User password to authorize SQL server.
 */
define('DATABASE_PASSWORD','epesi82_test');

/**
 * Database to use.
 */
define('DATABASE_NAME','epesi82_test');

/**
 * Database driver.
 */
define('DATABASE_DRIVER','mysqli');

/*
 * Turns on transfer reduction: not everything is sent to the client
 */
//define('REDUCING_TRANSFER',1);

/*
 * A lot of debug info, starting with what modules are changed, what module variables are set... etc.
 */
//define('DEBUG',0);

/*
 * Show module loading time ...  = module + all children times
 */
//define('MODULE_TIMES',0);

/*
 * Show queries execution time.
 */
//define('SQL_TIMES',0);

/*
 * If you have got good server, but poor connection, turn it on.
 */
//define('STRIP_OUTPUT',0);

/*
 * Display errors on page.
 */
//define('DISPLAY_ERRORS',1);

/*
 * Notify all errors, including E_NOTICE, etc. Developer should use it!
 */
//define('REPORT_ALL_ERRORS',0);

/*
 * Compress history
 */
//define('GZIP_HISTORY',1);

/*
 * Compress HTTP output
 */
//define('MINIFY_ENCODE',1);

/*
 * Apply sources minifying algorithms.
 *
 * If enabled CPU usage may raise, but amount
 * of transferred data is smaller.
 */
//define('MINIFY_SOURCES',0);

/*
 * Show donation links in EPESI
 */
//define('SUGGEST_DONATION',1);

/*
 * automatically check for new version
 */
//define('CHECK_VERSION',1);

/*
 * Disable some administrator preferences.
 */
//define('DEMO_MODE',0);

define('FILE_SESSION_DIR','/tmp');
define('INSTALLATION_ID','305a06cc71d59552f64f5c257c1e2e7f');


define('DIRECTION_RTL','0');
define('EPESI_URL','http://localhost/epesi_php8.2');
?>