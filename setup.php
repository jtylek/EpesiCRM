<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @license MIT
 * @package epesi-base
 */
if (version_compare(phpversion(), '5.4.0')==-1)
	error_reporting(E_ALL); //all without notices
else
	error_reporting(E_ALL & ~E_STRICT);
ob_start();
ini_set('arg_separator.output','&');
@define('SYSTEM_TIMEZONE',date_default_timezone_get());
date_default_timezone_set(SYSTEM_TIMEZONE);

define('_VALID_ACCESS',1);
require_once('include/data_dir.php');
require_once('modules/Libs/QuickForm/requires.php');

/* You can predefine user, password, database name, etc in file defined by var below.
Example installation_config.php file:
<?php
$CONFIG = array('user' => 'db_username', 'password' => 'db_password', 'db' => 'database_name', 'host' => 'db_server_host',
    'newdb' => 0,  // or 1 to create new database
    'engine' => 'mysqlt',  // or 'postgres' for PostgreSQL
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
$install_lang_load = isset($langs[$install_lang_code])
    ? $langs[$install_lang_code] : 'en'; // fallback to english
define('FORCE_LANG_CODE', $install_lang_load);
include "{$install_lang_dir}/{$install_lang_load}.php";
// end translations load

function set_header($str) {
	print('<script type="text/javascript">document.getElementById("setup_page_header").innerHTML="'.$str.'";</script>');
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
	  <meta content="text/html; charset=UTF-8" http-equiv="content-type">
	  <title><?php echo __("EPESI setup"); ?></title>
	  <link href="setup.css" type="text/css" rel="stylesheet"/>
</head>
<body>
		<table id="banner" border="0" cellpadding="0" cellspacing="0">
			<tr>
				<td class="image">&nbsp;</td>
				<td class="back" id="setup_page_header">&nbsp;</td>
				<td class="image back">&nbsp;</td>
			</tr>
		</table>
		<br>
		<center>
		<table id="main" border="0" cellpadding="0" cellspacing="0">
			<tr>
				<td>
<?php

function footer() {
?>
				</td>
			</tr>
		</table>
		</center>
		<br>
		<center>
		<span class="footer">Copyright &copy; <?php echo date('Y'); ?> &bull; <a href="http://www.telaxus.com">Telaxus LLC</a></span>
		<br>
		<p><a href="http://www.epe.si"><img src="images/epesi-powered.png" border="0"></a></p>
		</center>
</body>
</html>
<?php
}
register_shutdown_function('footer');

// language selection form
if (!isset($install_lang_code)) {
	$complete = Base_LangCommon::get_complete_languages();
	$labels = Base_LangCommon::get_base_languages();
	$list = array();
	foreach ($complete as $l) {
		$list[$l] = isset($labels[$l])?$labels[$l]:$l;
	}
	$rest = array();
	foreach ($langs as $l) {
		$rest[$l] = isset($labels[$l])?$labels[$l]:$l;
	}
	asort($list);
	asort($rest);
	$list = array_merge(array('en'=>$labels['en']), $list);
	print('<div id="complete_translations">');
	foreach ($list as $l=>$label) {
		Base_LangCommon::print_flag($l, $label, 'href="?install_lang='.$l.'"');
		unset($rest[$l]);
	}
	print('</div>');
	print('<a class="show_incomplete button" onclick="this.style.display=\'none\';document.getElementById(\'incomplete_translations\').style.display=\'\';">Show incomplete translations</a>');
	print('<div id="incomplete_translations" style="display:none;">');
	foreach ($rest as $l=>$label) {
		Base_LangCommon::print_flag($l, $label, 'href="?install_lang='.$l.'"');
	}
	print('</div>');
	
	set_header('Select Language');
	die();
}

/**
 * Check access to working directories
 */

if(file_exists('easyinstall.php')){
	unlink('easyinstall.php');
}

if (isset($_GET['check'])) {
	require_once('check.php');
	print('<br><br><a class="button" href="index.php?install_lang='.$install_lang_load.'" style="display:block;width:200px; margin:0 auto;">' . __('Continue with installation') . '</a>');
	die();
}

if(trim(ini_get("safe_mode")))
	die(__('You cannot use EPESI with PHP safe mode turned on - please disable it. Please notice this feature is deprecated since PHP 5.3 and is removed in PHP 5.4.'));

if(file_exists(DATA_DIR.'/config.php'))
	die(__('Cannot write into %s file. Please delete this file.', array(DATA_DIR.'/config.php')));

if(!is_writable(DATA_DIR))
	die(__('Cannot write into "%s" directory. Please fix privileges.', array(DATA_DIR)));

if (isset($_GET['tos1']) && $_GET['tos1'] && isset($_GET['tos2']) && $_GET['tos2'] && isset($_GET['tos3']) && $_GET['tos3'] && isset($_GET['tos4']) && $_GET['tos4']) {
    $_GET['license'] = 1;
    unset($_GET['tos1']);
    unset($_GET['tos2']);
    unset($_GET['tos3']);
    unset($_GET['tos4']);
}

if(!isset($_GET['license'])) {
	set_header(__('License Agreement'));
	print('<div class="license">');
    print read_doc_file('license');
	print('</div>');
	print('<div class="license agreement">');
	$form = new HTML_QuickForm('licenceform','get');
	$form -> addElement('html', '<tr><td colspan=2><h3>'.__('By installing and using this software you agree to the MIT license and following terms:').'</h3></td></tr>');
	$form -> addElement('checkbox','tos1','',__('I will not remove the <strong>"Copyright by Telaxus LLC"</strong> notice as required by the MIT license.'));
	$form -> addElement('checkbox','tos2','',__('I will not remove <strong>"EPESI powered"</strong> logo and the link from the application login screen or the toolbar.'));
	$form -> addElement('checkbox','tos3','',__('I will not remove <strong>"Support -> About"</strong> credit page from the application menu.'));
	$form -> addElement('checkbox','tos4','',__('I will not remove or rename <strong>"EPESI Store"</strong> links from the application.'));
	foreach($_GET as $f=>$v) {
        if (substr($f, 0, 3) != 'tos' && $f != 'submitted')
            $form->addElement('hidden',$f,$v);
    }
	$form->addElement('hidden','submitted',1);
	$form -> addRule('tos1', __('Field required'), 'required');
	$form -> addRule('tos2', __('Field required'), 'required');
	$form -> addRule('tos3', __('Field required'), 'required');
	$form -> addRule('tos4', __('Field required'), 'required');
	isset($_GET['submitted']) && $_GET['submitted'] && $form->validate();
	$form -> addElement('submit', null, __('Next'));
	$form->setRequiredNote('<span class="required_note_star">*</span> <span class="required_note">'.__('denotes required field').'</span>');
	$form->display();
    print('</div>');
} elseif(!isset($_GET['htaccess'])) {
	ob_start();
	print('<h1>' . __('Welcome to EPESI setup!') . '<br></h1><h2>' . __('Hosting compatibility') . ':</h2><br><div class="license">');
	if(check_htaccess()) {
		$_GET['htaccess'] = 1;
		ob_end_clean();
	} else {
		print('</div><br><a class="button" href="setup.php?license=1&htaccess=1&install_lang='.$install_lang_load.'">' . __('Ok') . '</a>');
		ob_end_flush();
	}
}
if(isset($_GET['htaccess']) && isset($_GET['license'])) {
	set_header(__('Configuration'));
	$form = new HTML_QuickForm('serverform','post',$_SERVER['PHP_SELF'].'?'.http_build_query($_GET));
	$form -> addElement('header', null, __('Database server settings'));
	$form -> addElement('text', 'host', __('Database server address'));
	$form -> addRule('host', __('Field required'), 'required');
	$form -> addElement('select', 'engine', __('Database engine'), array('postgres'=>'PostgreSQL', 'mysqlt'=>'MySQL'));
	$form -> addRule('engine', __('Field required'), 'required');
	$form -> addElement('text', 'user', __('Database server user'));
	$form -> addRule('user', __('Field required'), 'required');
	$form -> addElement('password', 'password', __('Database server password'));
	$form -> addRule('password', __('Field required'), 'required');
	$form -> addElement('text', 'db', __('Database name'));
	$form -> addRule('db', __('Field required'), 'required');
    $create_db_warn_msg = __('WARNING: Make sure you have CREATE access level to do this!');
	$form -> addElement('select', 'newdb', __('Create new database'),
            array(0 => __('No'), 1 => __('Yes')),
            array('onChange' => 'if(this.value==1) alert("' . $create_db_warn_msg . '","warning");'));
	$form -> addRule('newdb', __('Field required'), 'required');
	$form -> addElement('header', null, __('Other settings'));
	$form -> addElement('select', 'direction', __('Text direction'),
            array(0 => __('Left to Right'), 1 => __('Right to Left')));

	$form -> addElement('submit', 'submit', __('Next'));
	$form -> setDefaults(array('engine'=>'mysqlt','db'=>'epesi','host'=>'localhost'));
	$form->setRequiredNote('<span class="required_note_star">*</span> <span class="required_note">'.__('denotes required field').'</span>');

    if (file_exists($fast_install_filename)) {
        include $fast_install_filename;
        if (isset($CONFIG) && is_array($CONFIG)) {
            $txt = __('Some fields were filled to make installation easier.');
            print '<div style="text-align:center"><p style="width: 250px;margin-left: auto;margin-right: auto;">' . $txt . '</p></div>';
            foreach ($CONFIG as $key => $value) {
                $form->setDefaults(array($key => $value));
                $form->getElement($key)->freeze();
            }
        }
    }
    $required_note_text = __('denotes required field');
	$form->setRequiredNote('<span class="required_note_star">*</span> <span class="required_note">' . $required_note_text . '</span>');
	$form -> addElement('html', '<tr><td colspan=2><br /><b>'
            . __('Any existing tables will be dropped!') . '</b><br />'
            . __('The database will be populated with data.') . '<br />'
            . __('This operation can take several minutes.') . '</td></tr>');
	if($form -> validate()) {
		$engine = $form -> exportValue('engine');
		$direction = $form -> exportValue('direction');
		$other = array('direction'=>$direction);
        $host = $form -> exportValue('host');
        $user = $form -> exportValue('user');
        $pass = $form -> exportValue('password');
        $dbname = $form -> exportValue('db');
        $new_db = $form->exportValue('newdb');
		switch($engine) {
			case 'postgres':
				if(!function_exists('pg_connect')) {
				    echo(__('Please enable postgresql extension in php.ini.'));
				} else {
					$link = pg_connect("host=$host user=$user password=$pass dbname=postgres");
					if(!$link) {
	 					echo(__('Could not connect.'));
					} else {
						if ($new_db == 1) {
							$sql = 'CREATE DATABASE '.$dbname;
							if (pg_query($link, $sql)) {
				   				//echo "Database '$dbname' created successfully\n";
				   				write_config($host,$user,$pass,$dbname,$engine,$other);
							} else {
		 		  				echo __('Error creating database') . ': ' . pg_last_error() . "\n";
		 	  				}
		   					pg_close($link);
						} else {
							include_once('libs/adodb/adodb.inc.php');
							$ado = & NewADOConnection('postgres');
							if(!@$ado->Connect($host,$user,$pass,$dbname)) {
								echo __('Database does not exist.') . "\n";
                                echo '<br />' .__('Please create the database first or select option')
                                        . ':<br /><b>' . __('Create new database') . '</b>';
							} else {
								write_config($host, $user, $pass, $dbname, $engine,$other);
							}
						}
					}
				}
			break;
            case 'mysqlt':
                if (!function_exists('mysql_connect')) {
                    echo(__('Please enable mysql extension in php.ini.'));
                } else {
                    $link = @mysql_connect($host, $user, $pass);
                    if (!$link) {
                        echo(__('Could not connect') . ': ' . mysql_error());
                    } else {
                        if ($new_db == 1) {
                            $sql = 'CREATE DATABASE `' . $dbname . '` CHARACTER SET utf8 COLLATE utf8_unicode_ci';
                            if (mysql_query($sql, $link)) {
                                write_config($host, $user, $pass, $dbname, $engine, $other);
                            } else {
                                echo __('Error creating database: ') . mysql_error() . "\n";
                            }
                            mysql_close($link);
                        } else {
                            $result = mysql_select_db($dbname, $link);
                            if (!$result) {
                                echo __('Database does not exist') . ': ' . mysql_error() . "\n";
                                echo '<br />' . __('Please create the database first or select option')
                                . ':<br /><b>' . __('Create new database') . '</b>';
                            } else {
                                write_config($host, $user, $pass, $dbname, $engine, $other);
                            }
                        }
                    }
                }
                break;
		}
	}

	$renderer =& $form->defaultRenderer();
	$renderer->setHeaderTemplate("\n\t<tr>\n\t\t<td style=\"white-space: nowrap; height: 20px; vertical-align: middle; background-color: #336699; background-image: url('images/header-blue.png'); background-repeat: repeat-x; color: #FFFFFF; font-weight: normal; text-align: center;\" align=\"left\" valign=\"baseline\" colspan=\"2\">{header}</td>\n\t</tr>");
	$renderer->setElementTemplate("\n\t<tr>\n\t\t<td align=\"right\" valign=\"baseline\"><!-- BEGIN required --><span style=\"color: #ff0000\">*</span><!-- END required -->{label}</td>\n\t\t<td valign=\"baseline\" align=\"left\"><!-- BEGIN error --><span style=\"color: #ff0000\">{error}</span><br /><!-- END error -->\t{element}</td>\n\t</tr>");
		$form->accept($renderer);
		print($renderer->toHtml());
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
		print(__('Unable to check EPESI root .htaccess file hosting compatibility. You should tweak it yourself.')
                . '<br>' . __('Suggested .htaccess file is:') . '<pre>' . file_get_contents('htaccess.txt') . '</pre>');
		return false;
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
			print(__('Your hosting is not compatible with default EPESI root .htaccess file. You should tweak it yourself.')
                    . '<br>' . __('Default .htaccess file is:') . '<pre>' . file_get_contents('htaccess.txt') . '</pre>');
			return false;
		}
	}
	if(!is_writable('.')) {
		unlink('data/test.php');
		print(__('Your hosting is compatible with default EPESI root .htaccess file, but installer cannot write to EPESI root directory. You should paste following text to .htaccess file manually.')
                . '<pre>' . file_get_contents('data/.htaccess') . '</pre>');
		unlink('data/.htaccess');
		return false;
	}
	unlink('data/test.php');
	rename('data/.htaccess','.htaccess');
	return true;
}

function write_config($host, $user, $pass, $dbname, $engine, $other) {
    global $install_lang_load;
	$local_dir = dirname(dirname(str_replace('\\','/',__FILE__)));
	$script_filename = str_replace('\\','/',$_SERVER['SCRIPT_FILENAME']);
	$other_conf = '';
	if(strcmp($local_dir,substr($script_filename,0,strlen($local_dir))))
		$other_conf .= "\n".'@define("EPESI_DIR","'.str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])).'");';
	$other_conf .= "\n".'define("DIRECTION_RTL","'.($other['direction']?'1':'0').'");';

	$protocol = (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS'])!== "off") ? 'https://' : 'http://';
        $domain_name = '';
        if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST']) {
            $domain_name = $_SERVER['HTTP_HOST'];
        } else if (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME']) {
            $domain_name = $_SERVER['SERVER_NAME'];
        }
        if($domain_name) {
            $url = $protocol . $domain_name . dirname($_SERVER['REQUEST_URI']);
	    $other_conf .= "\n".'define("EPESI_URL","'.$url.'");';
        }
	$c = & fopen(DATA_DIR.'/config.php', 'w');
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
//define(\'GZIP_HISTORY\',1);

/*
 * Compress HTTP output
 */
//define(\'MINIFY_ENCODE\',1);

/*
 * Apply sources minifying algorithms.
 *
 * If enabled CPU usage may raise, but amount
 * of transferred data is smaller.
 */
//define(\'MINIFY_SOURCES\',0);

/*
 * Show donation links in EPESI
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
define(\'FILE_SESSION_TOKEN\',\'epesi_'.md5(__FILE__).'_\');

'.$other_conf.'
?>');
	fclose($c);

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
	if(DATABASE_DRIVER=='mysqlt' || DATABASE_DRIVER=='mysqli')
		DB::Execute('SET FOREIGN_KEY_CHECKS=0');
	if(DATABASE_DRIVER=='postgres' && strpos(DB::GetOne('SELECT version()'),'PostgreSQL 8.2')!==false) {
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
	if(DATABASE_DRIVER=='mysqlt' || DATABASE_DRIVER=='mysqli')
		DB::Execute('SET FOREIGN_KEY_CHECKS=1');
}

function install_base() {
	require_once('include/config.php');
	require_once('include/database.php');

	$ret = DB::CreateTable('modules',"name C(128) KEY,version I NOTNULL, priority I NOTNULL DEFAULT 0");
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

	$ret = DB::CreateTable('variables',"name C(32) KEY,value X");
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
