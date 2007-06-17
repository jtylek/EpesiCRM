<?php
/*
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @licence TL
 */
/**
 * Check access to working directories
 */
if(file_exists('data/config.php') && !is_writable('data/config.php'))
	die('Cannot write into data/config.php file. Please fix privileges.');

if(!is_writable('data'))
	die('Cannot write into "data" directory. Please fix privileges.');

if(!is_writable('backup'))
	die('Cannot write into "backup" directory. Please fix privileges.');

$delimiter = ($_ENV['OS']=='Windows_NT')?';':':';
ini_set('include_path','modules/Libs/QuickForm/3.2.7'.$delimiter.ini_get('include_path'));
require_once "HTML/QuickForm.php";

function write_config($host,$user,$pass,$dbname,$engine) {
	$c = & fopen('data/config.php','w');
	fwrite($c, '<?php
/**
 * Config file
 * 
 * This file contains database configuration.
 */
 defined("_VALID_ACCESS") || die("Direct access forbidden");
 
/**
 * Address of SQL server.
 */ 
define("DATABASE_HOST","'.$host.'");

 /**
 * User to log in to SQL server.
 */
define("DATABASE_USER","'.$user.'");

 /** 
 * User password to authorize SQL server.
 */ 
define("DATABASE_PASSWORD","'.$pass.'");
 
 /** 
 * Database to use.
 */	  
define("DATABASE_NAME","'.$dbname.'");

/** 
 * Database driver.
 */	  
define("DATABASE_DRIVER","'.$engine.'");

/*
 * A lot of debug info, starting with what modules are changed, what module variables are set... etc.
 */
define("DEBUG",0);

/*
 * Show module loading time ...  = module + all children times
 */
define("MODULE_TIMES",0);

/*
 * Show queries execution time.
 */
define("SQL_TIMES",0);

/*
 * Check type of params passed to queries in DB::* methods. Use it for debug, and early stage of usage.
 */
define("SQL_TYPE_CONTROL",0);

/*
 * Lower performance, unable to debug JS... Teenage hacker probably won\'t decrypt it.
 */
define("SECURE_HTTP",1);

/*
 * If you have got good server, but poor connection, turn it on.
 */
define("STRIP_OUTPUT",0);
?>');
	fclose($c);
	chmod('data/config.php',0444);
//	unlink('setup.php');
	header('Location: index.php');
}

$form = new HTML_QuickForm('formHello');
$form->addElement('header', null, 'Database server settings');
$form->addElement('select', 'engine', 'Database engine',array('postgres'=>'PostgreSQL', 'mysqlt'=>'MySQL'));
$form->addRule('engine', 'Field required', 'required');
$form->addElement('text', 'host', 'Database server address');
$form->addRule('host', 'Field required', 'required');
$form->addElement('text', 'user', 'Database server user');
$form->addRule('user', 'Field required', 'required');
$form->addElement('password', 'password', 'Database server password');
$form->addRule('password', 'Field required', 'required');
$form->addElement('text', 'db', 'Database name');
$form->addRule('db', 'Field required', 'required');
$form->addElement('select', 'newdb', 'Create new database',array(1=>'Yes', 0=>'No'));
$form->addRule('newdb', 'Field required', 'required');

$form->setDefaults(array('host'=>'localhost','engine'=>'mysqlt','db'=>'epesi'));
$form->addElement('submit', 'submit', 'OK', array('onclick'=>'alert("Setup will now check for available modules, this operation may take several minutes and will be triggered automatically only once. Click ok to proceed.");'));

if ($form->validate()) {
	$engine = $form->exportValue('engine');
	switch($engine) {
		case 'postgres': 
			$host = $form->exportValue('host');
			$user = $form->exportValue('user');
			$pass = $form->exportValue('password');
			$link = pg_connect("host=$host user=$user password=$pass dbname=postgres");
			if ($link) {
				$form->freeze();
				$dbname = $form->exportValue('db');
				if($form->exportValue('newdb')==1) {
					$sql = 'CREATE DATABASE '.$dbname;
					if (pg_query($link, $sql)) {
   						//echo "Database '$dbname' created successfully\n";
   						write_config($host,$user,$pass,$dbname,$engine);
					} else
   						echo 'Error creating database: ' . pg_error() . "\n";
   					pg_close($link);
				} else
					write_config($host,$user,$pass,$dbname,$engine);
			}
			break;
		case 'mysqlt':
			$host = $form->exportValue('host');
			$user = $form->exportValue('user');
			$pass = $form->exportValue('password');
			$link = mysql_connect($host,$user,$pass);
			if (!$link) {
 				echo('Could not connect: ' . mysql_error());
			} else {
				$form->freeze();
				$dbname = $form->exportValue('db');
				if($form->exportValue('newdb')==1) {
					$sql = 'CREATE DATABASE '.$dbname;
					if (mysql_query($sql, $link)) {
   						//echo "Database '$dbname' created successfully\n";
   						write_config($host,$user,$pass,$dbname,$engine);
					} else
   						echo 'Error creating database: ' . mysql_error() . "\n";
   					mysql_close($link);
				} else
					write_config($host,$user,$pass,$dbname,$engine);
			}
	}
}
$form->display();
?>
