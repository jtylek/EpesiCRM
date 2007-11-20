<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @license SPL
 * @package epesi-base
 */
ob_start();
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
      <meta content="text/html; charset=ISO-8859-1" http-equiv="content-type">
      <title>epesi setup</title>
      <link href="setup.css" type="text/css" rel="stylesheet"/>
</head>
<body>
        <table id="banner" border="0" cellpadding="0" cellspacing="0">
            <tr>
                <td class="image">&nbsp;</td>
                <td class="back">&nbsp;</td>
            </tr>
        </table>
        <br>
        <center>
        <table id="main" border="0" cellpadding="0" cellspacing="0">
            <tr>
                <td>
<?php
/**
 * Check access to working directories
 */
if(file_exists('data/config.php'))
	die('Cannot write into data/config.php file. Please delete this file.');

if(!is_writable('data'))
	die('Cannot write into "data" directory. Please fix privileges.');

if(!is_writable('backup'))
	die('Cannot write into "backup" directory. Please fix privileges.');

@define("_VALID_ACCESS", true);
require_once('include/include_path.php');
require_once('modules/Libs/QuickForm/requires.php');

if(!isset($_GET['license'])) {
	print('<h1>Welcome to epesi framework setup!<br></h1><h2>Please read and accept license</h2><br><div class="license">');
	license();
        print('</div><br><a class="button" href="setup.php?license=1">Accept</a>');
?>

<?php
}
    else {
	$form = new HTML_QuickForm('serverform','post',$_SERVER['PHP_SELF'].'?'.http_build_query($_GET));
	$form -> addElement('header', null, 'Database server settings');
	$form -> addElement('text', 'host', 'Database server address');
	$form -> addRule('host', 'Field required', 'required');
	$form -> addElement('select', 'engine', 'Database engine',array('postgres'=>'PostgreSQL', 'mysqlt'=>'MySQL'));
	$form -> addRule('engine', 'Field required', 'required');
	$form -> addElement('text', 'user', 'Database server user');
	$form -> addRule('user', 'Field required', 'required');
	$form -> addElement('password', 'password', 'Database server password');
	$form -> addRule('password', 'Field required', 'required');
	$form -> addElement('text', 'db', 'Database name');
	$form -> addRule('db', 'Field required', 'required');
	$form -> addElement('select', 'newdb', 'Create new database',array(0=>'No',1=>'Yes'),array('onChange'=>'if(this.value==1)alert("WARNING: Make sure you have CREATE access level to do this!","warning");'));
	$form -> addRule('newdb', 'Field required', 'required');
//	$form -> addElement('select', 'newuser', 'Create new user',array(1=>'Yes', 0=>'No'));
//	$form -> addRule('newuser', 'Field required', 'required');

	$form -> addElement('submit', 'submit', 'Next');
	$form -> setDefaults(array('engine'=>'mysqlt','db'=>'epesi','host'=>'localhost'));

	$form->setRequiredNote('<span class="required_note_star">*</span> <span class="required_note">denotes required field</span>');
	$form -> addElement('html','<tr><td colspan=2><br /><b>Any existing tables will be dropped!</b><br />The database will be populated with data.<br />This operation can take several minutes.</td></tr>');
	if($form -> validate()) {
	    $engine = $form -> exportValue('engine');
	    switch($engine) {
		case 'postgres': {
		    $host = $form -> exportValue('host');
		    $user = $form -> exportValue('user');
		    $pass = $form -> exportValue('password');
		    $link = @pg_connect("host=$host user=$user password=$pass dbname=postgres");
		    if(!$link) {
 			echo('Could not connect.');
		    }
                    else {
			$dbname = $form -> exportValue('db');
			if($form->exportValue('newdb')==1) {
			    $sql = 'CREATE DATABASE '.$dbname;
			    if (pg_query($link, $sql)) {
   				//echo "Database '$dbname' created successfully\n";
   				write_config($host,$user,$pass,$dbname,$engine);
			    }
                            else {
 	  			echo 'Error creating database: ' . pg_last_error() . "\n";
                            }
   			    pg_close($link);
			}
                        else {
			    write_config($host, $user, $pass, $dbname, $engine);
                        }
		    }
                }
                break;
		case 'mysqlt': {
		    $host = $form->exportValue('host');
		    $user = $form->exportValue('user');
		    $pass = $form->exportValue('password');
		    $link = @mysql_connect($host,$user,$pass);
		    if (!$link) {
 			echo('Could not connect: ' . mysql_error());
		    }
                    else {
			$dbname = $form->exportValue('db');
			if($form->exportValue('newdb')==1) {
			    $sql = 'CREATE DATABASE '.$dbname;
			    if(mysql_query($sql, $link)) {
   				//echo "Database '$dbname' created successfully\n";
   				write_config($host,$user,$pass,$dbname,$engine);
			    }
                            else {
	   			echo 'Error creating database: ' . mysql_error() . "\n";
                            }
   			    mysql_close($link);
			}
                        else {
			    write_config($host, $user, $pass, $dbname, $engine);
                        }
		    }
                }
                break;
	    }
	}

	$renderer =& $form->defaultRenderer();
	$renderer->setHeaderTemplate("\n\t<tr>\n\t\t<td style=\"white-space: nowrap; height: 20px; vertical-align: middle; background-color: #336699; background-image: url('images/header-blue.png'); background-repeat: repeat-x; color: #FFFFFF; font-weight: normal; text-align: center;\" align=\"left\" valign=\"top\" colspan=\"2\">{header}</td>\n\t</tr>");
	$renderer->setElementTemplate("\n\t<tr>\n\t\t<td align=\"right\" valign=\"top\"><!-- BEGIN required --><span style=\"color: #ff0000\">*</span><!-- END required -->{label}</td>\n\t\t<td valign=\"top\" align=\"left\"><!-- BEGIN error --><span style=\"color: #ff0000\">{error}</span><br /><!-- END error -->\t{element}</td>\n\t</tr>");
        $form->accept($renderer);
        print($renderer->toHtml());
    }

///////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////
function write_config($host, $user, $pass, $dbname, $engine) {
    $c = & fopen('data/config.php', 'w');
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
 * If you have got good server, but poor connection, turn it on.
 */
define("STRIP_OUTPUT",0);

/*
 * Display errors on page.
 */
define("DISPLAY_ERRORS",1);

/*
 * Notify all errors, including E_NOTICE, etc. Developer should use it!
 */
define("REPORT_ALL_ERRORS",1);

/*
 * Compress output buffer,session,history
 */
define("GZIP_OUTPUT",1);
define("GZIP_HISTORY",1);
?>');
	fclose($c);

	ob_start();
	ob_start('rm_config');

	//fill database
	clean_database();
	install_base();

	ob_end_flush();

	if(file_exists('data/config.php'))
		header('Location: index.php');
	ob_end_flush();
}


//////////////////////////////////////////////
function rm_config($x) {
	if($x) {
		unlink(dirname(__FILE__).'/data/config.php');
		clean_database();
	}
	return $x;
}

function clean_database() {
	require_once('include/include_path.php');
	require_once('include/config.php');
	require_once('include/database.php');
	$tables_db = DB::MetaTables();
	$tables = array();
	if(DATABASE_DRIVER=='mysqlt')
		DB::Execute('SET FOREIGN_KEY_CHECKS=0');
	foreach($tables_db as $t) {
		DB::DropTable($t);
	}
	if(DATABASE_DRIVER=='mysqlt')
		DB::Execute('SET FOREIGN_KEY_CHECKS=1');
}

function install_base() {
	require_once('include/include_path.php');
	require_once('include/config.php');
	require_once('include/database.php');

	$ret = DB::CreateTable('modules',"name C(128) KEY,version I NOTNULL, priority I NOTNULL DEFAULT 0");
	if($ret===false)
		die('Invalid SQL query - Setup module (modules table)');

	$ret = DB::CreateTable('session',"name C(32) NOTNULL," .
			"expires I NOTNULL DEFAULT 0, data X",array('constraints'=>', PRIMARY KEY(name)'));
	if($ret===false)
		die('Invalid SQL query - Database module (session table)');

	$ret = DB::CreateTable('session_client',"session_name C(32) NOTNULL, client_id I2," .
			"data X2",array('constraints'=>', FOREIGN KEY(session_name) REFERENCES session(name)'));
	if($ret===false)
		die('Invalid SQL query - Database module (session_client table)');

	$ret = DB::CreateTable('history',"session_name C(32) NOTNULL, page_id I, client_id I2," .
			"data X2",array('constraints'=>', FOREIGN KEY(session_name) REFERENCES session(name)'));
	if($ret===false)
		die('Invalid SQL query - Database module (history table)');

	$ret = DB::CreateTable('variables',"name C(32) KEY,value X");
	if($ret===false)
		die('Invalid SQL query - Database module (variables table)');

	$ret = DB::Execute("insert into variables values('default_module',%s)",array(serialize('FirstRun')));
	if($ret === false)
		die('Invalid SQL query - Setup module (populating variables)');

	$ret = DB::Execute("insert into variables values('version',%s)",array(serialize(EPESI_VERSION)));
	if($ret === false)
		die('Invalid SQL query - Setup module (populating variables)');

	//phpgacl
	require( "adodb/adodb-xmlschema.inc.php" );

	$errh = DB::$ado->raiseErrorFn;
	DB::$ado->raiseErrorFn = false;

	$schema = new adoSchema(DB::$ado);
	$schema->ParseSchema('libs/phpgacl/schema.xml');
	$schema->ContinueOnError(TRUE);
	$ret = $schema->ExecuteSchema();
	if($ret===false)
		die('Invalid SQL query - Setup module (phpgacl tables)');

	DB::$ado->raiseErrorFn = $errh;

	require_once('include/acl.php');

	Acl::$gacl->add_object_section('Administration','Administration',1,0,'aco');
	Acl::$gacl->add_object_section('Data','Data',2,0,'aco');

	Acl::$gacl->add_object('Administration','Main','Main',1,0,'aco');
	Acl::$gacl->add_object('Administration','Modules','Modules',2,0,'aco');
	Acl::$gacl->add_object('Data','Moderation','Moderation',1,0,'aco');
	Acl::$gacl->add_object('Data','View','View',2,0,'aco');

	Acl::$gacl->add_object_section('Users','Users',1,0,'aro');

	$user_id = Acl::$gacl->add_group('User','User');
	$moderator_id = Acl::$gacl->add_group('Moderator','Moderator', $user_id);
	$administrator_id = Acl::$gacl->add_group('Administrator','Administrator', $moderator_id);
	$sa_id = Acl::$gacl->add_group('Super administrator','Super administrator', $administrator_id);

	Acl::$gacl->add_acl(array('Administration' =>array('Main')), array(), array($sa_id), NULL, NULL,1,1,'','','user');
	Acl::$gacl->add_acl(array('Administration' =>array('Modules')), array(), array($administrator_id), NULL, NULL,1,1,'','','user');
	Acl::$gacl->add_acl(array('Data' =>array('Moderation')), array(), array($moderator_id), NULL, NULL,1,1,'','','user');
	Acl::$gacl->add_acl(array('Data' =>array('View')), array(), array($user_id), NULL, NULL,1,1,'','','user');
}
//////////////////////////////////////////////
function license() {
$fp = @fopen('license.html', 'r');
if ($fp){
	$license_txt = fread($fp,filesize('license.html'));
	}
fclose($fp);
print $license_txt;
}
?>
                </td>
            </tr>
        </table>
        </center>
        <br>
        <center>
        <span class="footer">Copyright &copy; 2007 &bull; <a href="http://epesi.sourceforge.net/">epesi framework</a> &bull; Application developed by <a href="http://www.telaxus.com">Telaxus LLC</a></span>
        <br>
        <p><a href="http://www.epesi.org"><img src="images/epesi-powered.png" border="0"></a></p>
        </center>
</body>
</html>
<?php
ob_end_flush();
?>
