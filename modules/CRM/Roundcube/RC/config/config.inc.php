<?php
$d = getcwd();
defined("_VALID_ACCESS") || define("_VALID_ACCESS", true);
chdir(dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))));
define('SET_SESSION',false);
$CID = isset($_GET['ECID']) ? $_GET['ECID'] : false;
define('CID', $CID);
define('READ_ONLY_SESSION',isset($_GET['_action']) && $_GET['_action']=='plugin.epesi_archive'?false:true);
error_reporting(E_ALL & ~(E_STRICT | E_NOTICE | E_DEPRECATED));

require_once('vendor/autoload.php');
require_once('include/data_dir.php');
require_once('include/config.php');
require_once('include/database.php');
require_once('include/session.php'); // load to get class in runtime
require_once('include/variables.php');
global $E_SESSION,$E_SESSION_ID;
$E_SESSION_ID = $_COOKIE[session_name()];
if(!$E_SESSION_ID)
    $E_SESSION_ID = $_REQUEST[session_name()];

$E_SESSION = EpesiSession::get($E_SESSION_ID);

chdir($d);
$data_dir = EPESI_LOCAL_DIR.'/'.DATA_DIR.'/CRM_Roundcube/tmp/';
$log_dir = EPESI_LOCAL_DIR.'/'.DATA_DIR.'/CRM_Roundcube/log/';
if(!file_exists($data_dir))
    mkdir($data_dir);
if(!file_exists($log_dir))
    mkdir($log_dir);

try {
    if(!isset($E_SESSION['user'])) {
        throw new Exception('Not logged');
    }

    if(isset($_GET['_autologin_id'])) {
        $id = $_GET['_autologin_id'];
        setcookie("rc_account$CID",$id);
    } elseif(isset($_COOKIE["rc_account$CID"])) {
        $id = $_COOKIE["rc_account$CID"];
    } else {
        throw new Exception('Forbidden');
    }

    if(!is_numeric($id)) {
        throw new Exception('Invalid account id');
    }

    global $account;
    $account = DB::GetRow('SELECT * FROM rc_accounts_data_1 WHERE id=%d AND active=1',array($id));
    if($E_SESSION['user']!==$account['f_epesi_user']) {
        throw new Exception('Access Denied');
    }
} catch (Exception $ex) {
    header("Cache-Control: private, no-cache, no-store, must-revalidate, post-check=0, pre-check=0");
    header("Pragma: no-cache");
    header("Expires: ".gmdate("D, d M Y H:i:s")." GMT");
    header("Last-Modified: ".gmdate("D, d M Y H:i:s")." GMT");
    die($ex->getMessage());
}

/*
 +-----------------------------------------------------------------------+
 | Local configuration for the Roundcube Webmail installation.           |
 |                                                                       |
 | This is a sample configuration file only containing the minumum       |
 | setup required for a functional installation. Copy more options       |
 | from defaults.inc.php to this file to override the defaults.          |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2013, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 +-----------------------------------------------------------------------+
*/

$config = array();

// Database connection string (DSN) for read+write operations
// Format (compatible with PEAR MDB2): db_provider://user:password@host/database
// Currently supported db_providers: mysql, pgsql, sqlite, mssql or sqlsrv
// For examples see http://pear.php.net/manual/en/package.database.mdb2.intro-dsn.php
// NOTE: for SQLite use absolute path: 'sqlite:////full/path/to/sqlite.db?mode=0646'
$config['db_dsnw'] = (DB::is_mysql()?'mysql':'pgsql').'://'.DATABASE_USER.':'.DATABASE_PASSWORD.'@'.DATABASE_HOST.'/'.DATABASE_NAME;
$config['db_prefix'] = 'rc_';

// The mail host chosen to perform the log-in.
// Leave blank to show a textbox at login, give a list of hosts
// to display a pulldown menu or set one host as string.
// To use SSL/TLS connection, enter hostname with prefix ssl:// or tls://
// Supported replacement variables:
// %n - hostname ($_SERVER['SERVER_NAME'])
// %t - hostname without the first part
// %d - domain (http hostname $_SERVER['HTTP_HOST'] without the first part)
// %s - domain name after the '@' from e-mail address provided at login screen
// For example %n = mail.domain.tld, %t = domain.tld
$config['default_host'] = ($account['f_security']?$account['f_security'].'://':'').$account['f_server'];
$config['default_port'] = $account['f_security']=='ssl'?993:143;
$config['imap_delimiter'] = ($account['f_imap_delimiter']?$account['f_imap_delimiter']:null);
$config['imap_ns_personal'] = ($account['f_imap_root']?$account['f_imap_root']:null);
$config['imap_cache'] = (MEMCACHE_SESSION_SERVER && class_exists('Memcache'))?'memcache':'db';

// SMTP server host (for sending mails).
// To use SSL/TLS connection, enter hostname with prefix ssl:// or tls://
// If left blank, the PHP mail() function is used
// Supported replacement variables:
// %h - user's IMAP hostname
// %n - hostname ($_SERVER['SERVER_NAME'])
// %t - hostname without the first part
// %d - domain (http hostname $_SERVER['HTTP_HOST'] without the first part)
// %z - IMAP domain (IMAP hostname without the first part)
// For example %n = mail.domain.tld, %t = domain.tld
$config['smtp_server'] = ($account['f_smtp_security']?$account['f_smtp_security'].'://':'').$account['f_smtp_server'];

// SMTP port (default is 25; use 587 for STARTTLS or 465 for the
// deprecated SSL over SMTP (aka SMTPS))
$config['smtp_port'] = $account['f_smtp_security']=='ssl'?465:25;

// SMTP username (if required) if you use %u as the username Roundcube
// will use the current username for login
$config['smtp_user'] = $account['f_smtp_auth']?$account['f_smtp_login']:'';

// SMTP password (if required) if you use %p as the password Roundcube
// will use the current user's password for login
$config['smtp_pass'] = $account['f_smtp_auth']?$account['f_smtp_password']:'';

// provide an URL where a user can get support for this Roundcube installation
// PLEASE DO NOT LINK TO THE ROUNDCUBE.NET WEBSITE HERE!
$config['support_url'] =  (EPESI == 'EPESI') ? 'http://epe.si/support/' : Variable::get('whitelabel_url',false);

// Name your service. This is displayed on the login screen and in the window title
$config['product_name'] = EPESI . ' Mail';

// this key is used to encrypt the users imap password which is stored
// in the session record (and the client cookie if remember password is enabled).
// please provide a string of exactly 24 chars.
// YOUR KEY MUST BE DIFFERENT THAN THE SAMPLE VALUE FOR SECURITY REASONS
$config['des_key'] = 'epesil-!24ByteDESkey*Str';

// List of active plugins (in plugins/ directory)
$config['plugins'] = array(
    'epesi_init','epesi_autologon','epesi_autorelogon','epesi_addressbook','epesi_mailto','additional_message_headers','epesi_archive','markasjunk',
    'zipdownload',
);

// skin name: folder from skins/
$config['skin'] = 'classic';

$config['log_dir'] = $log_dir;
$config['temp_dir'] = $data_dir;
$config['session_auth_name'] = 'roundcube_sessauth_ecid' . $CID;
$config['session_name'] = "roundcube_sessid_ecid" . $CID;
$config['session_lifetime'] = 480;
$config['session_storage'] = (MEMCACHE_SESSION_SERVER && class_exists('Memcache'))?'memcache':'db';
$config['memcache_hosts'] = (MEMCACHE_SESSION_SERVER && class_exists('Memcache'))?array(MEMCACHE_SESSION_SERVER):null; // e.g. array( 'localhost:11211', '192.168.1.12:11211', 'unix:///var/tmp/memcached.sock' );
$config['default_charset'] = 'UTF-8';
$config['htmleditor'] = 1;
$config['preview_pane'] = true;
$config['reply_mode'] = 1;
$config['zipdownload_charset'] = 'UTF-8';

$config['smtp_conn_options'] = array(
  'ssl'         => array(
     'verify_peer'  => false
   ),
 );
$config['imap_conn_options'] = array(
  'ssl'         => array(
     'verify_peer'  => false
   ),
 );
