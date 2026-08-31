<?php
/**
 * @author Janusz Tylek
 * @version 2.0
 * @copyright Copyright &copy; 2026, Janusz Tylek
 * @license MIT
 * @package epesi-base
 */
if (version_compare(phpversion(), '7.0.0')==-1)
	error_reporting(E_ALL); //all without notices
elseif (PHP_VERSION_ID < 80400)
	error_reporting(E_ALL & ~E_STRICT & ~E_DEPRECATED);
else
	error_reporting(E_ALL & ~E_DEPRECATED); // E_STRICT itself deprecated to reference since PHP 8.4
ob_start();
ini_set('arg_separator.output','&');
@define('SYSTEM_TIMEZONE',date_default_timezone_get());
date_default_timezone_set(SYSTEM_TIMEZONE);

define('_VALID_ACCESS',1);
require_once('include/data_dir.php');
require_once('vendor/autoload.php');
//added for new openpsa/quickform installation, kt June 19, 2026
require_once('modules/Libs/QuickForm/requires.php');
require_once('setuptheme/SetupSmarty.php');

// EpesiArray(Smarty)'s startForm()/renderError() call the global load_js()/
// eval_js() helpers (include/misc.php), which normally proxy to the real
// Epesi asset-queue class (include/epesi.php) - not loaded here, since this
// script runs before the app is installed/bootstrapped (no DB yet). This
// page's own shell (setuptheme/shell.tpl) already hardcodes every CSS/JS it
// needs, so there's nothing for load_js()/load_css()/eval_js() to queue.
// Skipped entirely for the ?check=1 request (the one that follows a
// successful write_config()): that branch requires check.php, which by then
// finds config.php in place and loads the REAL include.php bootstrap - this
// stub must not have already claimed the class name, or that fatals with
// "Cannot declare class Epesi, because the name is already in use". That
// branch never touches the QuickForm renderer, so it never needed the stub
// anyway.
if (!isset($_GET['check']) && !class_exists('Epesi')) {
	class Epesi {
		static function load_js($u, $loader = null) {}
		static function load_css($u, $loader = null) {}
		static function js($u, $del_on_loc = true) {}
		static function escapeJS($str, $double = true, $single = true) { return addslashes($str); }
	}
}

/* You can predefine user, password, database name, etc in file defined by var below.
Example installation_config.php file:
<?php
$CONFIG = array('user' => 'db_username', 'password' => 'db_password', 'db' => 'database_name', 'host' => 'db_server_host',
    'newdb' => 0,  // or 1 to create new database
    'engine' => 'mysqli',  // or 'postgres' for PostgreSQL
    'direction' => 0  // Left to Right, or 1 for Right to Left
);
?>
*/
$fast_install_filename = "installation_config.php";

// translation support
$install_lang_dir = 'modules/Base/Lang/lang';
$ls_langs = scandir($install_lang_dir);
$langs = array();
foreach ($ls_langs as $entry)
    if (preg_match('/.\.php$/i', $entry)) {
        $lang = substr($entry, 0, -4);
        $langs[$lang] = $lang;
    }
$install_lang_code = & $_GET['install_lang'];
require 'include/misc.php';
require 'include/module_primitive.php';
require 'include/module.php';
require 'include/module_common.php';
require 'modules/Base/Lang/LangCommon_0.php';
$install_lang_load = $langs[$install_lang_code] ?? 'en'; // fallback to english
define('FORCE_LANG_CODE', $install_lang_load);
include "{$install_lang_dir}/{$install_lang_load}.php";
// end translations load

// Renders the given fragment inside setuptheme/shell.tpl and ends the request -
// every screen in this script produces its $body this way instead of the old
// header()/print()/footer() shutdown-function dance (see git history), so each
// step is one self-contained AdminLTE-themed page. $step selects which entry
// in $steps (if any) gets the "current"/"done" marker in the wizard's progress bar.
function setup_page($title, $body, $step = null) {
    global $install_lang_load;
    $order = array('language', 'license', 'database' , 'compat');
    $labels = array(
        'language' => array('label' => __('Language'), 'icon' => 'bi-translate'),
        'license'  => array('label' => __('License'), 'icon' => 'bi-file-earmark-text'),
        'database' => array('label' => __('Database'), 'icon' => 'bi-database'),
		'compat'   => array('label' => __('Compatibility'), 'icon' => 'bi-check2-square'),
    );
    $steps = array();
    if (isset($install_lang_load)) {
        $reached = $step ? array_search($step, $order) : -1;
        foreach ($order as $i => $key) {
            $steps[] = array(
                'label' => $labels[$key]['label'],
                'icon' => $labels[$key]['icon'],
                'current' => $key === $step,
                'done' => $reached !== false && $i < $reached,
            );
        }
    }
    die(SetupSmarty::render_page($title, $body, array('steps' => $steps)));
}

function lang_flag_file($code) {
    $file = 'modules/Base/Lang/theme/flags/'.$code.'.svg';
    if (!file_exists($file))
        $file = 'modules/Base/Lang/theme/flag_placeholder.png';
    return $file;
}

/**
 * Check access to working directories
 */

if(file_exists('easyinstall.php')){
	unlink('easyinstall.php');
}

// language selection form
if (!isset($install_lang_code)) {
	$labels = Base_LangCommon::get_base_languages();
	$list = array();
	foreach ($langs as $l) {
		if ($l === 'en') continue;
		$list[$l] = $labels[$l] ?? $l;
	}
	asort($list);
	$list = array_merge(array('en'=>$labels['en']), $list);

	$langs_list = array();
	foreach ($list as $l => $label) {
		$langs_list[] = array('href' => '?install_lang='.$l, 'label' => $label, 'flag' => lang_flag_file($l));
	}

	$body = SetupSmarty::render('language_select.tpl', array(
		'langs_list' => $langs_list,
	));
	setup_page(__('Select Language'), $body, 'language');
}

if (isset($_GET['check'])) {
	ob_start();
	require_once('check.php');
	$check_html = ob_get_clean();
	$continue_url = 'index.php?install_lang='.$install_lang_load;
	$check_html .= '<div class="text-center mt-3"><a class="btn btn-primary" href="'.htmlspecialchars($continue_url).'">' . __('Continue with installation') . '</a></div>';
	setup_page(__('Compatibility check'), $check_html, 'compat');
}

if(trim(ini_get("safe_mode")))
	setup_page(__('Error'), SetupSmarty::render('message.tpl', array(
		'message' => __('You cannot use Epesi with PHP safe mode turned on - please disable it. Please notice this feature is deprecated since PHP 5.3 and is removed in PHP 5.4.'),
	)));

if(file_exists(DATA_DIR.'/config.php'))
	setup_page(__('Error'), SetupSmarty::render('message.tpl', array(
		'message' => __('Cannot write into %s file. Please delete this file.', array(DATA_DIR.'/config.php')),
	)));

if(!is_writable(DATA_DIR))
	setup_page(__('Error'), SetupSmarty::render('message.tpl', array(
		'message' => __('Cannot write into "%s" directory. Please fix privileges.', array(DATA_DIR)),
	)));

if (isset($_GET['tos1']) && $_GET['tos1'] && isset($_GET['tos2']) && $_GET['tos2'] && isset($_GET['tos3']) && $_GET['tos3'] && isset($_GET['tos4']) && $_GET['tos4']) {
    $_GET['license'] = 1;
    unset($_GET['tos1']);
    unset($_GET['tos2']);
    unset($_GET['tos3']);
    unset($_GET['tos4']);
}

if(!isset($_GET['license'])) {
	$license_html = str_replace('{YEAR}', date('Y'), read_doc_file('license'));

	$form = new HTML_QuickForm('licenceform','get');
	$form -> addElement('checkbox','tos1','',__('I will not remove the <strong>"Copyright by Janusz Tylek and Karina Tylek"</strong> notice as required by the MIT license.'), array('class'=>'form-check-input'));
	$form -> addElement('checkbox','tos2','',__('I will not remove <strong>"Made with Epesi"</strong> logo and the link from the application login screen or the toolbar.'), array('class'=>'form-check-input'));
	$form -> addElement('checkbox','tos3','',__('I will not remove <strong>"Support -> About"</strong> credit page from the application menu.'), array('class'=>'form-check-input'));
	$form -> addElement('checkbox','tos4','',__('I will not remove or rename <strong>"Epesi Store"</strong> links from the application.'), array('class'=>'form-check-input'));
	foreach($_GET as $f=>$v) {
        if (!str_starts_with($f, 'tos') && $f != 'submitted' && $f != 'submit')
            $form->addElement('hidden',$f,$v);
    }
	$form->addElement('hidden','submitted',1);
	$form -> addRule('tos1', __('Field required'), 'required');
	$form -> addRule('tos2', __('Field required'), 'required');
	$form -> addRule('tos3', __('Field required'), 'required');
	$form -> addRule('tos4', __('Field required'), 'required');
	isset($_GET['submitted']) && $_GET['submitted'] && $form->validate();
	$form -> addElement('submit', 'submit', __('Next'), array('class'=>'btn btn-primary'));
	$form->setRequiredNote('<span class="required_note_star">*</span> <span class="required_note">'.__('denotes required field').'</span>');

	require_once('include/EpesiSmartyRenderer.php');
	$renderer = new EpesiSmartyRenderer();
	$form->accept($renderer);
	$form_data = $renderer->toArray();
	// toArray()'s 'errors' key only gets a field's entry when that field
	// actually fails validation (see EpesiArray::_elementToArray()) - Smarty's
	// dot notation ($form_data.errors.tos1) has no isset guard of its own and
	// throws a PHP 8 warning on a plain first page load, same class of bug
	// already fixed in QuickForm_0.php's own 'header' key (see [[report-all-errors-exits-on-warning]]).
	foreach (array('tos1','tos2','tos3','tos4') as $f) $form_data['errors'][$f] ??= '';

	$body = SetupSmarty::render('license.tpl', array(
		'license_html' => $license_html,
		'form_data' => $form_data,
	));
	setup_page(__('License Agreement'), $body, 'license');
} elseif(!isset($_GET['htaccess'])) {
	$check = check_htaccess();
	if ($check['ok']) {
		$_GET['htaccess'] = 1;
	} else {
		$continue_url = 'setup.php?' . http_build_query(array('license'=>1,'htaccess'=>1,'install_lang'=>$install_lang_load));
		$body = SetupSmarty::render('message.tpl', array(
			'heading' => __('Hosting compatibility'),
			'message' => $check['message'],
			'pre' => $check['pre'] ?? null,
			'pre_collapsed' => $check['pre_collapsed'] ?? false,
			'pre_label' => $check['pre_label'] ?? null,
			'link_href' => $continue_url,
			'link_text' => __('Ok'),
		));
		setup_page('', $body, 'database');
	}
}
if(isset($_GET['htaccess']) && isset($_GET['license'])) {
	$form = new HTML_QuickForm('serverform','post',$_SERVER['PHP_SELF'].'?'.http_build_query($_GET));
	$form -> addElement('header', 'db_header', __('Database server settings'));
	$form -> addElement('text', 'host', __('Database server address'), array('class'=>'form-control'));
	$form -> addRule('host', __('Field required'), 'required');
	$form -> addElement('text', 'port', __('Custom database port'), array('class'=>'form-control'));
	$form -> addElement('select', 'engine', __('Database engine'), array('postgres'=>'PostgreSQL', 'mysqli'=>'MySQL'), array('class'=>'form-select'));
	$form -> addRule('engine', __('Field required'), 'required');
	$form -> addElement('text', 'user', __('Database server user'), array('class'=>'form-control'));
	$form -> addRule('user', __('Field required'), 'required');
	$form -> addElement('password', 'password', __('Database server password'), array('class'=>'form-control'));
	$form -> addRule('password', __('Field required'), 'required');
	$form -> addElement('text', 'db', __('Database name'), array('class'=>'form-control'));
	$form -> addRule('db', __('Field required'), 'required');
    $create_db_warn_msg = __('WARNING: Make sure you have CREATE access level to do this!');
	$form -> addElement('select', 'newdb', __('Create new database'),
            array(0 => __('No'), 1 => __('Yes')),
            array('class'=>'form-select', 'onChange' => 'if(this.value==1) alert("' . $create_db_warn_msg . '","warning");'));
	$form -> addRule('newdb', __('Field required'), 'required');
	$form -> addElement('header', 'other_header', __('Other settings'));
	$form -> addElement('select', 'direction', __('Text direction'),
            array(0 => __('Left to Right'), 1 => __('Right to Left')), array('class'=>'form-select'));

	$form -> addElement('submit', 'submit', __('Next'), array('class'=>'btn btn-primary'));
	$form -> setDefaults(array('engine'=>'mysqli','db'=>'epesi','host'=>'localhost'));

	$fast_install_msg = null;
    if (file_exists($fast_install_filename)) {
        include $fast_install_filename;
        if (isset($CONFIG) && is_array($CONFIG)) {
            $fast_install_msg = __('Some fields were filled to make installation easier.');
            foreach ($CONFIG as $key => $value) {
                $form->setDefaults(array($key => $value));
                $form->getElement($key)->freeze();
            }
        }
    }
	$form->setRequiredNote('<span class="required_note_star">*</span> <span class="required_note">'.__('denotes required field').'</span>');

	$db_error = null;
	if($form -> validate()) {
		$engine = $form -> exportValue('engine');
		$direction = $form -> exportValue('direction');
		$other = array('direction'=>$direction);
        $host = $form -> exportValue('host');
		$port = $form->exportValue('port');
        $user = $form -> exportValue('user');
        $pass = $form -> exportValue('password');
        $dbname = $form -> exportValue('db');
        $new_db = $form->exportValue('newdb');
		switch($engine) {
			case 'postgres':
				if(!function_exists('pg_connect')) {
				    $db_error = __('Please enable postgresql extension in php.ini.');
				} else {
					$port_def = $port ? " port=$port" : '';
					$link = pg_connect("host=$host user=$user password=$pass dbname=postgres" . $port_def);
					if(!$link) {
	 					$db_error = __('Could not connect.');
					} else {
						if ($port) {
							$host .= ':' . $port;
						}
						if ($new_db == 1) {
							$sql = 'CREATE DATABASE "'.$dbname.'"';
							if (pg_query($link, $sql)) {
				   				write_config($host,$user,$pass,$dbname,$engine,$other);
							} else {
		 		  				$db_error = __('Error creating database') . ': ' . pg_last_error();
		 	  				}
			   				pg_close($link);
						} else {
							include_once('vendor/adodb/adodb-php/adodb.inc.php');
							$ado = & NewADOConnection('postgres');
							if(!@$ado->Connect($host,$user,$pass,$dbname)) {
								$db_error = __('Database does not exist.') . '<br />' . __('Please create the database first or select option')
                                        . ':<br /><b>' . __('Create new database') . '</b>';
							} else {
								write_config($host, $user, $pass, $dbname, $engine,$other);
							}
						}
					}
				}
			break;
            case 'mysqli':
                $mysqli_loaded = class_exists('mysqli');
				if ($port) {
					$host .= ':' . $port;
				}
                $connect_ok = false;
                $driver_error = null;
                if ($mysqli_loaded) {
                    // PHP 8.1+ changed mysqli's default report mode to throw
                    // mysqli_sql_exception on connect failure instead of just
                    // setting connect_errno - without this, an unreachable host
                    // or bad credentials fatals here with a raw PHP exception
                    // dump instead of ever reaching the connect_errno check below.
                    // With reporting off, a failed connect instead falls back
                    // to a plain E_WARNING (visible as raw text above the
                    // themed page) - @ suppresses it since connect_errno below
                    // already surfaces the same failure through db_connect_error.tpl.
                    mysqli_report(MYSQLI_REPORT_OFF);
                    $link = @new mysqli($host, $user, $pass);
                    if ($link->connect_errno) {
                        $driver_error = __('Could not connect') . " (Errno: {$link->connect_errno}): " . $link->connect_error;
                    } else {
                        $connect_ok = true;
                    }
                }
                if (!$connect_ok) {
                    // Distinguishes "server unreachable/down" from "driver
                    // missing" from "reachable but auth/credentials wrong" -
                    // the raw driver error alone doesn't tell a first-time
                    // installer which of those they're looking at.
                    $diag = mysql_connect_diagnostics($host);
                    $body = SetupSmarty::render('db_connect_error.tpl', array(
                        'mysqli_loaded'    => $mysqli_loaded,
                        'server_reachable' => $diag['reachable'],
                        'diag_host'        => $diag['host'],
                        'diag_port'        => $diag['port'],
                        'driver_error'     => $driver_error,
                        'retry_url'        => $_SERVER['PHP_SELF'] . '?' . http_build_query(array('license' => 1, 'htaccess' => 1, 'install_lang' => $install_lang_load)),
                    ));
                    setup_page(__('Database connection failed'), $body, 'database');
                }
                if ($new_db == 1) {
                    $sql = 'CREATE DATABASE `' . $dbname . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
                    if ($link->query($sql)) {
                        write_config($host, $user, $pass, $dbname, $engine, $other);
                    } else {
                        $db_error = __('Error creating database: ') . $link->error;
                    }
                    $link->close();
                } else {
                    $result = $link->select_db($dbname);
                    if (!$result) {
                        $db_error = __('Database does not exist') . ': ' . $link->error . '<br />' . __('Please create the database first or select option')
                        . ':<br /><b>' . __('Create new database') . '</b>';
                    } else {
                        write_config($host, $user, $pass, $dbname, $engine, $other);
                    }
                }
                break;
		}
	}

	require_once('include/EpesiSmartyRenderer.php');
	$renderer = new EpesiSmartyRenderer();
	$form->accept($renderer);
	$form_data = $renderer->toArray();
	// see the identical backfill on the license form above.
	foreach (array('host','engine','user','password','db') as $f) $form_data['errors'][$f] ??= '';

	$body = SetupSmarty::render('db_config.tpl', array(
		'form_data' => $form_data,
		'fast_install_msg' => $fast_install_msg,
		'db_error' => $db_error,
	));
	setup_page(__('Configuration'), $body, 'database');
}

///////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////
function check_htaccess() {
	$dir = trim(dirname($_SERVER['SCRIPT_NAME']),'/');
	$epesi_dir = '/'.$dir.($dir?'/':'');
	$protocol = (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS'])!== "off") ? 'https://' : 'http://';
	$test_url = $protocol.$_SERVER['HTTP_HOST'].$epesi_dir.'data/test.php';
	file_put_contents('data/test.php','<?php'."\n".'print("OK"); ?>');

    	copy('htaccess.txt','data/.htaccess');

	if(ini_get('allow_url_fopen'))
		$ret = @file_get_contents($test_url);
	elseif (extension_loaded('curl')) { // Test if curl is loaded
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //Set curl to return the data instead of printing it to the browser.
		curl_setopt($ch, CURLOPT_URL, $test_url);
		$ret = curl_exec($ch);
		curl_close($ch);
	}

	if(!isset($ret)) {
		unlink('data/.htaccess');
		unlink('data/test.php');
		return array('ok'=>false,
			'message'=>__("Epesi couldn't check whether your web server supports its suggested .htaccess file — but this is optional hardening, not something Epesi needs to run, so it's safe to click Continue.") . ' ' . __('Two of its four protections (security response headers and a minimum PHP memory limit) are applied by Epesi itself either way. The rest — hiding folder listings and your .git/.svn files — needs the .htaccess file below; add it yourself, or ask your host, if you want it.'),
			'pre'=>file_get_contents('htaccess.txt'),
			'pre_collapsed'=>true,
			'pre_label'=>__('Show suggested .htaccess file'));
	}
	if($ret!=="OK") {
		file_put_contents('data/.htaccess',"Options -Indexes\nSetEnv PHPRC ".dirname(__FILE__)."\n");
		if(ini_get('allow_url_fopen'))
			$ret = @file_get_contents($test_url);
		elseif (extension_loaded('curl')) { // Test if curl is loaded
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //Set curl to return the data instead of printing it to the browser.
			curl_setopt($ch, CURLOPT_URL, $test_url);
			$ret = curl_exec($ch);
			curl_close($ch);
		}
		if($ret!=="OK") {
			unlink('data/.htaccess');
			unlink('data/test.php');
			return array('ok'=>false,
				'message'=>__("Your web server doesn't support Epesi's suggested .htaccess file — but this is optional hardening, not something Epesi needs to run, so it's safe to click Continue.") . ' ' . __('Two of its four protections (security response headers and a minimum PHP memory limit) are applied by Epesi itself either way. The rest — hiding folder listings and your .git/.svn files — needs web server-level configuration; ask your host, or use the file below as a starting point if you manage your own server.'),
				'pre'=>file_get_contents('htaccess.txt'),
				'pre_collapsed'=>true,
				'pre_label'=>__('Show default .htaccess file'));
		}
	}
	if(!is_writable('.')) {
		unlink('data/test.php');
		$pre_content = file_get_contents('data/.htaccess');
		unlink('data/.htaccess');
		return array('ok'=>false,
			'message'=>__("Your web server supports Epesi's .htaccess file, but the installer doesn't have permission to create it automatically. Copy the text below into a file named .htaccess in your Epesi root folder."),
			'pre'=>$pre_content);
	}
	unlink('data/test.php');
	rename('data/.htaccess','.htaccess');
	return array('ok'=>true);
}

// Reachability probe used by the mysqli "could not connect" screen - separate
// from the actual mysqli connect attempt so it still runs even when the
// mysqli extension itself isn't loaded (a plain TCP socket needs no driver).
function mysql_connect_diagnostics($host) {
    $port = 3306;
    if (str_contains($host, ':')) {
        list($host, $maybe_port) = explode(':', $host, 2);
        if (ctype_digit($maybe_port)) $port = (int)$maybe_port;
    }
    $sock = @fsockopen($host, $port, $errno, $errstr, 3);
    $reachable = (bool)$sock;
    if ($sock) fclose($sock);
    return array('host' => $host, 'port' => $port, 'reachable' => $reachable);
}

function write_config($host, $user, $pass, $dbname, $engine, $other) {
    global $install_lang_load;
	$local_dir = dirname(str_replace('\\','/',__FILE__), 2);
	$script_filename = str_replace('\\','/',$_SERVER['SCRIPT_FILENAME']);
	$other_conf = '';
	if(strcmp($local_dir,substr($script_filename,0,strlen($local_dir)))) {
        $epesi_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        $other_conf .= "\n".'@define(\'EPESI_DIR\',\''. addcslashes($epesi_dir, '\'\\') .'\');';
    }
	$other_conf .= "\n".'define(\'DIRECTION_RTL\',\''.($other['direction']?'1':'0').'\');';

	$protocol = (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS'])!== "off") ? 'https://' : 'http://';
        $domain_name = '';
        if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST']) {
            $domain_name = $_SERVER['HTTP_HOST'];
        } else if (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME']) {
            $domain_name = $_SERVER['SERVER_NAME'];
        }
    if ($domain_name) {
        $url = $protocol . $domain_name . dirname($_SERVER['REQUEST_URI']);
        $url = str_replace('\\', '/', $url);
        $other_conf .= "\n" . 'define(\'EPESI_URL\',\'' . addcslashes($url, '\'\\') . '\');';
    }
	if (memcached_session_available()) {
	    $other_conf .= "\n".'define(\'SESSION_TYPE\',\'memcache\');';
	    $other_conf .= "\n".'define(\'MEMCACHE_SESSION_SERVER\',\'127.0.0.1:11211\');';
	} else {
	    // No reachable memcached server at install time - fall back to the
	    // database-backed session handler (EpesiSessionDBStorage) rather than
	    // leaving SESSION_TYPE unset, which would silently default to the
	    // file-based handler (include/config.php) instead. SQL survives a
	    // multi-server/load-balanced deployment the way file-based sessions
	    // don't, without requiring an extra service to install.
	    $other_conf .= "\n".'define(\'SESSION_TYPE\',\'sql\');';
	}
	$c = fopen(DATA_DIR.'/config.php', 'w');
	fwrite($c, '<?php
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
defined(\'_VALID_ACCESS\') || die(\'Direct access forbidden\');

/**
 * Address of SQL server.
 */
define(\'DATABASE_HOST\',\''.addcslashes($host, '\'\\').'\');

/**
 * User to log in to SQL server.
 */
define(\'DATABASE_USER\',\''.addcslashes($user, '\'\\').'\');

/**
 * User password to authorize SQL server.
 */
define(\'DATABASE_PASSWORD\',\''.addcslashes($pass, '\'\\').'\');

/**
 * Database to use.
 */
define(\'DATABASE_NAME\',\''.addcslashes($dbname, '\'\\').'\');

/**
 * Database driver.
 */
define(\'DATABASE_DRIVER\',\''.addcslashes($engine, '\'\\').'\');

/*
 * Turns on transfer reduction: not everything is sent to the client
 */
//define(\'REDUCING_TRANSFER\',1);

/*
 * A lot of debug info, starting with what modules are changed, what module variables are set... etc.
 */
//define(\'DEBUG\',0);

/*
 * Show module loading time ...  = module + all children times
 */
//define(\'MODULE_TIMES\',0);

/*
 * Show queries execution time.
 */
//define(\'SQL_TIMES\',0);

/*
 * If you have got good server, but poor connection, turn it on.
 */
//define(\'STRIP_OUTPUT\',0);

/*
 * Display errors on page.
 */
//define(\'DISPLAY_ERRORS\',1);

/*
 * Notify all errors, including E_NOTICE, etc. Developer should use it!
 */
//define(\'REPORT_ALL_ERRORS\',0);

/*
 * Compress history
 */
define(\'GZIP_HISTORY\',1);

/*
 * Compress HTTP output
 */
define(\'MINIFY_ENCODE\',1);

/*
 * Apply sources minifying algorithms.
 *
 * If enabled CPU usage may raise, but amount
 * of transferred data is smaller.
 */
define(\'MINIFY_SOURCES\',1);

/*
 * Poll for a shipped JS/CSS fix and prompt long-open tabs to reload (this
 * app never reloads index.php on its own, so a tab can otherwise keep
 * running stale JS indefinitely - see AI-shared/bug-patterns.md). Off by
 * default for fresh installs - a newer, less-battle-tested feature the
 * admin can opt into rather than one imposed on every install.
 */
define(\'ASSET_VERSION_CHECK\',0);

/*
 * Application cache driver, independent of where sessions are stored.
 *
 * \'auto\' (the default) uses memcached when MEMCACHE_SESSION_SERVER or
 * CACHE_SERVER names one, then falls back through APCu, Zend SHM, SQLite
 * and finally plain files. Pin one instead with memcached, memcache,
 * redis, predis, apcu, zendshm, sqlite or files; a pinned driver whose
 * extension is missing still degrades down the same chain rather than
 * breaking the install. CACHE_SERVER is host:port for the networked
 * drivers and defaults to MEMCACHE_SESSION_SERVER.
 */
//define(\'CACHE_TYPE\',\'auto\');
//define(\'CACHE_SERVER\',\'127.0.0.1:11211\');

/*
 * Show donation links in Epesi
 */
//define(\'SUGGEST_DONATION\',1);

/*
 * automatically check for new version
 */
//define(\'CHECK_VERSION\',1);

/*
 * Disable some administrator preferences.
 */
//define(\'DEMO_MODE\',0);

define(\'FILE_SESSION_DIR\',\''.str_replace('\\', '/', sys_get_temp_dir()).'\');
define(\'INSTALLATION_ID\',\''.md5(__FILE__ . strval(microtime(true))).'\');

'.$other_conf.'
?>');
	fclose($c);

	// index.php's own Smarty instance (rendering the outer page shell) needs
	// this directory the moment config.php exists, but Base_Theme's own
	// module install - which normally creates it - hasn't run yet at this
	// point in the flow (it happens later, during module installation).
	// On a completely fresh data/ directory (matching what .gitignore
	// excludes, or a from-scratch distribution package) this crashes with
	// a Smarty fatal before the user ever reaches that step.
	// include/config.php (which defines TEMP_DIR) isn't require'd until later
	// in this script (see below) - inline the same 'temp/'.DATA_DIR formula.
	$temp_dir = defined('TEMP_DIR') ? TEMP_DIR : 'temp/'.DATA_DIR;
	if (!is_dir($temp_dir.'/Base_Theme/compiled'))
		mkdir($temp_dir.'/Base_Theme/compiled', 0777, true);

	ob_start();
	ob_start('rm_config');

	//fill database
	clean_database();
	install_base();

	ob_end_flush();

	if(file_exists(DATA_DIR.'/config.php'))
		header("Location: setup.php?install_lang={$install_lang_load}&check=1");
	ob_end_flush();
}

// Checked at install time only (see write_config() above) - if a memcached
// server is reachable on the default port, session storage defaults to it
// instead of the file-based handler (see EpesiSessionMemcachedStorage /
// EpesiSession::$storageMap in include/session.php). Requires both the PHP
// extension and an actual reachable server - either alone would leave
// SESSION_TYPE pointed at a backend that can't be used on every request.
function memcached_session_available($host = '127.0.0.1', $port = 11211) {
	if (!extension_loaded('memcached') && !extension_loaded('memcache'))
		return false;
	$sock = @fsockopen($host, $port, $errno, $errstr, 1);
	if (!$sock)
		return false;
	fclose($sock);
	return true;
}

//////////////////////////////////////////////
function rm_config($x) {
	if($x) {
		unlink(dirname(__FILE__).'/'.DATA_DIR.'/config.php');
		clean_database();
	}
	return $x;
}

function clean_database() {
	require_once('include/config.php');
	require_once('include/database.php');
	$tables_db = DB::MetaTables();
	$tables = array();
	if(DB::is_mysql())
		DB::Execute('SET FOREIGN_KEY_CHECKS=0');
	if(DB::is_postgresql() && str_contains(DB::GetOne('SELECT version()'),'PostgreSQL 8.2')) {
    	    foreach ($tables_db as $t) {
	            $idxs = DB::Execute('SELECT t.tgargs as args FROM pg_trigger t,pg_class c,pg_proc p WHERE t.tgenabled AND t.tgrelid = c.oid AND t.tgfoid = p.oid AND p.proname = \'RI_FKey_check_ins\' AND c.relname = \''.strtolower($t).'\' ORDER BY t.tgrelid');
		    $matches = array(1=>array());
		    while ($i = $idxs->FetchRow()) {
		            $data = explode(chr(0), $i[0]);
			    $matches[1][] = $data[0];
		    }
		    $num_keys = count($matches[1]);
		    for ( $i = 0;  $i < $num_keys;  $i ++ )
		            DB::Execute('ALTER TABLE '.$t.' DROP CONSTRAINT '.$matches[1][$i]);
	}
																	    }
	foreach($tables_db as $t) {
		DB::DropTable($t);
	}
	if(DB::is_mysql())
		DB::Execute('SET FOREIGN_KEY_CHECKS=1');
}

function install_base() {
	require_once('include/config.php');
	require_once('include/database.php');

	@DB::Execute('ALTER DATABASE `'.DATABASE_NAME.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

	$ret = DB::CreateTable('modules',"name C(128) KEY,version I NOTNULL, priority I NOTNULL DEFAULT 0, state I NOTNULL DEFAULT 0");
	if($ret===false)
		die('Invalid SQL query - Setup module (modules table)');

	$ret = DB::CreateTable('cron',"func C(32) KEY,last I NOTNULL, running I1 NOTNULL DEFAULT 0, description C(255)");
	if($ret===false)
		die('Invalid SQL query - Setup cron (cron table)');

	$ret = DB::CreateTable('session',"name C(128) NOTNULL," .
			"expires I NOTNULL DEFAULT 0, data B",array('constraints'=>', PRIMARY KEY(name)'));
	if($ret===false)
		die('Invalid SQL query - Database module (session table)');

	$ret = DB::CreateTable('session_client',"session_name C(128) NOTNULL, client_id I2," .
			"data B",array('constraints'=>', FOREIGN KEY(session_name) REFERENCES session(name), PRIMARY KEY(client_id,session_name)'));
	if($ret===false)
		die('Invalid SQL query - Database module (session_client table)');

	$ret = DB::CreateTable('history',"session_name C(128) NOTNULL, page_id I, client_id I2," .
			"data B",array('constraints'=>', FOREIGN KEY(session_name) REFERENCES session(name), PRIMARY KEY(client_id,session_name,page_id)'));
	if($ret===false)
		die('Invalid SQL query - Database module (history table)');

	DB::CreateIndex('history__session_name__client_id__idx', 'history', 'session_name, client_id');

	$ret = DB::CreateTable('variables',"name C(128) KEY,value X");
	if($ret===false)
		die('Invalid SQL query - Database module (variables table)');

	$ret = DB::Execute("insert into variables values('default_module',%s)",array(serialize('FirstRun')));
	if($ret === false)
		die('Invalid SQL query - Setup module (populating variables)');

	$ret = DB::Execute("insert into variables values('version',%s)",array(serialize(EPESI_VERSION)));
	if($ret === false)
		die('Invalid SQL query - Setup module (populating variables)');

}
//////////////////////////////////////////////
function read_doc_file($file_basename, $suffix = 'html') {
    global $install_lang_load;
    $dir = 'docs/';
    $file = $dir . $file_basename . '.' . $suffix;  // default file
    $custom_file = $dir . "{$file_basename}_{$install_lang_load}.{$suffix}";
    if (file_exists($custom_file))
        $file = $custom_file;
    $fp = @fopen($file, 'r');
    if ($fp) {
        $content = fread($fp, filesize($file));
    }
    fclose($fp);
    return $content;
}

ob_end_flush();
?>
