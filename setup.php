<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @licence SPL
 * @package epesi-base
 */
/**
 * Check access to working directories
 */
if(file_exists('data/config.php') && !is_writable('data/config.php'))
	die('Cannot write into data/config.php file. Please fix privileges or delete this file.');

if(!is_writable('data'))
	die('Cannot write into "data" directory. Please fix privileges.');

if(!is_writable('backup'))
	die('Cannot write into "backup" directory. Please fix privileges.');

$delimiter = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')?';':':';
ini_set('include_path','modules/Libs/QuickForm/3.2.7'.$delimiter.ini_get('include_path'));
require_once "HTML/QuickForm.php";


if(!isset($_GET['licence'])) {
	print('<h1>Welcome to epesi!<br></h1><h2>Please read and accept licence</h2><br><div style="overflow:auto;height:60%; border: 1px solid black;">');
	licence();
?>
</div>
<h2><a href="setup.php?licence=1">Accept</a></h2>
<?php
} else {
	$form = new HTML_QuickForm('serverform','post',$_SERVER['PHP_SELF'].'?'.http_build_query($_GET));
	$form->addElement('header', null, 'Database server settings');
	$form->addElement('text', 'host', 'Database server address');
	$form->addRule('host', 'Field required', 'required');
	$form->addElement('select', 'engine', 'Database engine',array('postgres'=>'PostgreSQL', 'mysqlt'=>'MySQL'));
	$form->addRule('engine', 'Field required', 'required');
	$form->addElement('text', 'user', 'Database server user');
	$form->addRule('user', 'Field required', 'required');
	$form->addElement('password', 'password', 'Database server password');
	$form->addRule('password', 'Field required', 'required');
	$form->addElement('text', 'db', 'Database name');
	$form->addRule('db', 'Field required', 'required');
	$form->addElement('select', 'newdb', 'Create new database',array(1=>'Yes', 0=>'No'));
	$form->addRule('newdb', 'Field required', 'required');

	$form->addElement('submit', 'submit', 'OK');
	$form->setDefaults(array('engine'=>'mysqlt','db'=>'epesi','host'=>'localhost'));

	if ($form->validate()) {
		$engine = $form->exportValue('engine');
		switch($engine) {
			case 'postgres': 
				$host = $form->exportValue('host');
				$user = $form->exportValue('user');
				$pass = $form->exportValue('password');
				$link = @pg_connect("host=$host user=$user password=$pass dbname=postgres");
				if (!$link) {
 					echo('Could not connect.');
				} else {
					$dbname = $form->exportValue('db');
					if($form->exportValue('newdb')==1) {
						$sql = 'CREATE DATABASE '.$dbname;
						if (pg_query($link, $sql)) {
   							//echo "Database '$dbname' created successfully\n";
   							write_config($host,$user,$pass,$dbname,$engine);
						} else
 	  						echo 'Error creating database: ' . pg_last_error() . "\n";
   						pg_close($link);
					} else
						write_config($host,$user,$pass,$dbname,$engine);
				}
				break;
			case 'mysqlt':
				$host = $form->exportValue('host');
				$user = $form->exportValue('user');
				$pass = $form->exportValue('password');
				$link = @mysql_connect($host,$user,$pass);
				if (!$link) {
 					echo('Could not connect: ' . mysql_error());
				} else {
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
}

///////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////
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
define("SECURE_HTTP",0);

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
define("GZIP_SESSION",1);
define("GZIP_HISTORY",1);
?>');
	fclose($c);

	ob_start('rm_config');

	//fill database	
	install_base();

//	unlink('setup.php');
	header('Location: index.php');

	ob_end_flush();
}


//////////////////////////////////////////////
function rm_config($x) {
	if($x) unlink(dirname(__FILE__).'/data/config.php');
	return false;
}

function install_base() {
	define("_VALID_ACCESS", true);
	require_once('include/include_path.php');
	require_once('include/config.php');
	require_once('include/database.php');
	
	$ret = DB::CreateTable('modules',"name C(128) KEY,version I NOTNULL, priority I NOTNULL DEFAULT 0");
	if($ret===false)
		die('Invalid SQL query - Setup module (modules table)');
	
	$ret = DB::CreateTable('session',"name C(255) NOTNULL KEY, " .
			"expires I NOTNULL DEFAULT 0, data X2");
	if($ret===false)
		die('Invalid SQL query - Database module (session table)');
	
	$ret = DB::CreateTable('history',"session_name C(255) NOTNULL, page_id I, client_id I," .
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
function licence() {
?>
<h1>SUN PUBLIC LICENSE Version 1.0</h1>
<p><tt><br>
1. Definitions.</tt></p>
<blockquote>
<p><tt>1.0.1. "Commercial Use" means distribution or otherwise making the<br>
        Covered Code available to a third party.</tt></p>
<p><tt>1.1. "Contributor" means each entity that creates or contributes to<br>
        the creation of Modifications.</tt></p>

<p><tt>1.2. "Contributor Version" means the combination of the Original Code,<br>
        prior Modifications used by a Contributor, and the Modifications made<br>
        by that particular Contributor.</tt></p>
<p><tt>1.3. "Covered Code" means the Original Code or Modifications or the<br>
        combination of the Original Code and Modifications, in each case<br>
        including portions thereof and corresponding documentation released<br>
        with the source code.</tt></p>

<p><tt>1.4. "Electronic Distribution Mechanism" means a mechanism generally<br>
        accepted in the software development community for the electronic<br>
        transfer of data.</tt></p>
<p><tt>1.5. "Executable" means Covered Code in any form other than Source<br>
        Code.</tt></p>
<p><tt>1.6. "Initial Developer" means the individual or entity identified as<br>
        the Initial Developer in the Source Code notice required by Exhibit<br>

 A.</tt></p>
<p><tt>1.7. "Larger Work" means a work which combines Covered Code or<br>
        portions thereof with code not governed by the terms of this<br>
 License.</tt></p>
<p><tt>1.8. "License" means this document.</tt></p>
<p><tt>1.8.1. "Licensable" means having the right to grant, to the maximum<br>
        extent possible, whether at the time of the initial grant or<br>

        subsequently acquired, any and all of the rights conveyed herein.</tt></p>
<p><tt>1.9. "Modifications" means any addition to or deletion from the<br>
        substance or structure of either the Original Code or any previous<br>
        Modifications. When Covered Code is released as a series of files, a<br>
        Modification is:</tt></p>
<blockquote>
<p><tt>A. Any addition to or deletion from the contents of a file containing<br>

        Original Code or previous Modifications.</tt></p>
<p><tt>B. Any new file that contains any part of the Original Code or<br>
        previous Modifications.</tt></p></blockquote>
<p><tt>1.10. "Original Code"../ means Source Code of computer software code<br>
        which is described in the Source Code notice required by Exhibit A as<br>
        Original Code, and which, at the time of its release under this<br>
        License is not already Covered Code governed by this License.</tt></p>

<p><tt>1.10.1. "Patent Claims" means any patent claim(s), now owned or<br>
        hereafter acquired, including without limitation, method, process, and<br>
        apparatus claims, in any patent Licensable by grantor.</tt></p>
<p><tt>1.11. "Source Code"../ means the preferred form of the Covered Code<br>
 for<br>
        making modifications to it, including all modules it contains, plus<br>
        any associated documentation, interface definition files, scripts used<br>

        to control compilation and installation of an Executable, or source<br>
        code differential comparisons against either the Original Code or<br>
        another well known, available Covered Code of the Contributor's<br>
        choice. The Source Code can be in a compressed or archival form,<br>
        provided the appropriate decompression or de-archiving software is<br>
        widely available for no charge.</tt></p>

<p><tt>1.12. "You" (or "Your") means an individual or a legal entity<br>
        exercising rights under, and complying with all of the terms of, this<br>
        License or a future version of this License issued under Section 6.1.<br>
        For legal entities, "You" includes any entity which controls, is<br>
        controlled by, or is under common control with You. For purposes of<br>
        this definition, "control"../ means (a) the power, direct or indirect,<br>

 to<br>
        cause the direction or management of such entity, whether by contract<br>
        or otherwise, or (b) ownership of more than fifty percent (50%) of the<br>
        outstanding shares or beneficial ownership of such entity.</tt></p>
</blockquote>
<p><tt>2. Source Code License.</tt></p>
<blockquote>
<p><tt>2.1 The Initial Developer Grant.</tt></p>

<blockquote>
<p><tt>The Initial Developer hereby grants You a world-wide, royalty-free,<br>
        non-exclusive license, subject to third party intellectual property<br>
        claims:</tt></p>
<p><tt>   (a)  under intellectual property rights (other than patent or<br>
        trademark) Licensable by Initial Developer to use, reproduce, modify,<br>
        display, perform, sublicense and distribute the Original Code (or<br>

        portions thereof) with or without Modifications, and/or as part of a<br>
        Larger Work; and</tt></p>
<p><tt>(b) under Patent Claims infringed by the making, using or selling of<br>
        Original Code, to make, have made, use, practice, sell, and offer for<br>
        sale, and/or otherwise dispose of the Original Code (or portions<br>
        thereof).</tt></p>

<p><tt>(c) the licenses granted in this Section 2.1(a) and (b) are effective<br>
        on the date Initial Developer first distributes Original Code under<br>
        the terms of this License.</tt></p>
<p><tt>(d) Notwithstanding Section 2.1(b) above, no patent license is<br>
        granted: 1)     for code that You delete from the Original Code; 2)<br>
        separate from the       Original Code; or 3) for infringements caused<br>
 by:</tt></p>

<p><tt>i) the modification of the Original Code or ii) the combination of the<br>
        Original Code with other software or devices.</tt></p>
</blockquote>
<p><tt>2.2. Contributor Grant.</tt></p>
<blockquote>
<p><tt>Subject to third party intellectual property claims, each Contributor<br>
        hereby grants You a world-wide, royalty-free, non-exclusive license</tt></p>
<blockquote><p><tt>(a) under intellectual property rights (other than patent<br>
 or<br>

        trademark) Licensable by Contributor, to use, reproduce,  modify,<br>
        display, perform, sublicense and distribute the Modifications created<br>
        by such Contributor (or portions thereof) either on an unmodified<br>
        basis, with other Modifications, as Covered Code and/or as part of a<br>
        Larger Work; and</tt></p>
<p><tt>b) under Patent Claims infringed by the making, using, or selling of<br>

        Modifications made by that Contributor either alone and/or in<br>
        combination with its Contributor Version (or portions of such<br>
        combination), to make, use, sell, offer for sale, have made, and/or<br>
        otherwise dispose of: 1) Modifications made by that Contributor (or<br>
        portions thereof); and 2) the combination of Modifications made by<br>
        that Contributor with its Contributor Version (or portions of such<br>

        combination).</tt></p>
<p><tt>(c) the licenses granted in Sections 2.2(a) and 2.2(b) are effective<br>
        on the date Contributor first makes Commercial Use of the Covered<br>
        Code.</tt></p>
<p><tt>(d)  notwithstanding Section 2.2(b) above, no patent license is<br>
        granted: 1) for any code that Contributor has deleted from the<br>
        Contributor Version; 2)  separate from the Contributor Version; 3) for<br>

        infringements caused by: i) third party modifications of Contributor<br>
        Version or ii) the combination of Modifications made by that<br>
        Contributor with other software (except as part of the Contributor<br>
        Version) or other devices; or 4) under Patent Claims infringed by<br>
        Covered Code in the absence of Modifications made by that<br>
 Contributor.</tt></p>

</blockquote></blockquote>
<p><tt>3. Distribution Obligations.</tt></p>
<blockquote>
<p><tt>3.1. Application of License.</tt></p>
<p><tt>The Modifications which You create or to which You contribute are<br>
        governed by the terms of this License, including without limitation<br>
        Section 2.2. The Source Code version of Covered Code may be<br>
        distributed only under the terms of this License or a future version<br>

        of this License released under Section 6.1, and You must include a<br>
        copy of this License with every copy of the Source Code You<br>
        distribute. You may not offer or impose any terms on any Source Code<br>
        version that alters or restricts the applicable version of this<br>
        License or the recipients' rights hereunder. However, You may include<br>
        an additional document offering the additional rights described in<br>

        Section 3.5.</tt></p>
<p><tt>3.2. Availability of Source Code.</tt></p>
<p><tt>Any Modification which You create or to which You contribute must be<br>
        made available in Source Code form under the terms of this License<br>
        either on the same media as an Executable version or via an accepted<br>
        Electronic Distribution Mechanism to anyone to whom you made an<br>
        Executable version available; and if made available via Electronic<br>

        Distribution Mechanism, must remain available for at least twelve (12)<br>
        months after the date it initially became available, or at least six<br>
        (6) months after a subsequent version of that particular Modification<br>
        has been made available to such recipients. You are responsible for<br>
        ensuring that the Source Code version remains available even if the<br>
        Electronic Distribution Mechanism is maintained by a third party.</tt></p>

<p><tt>3.3. Description of Modifications.</tt></p>
<p><tt>You must cause all Covered Code to which You contribute to contain a<br>
        file documenting the changes You made to create that Covered Code and<br>
        the date of any change. You must include a prominent statement that<br>
        the Modification is derived, directly or indirectly, from Original<br>
        Code provided by the Initial Developer and including the name of the<br>
        Initial Developer in (a) the Source Code, and (b) in any notice in an<br>

        Executable version or related documentation in which You describe the<br>
        origin or ownership of the Covered Code.</tt></p>
<p>
<tt>3.4. Intellectual Property Matters.</tt></p>
<blockquote>
<p><tt>(a) Third Party Claims.</tt></p>
<p><tt>        If Contributor has knowledge that a license under a third party's<br>
        intellectual property rights is required to exercise the rights<br>

        granted by such Contributor under Sections 2.1 or 2.2, Contributor<br>
        must include a text file with the Source Code distribution titled<br>
        "../LEGAL'' which describes the claim and the party making the claim in<br>
        sufficient detail that a recipient will know whom to contact. If<br>
        Contributor obtains such knowledge after the Modification is made<br>
        available as described in Section 3.2, Contributor shall promptly<br>

        modify the LEGAL file in all copies Contributor makes available<br>
        thereafter and shall take other steps (such as notifying appropriate<br>
        mailing lists or newsgroups) reasonably calculated to inform those who<br>
        received the Covered Code that new knowledge has been obtained.</tt></p>
<p><tt>(b) Contributor APIs.</tt></p>
<p><tt>If Contributor's Modifications include an application programming<br>
        interface ("API"../) and Contributor has knowledge of patent licenses<br>

        which are reasonably necessary to implement that API, Contributor must<br>
        also include this information in the LEGAL file.</tt></p>
<p>
<tt>        (c) Representations.</tt></p>
<p><tt>Contributor represents that, except as disclosed pursuant to Section<br>
        3.4(a) above, Contributor believes that Contributor's Modifications<br>
        are Contributor's original creation(s) and/or Contributor has<br>

        sufficient rights to grant the rights conveyed by this<br>
 License</tt></p>
<p><tt>.</tt></p></blockquote>
<p>
<tt>3.5. Required Notices.</tt></p>
<p><tt>You must duplicate the notice in Exhibit A in each file of the Source<br>
        Code. If it is not possible to put such notice in a particular Source<br>
        Code file due to its structure, then You must include such notice in a<br>

        location (such as a relevant directory) where a user would be likely<br>
        to look for such a notice.  If You created one or more Modification(s)<br>
        You may add your name as a Contributor to the notice described in<br>
        Exhibit A. You must also duplicate this License in any documentation<br>
        for the Source Code where You describe recipients' rights or ownership<br>
        rights relating to Covered Code. You may choose to offer, and to<br>

        charge a fee for, warranty, support, indemnity or liability<br>
        obligations to one or more recipients of Covered Code. However, You<br>
        may do so only on Your own behalf, and not on behalf of the Initial<br>
        Developer or any Contributor. You must make it absolutely clear than<br>
        any such warranty, support, indemnity or liability obligation is<br>
        offered by You alone, and You hereby agree to indemnify the Initial<br>

        Developer and every Contributor for any liability incurred by the<br>
        Initial Developer or such Contributor as a result of warranty,<br>
        support, indemnity or liability terms You offer.</tt></p>
<p><tt>3.6. Distribution of Executable Versions.</tt></p>
<p><tt>You may distribute Covered Code in Executable form only if the<br>
        requirements of Section 3.1-3.5 have been met for that Covered Code,<br>
        and if You include a notice stating that the Source Code version of<br>

        the Covered Code is available under the terms of this License,<br>
        including a description of how and where You have fulfilled the<br>
        obligations of Section 3.2. The notice must be conspicuously included<br>
        in any notice in an Executable version, related documentation or<br>
        collateral in which You describe recipients' rights relating to the<br>
        Covered Code. You may distribute the Executable version of Covered<br>

        Code or ownership rights under a license of Your choice, which may<br>
        contain terms different from this License, provided that You are in<br>
        compliance with the terms of this License and that the license for the<br>
        Executable version does not attempt to limit or alter the recipient's<br>
        rights in the Source Code version from the rights set forth in this<br>
        License. If You distribute the Executable version under a different<br>

        license You must make it absolutely clear that any terms which differ<br>
        from this License are offered by You alone, not by the Initial<br>
        Developer or any Contributor. You hereby agree to indemnify the<br>
        Initial Developer and every Contributor for any liability incurred by<br>
        the Initial Developer or such Contributor as a result of any such<br>
        terms You offer.</tt></p>

<p><tt>3.7. Larger Works.</tt></p>
<p><tt>You may create a Larger Work by combining Covered Code with other<br>
 code<br>
        not governed by the terms of this License and distribute the Larger<br>
        Work as a single product. In such a case, You must make sure the<br>
        requirements of this License are fulfilled for the Covered Code.</tt></p>
</blockquote>

<p><tt>4. Inability to Comply Due to Statute or Regulation.</tt></p>
<p><tt>If it is impossible for You to comply with any of the terms of this<br>
        License with respect to some or all of the Covered Code due to<br>
        statute, judicial order, or regulation then You must: (a) comply with<br>
        the terms of this License to the maximum extent possible; and (b)<br>
        describe the limitations and the code they affect. Such description<br>
        must be included in the LEGAL file described in Section 3.4 and must<br>

        be included with all distributions of the Source Code. Except to the<br>
        extent prohibited by statute or regulation, such description must be<br>
        sufficiently detailed for a recipient of ordinary skill to be able to<br>
        understand it.</tt></p>
<p>
<tt>5. Application of this License.</tt></p>
<p><tt>This License applies to code to which the Initial Developer has<br>

        attached the notice in Exhibit A and to related Covered Code.</tt></p>
<p><tt>6. Versions of the License.</tt></p>
<blockquote><p>
<tt>6.1. New Versions.</tt></p>
<p><tt>        Sun Microsystems, Inc. ("Sun") may publish revised and/or new versions<br>
        of the License from time to time. Each version will be given a<br>
        distinguishing version number.</tt></p>

<p><tt>6.2. Effect of New Versions.</tt></p>
<p><tt> Once Covered Code has been published under a particular version of<br>
 the<br>
        License, You may always continue to use it under the terms of that<br>
        version. You may also choose to use such Covered Code under the terms<br>
        of any subsequent version of the License published by Sun. No one<br>

        other than Sun has the right to modify the terms applicable to Covered<br>
        Code created under this License.</tt></p>
<p><tt>6.3. Derivative Works.</tt></p>
<p><tt>If You create or use a modified version of this License (which you<br>
 may<br>
        only do in order to apply it to code which is not already Covered Code<br>
        governed by this License), You must: (a) rename Your license so that<br>

        the phrases "Sun," "Sun Public License," or "SPL"../ or any confusingly<br>
        similar phrase do not appear in your license (except to note that your<br>
        license differs from this License) and (b) otherwise make it clear<br>
        that Your version of the license contains terms which differ from the<br>
        Sun Public License. (Filling in the name of the Initial Developer,<br>
        Original Code or Contributor in the notice described in Exhibit A<br>

        shall not of themselves be deemed to be modifications of this<br>
        License.)</tt></p>
</blockquote>
<p><tt>7. DISCLAIMER OF WARRANTY.</tt></p>
<p><tt>COVERED CODE IS PROVIDED UNDER THIS LICENSE ON AN "../AS IS'' BASIS,<br>
        WITHOUT WARRANTY OF ANY KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING,<br>
        WITHOUT LIMITATION, WARRANTIES THAT THE COVERED CODE IS FREE OF<br>

        DEFECTS, MERCHANTABLE, FIT FOR A PARTICULAR PURPOSE OR NON-INFRINGING.<br>
        THE ENTIRE RISK AS TO THE QUALITY AND PERFORMANCE OF THE COVERED CODE<br>
        IS WITH YOU. SHOULD ANY COVERED CODE PROVE DEFECTIVE IN ANY RESPECT,<br>
        YOU (NOT THE INITIAL DEVELOPER OR ANY OTHER CONTRIBUTOR) ASSUME THE<br>
        COST OF ANY NECESSARY SERVICING, REPAIR OR CORRECTION. THIS DISCLAIMER<br>
        OF WARRANTY CONSTITUTES AN ESSENTIAL PART OF THIS LICENSE. NO USE OF<br>

        ANY COVERED CODE IS AUTHORIZED HEREUNDER EXCEPT UNDER THIS<br>
 DISCLAIMER.</tt></p>
<p><tt>8. TERMINATION.</tt></p>
<blockquote>
<p><tt>8.1. This License and the rights granted hereunder will terminate<br>
        automatically if You fail to comply with terms herein and fail to cure<br>
        such breach within 30 days of becoming aware of the breach. All<br>

        sublicenses to the Covered Code which are properly granted shall<br>
        survive any termination of this License. Provisions which, by their<br>
        nature, must remain in effect beyond the termination of this License<br>
        shall survive.</tt></p>
<p><tt>8.2. If You initiate litigation by asserting a patent infringement<br>
        claim (excluding declaratory judgment actions) against Initial Developer<br>

        or a Contributor (the Initial Developer or Contributor against whom<br>
        You file such action is referred to as "Participant")  alleging<br>
 that:</tt></p>
<blockquote>
<p><tt>(a) such Participant's Contributor Version directly or indirectly<br>
        infringes any patent, then any and all rights granted by such<br>
        Participant to You under Sections 2.1 and/or 2.2 of this License<br>

        shall, upon 60 days notice from Participant terminate prospectively,<br>
        unless if within 60 days after receipt of notice You either: (i)<br>
        agree in writing to pay Participant a mutually agreeable reasonable<br>
        royalty for Your past and future use of Modifications made by such<br>
        Participant, or (ii) withdraw Your litigation claim with respect to<br>
        the Contributor Version against such Participant.  If within 60 days<br>

        of notice, a reasonable royalty and payment arrangement are not<br>
        mutually agreed upon in writing by the parties or the litigation claim<br>
        is not withdrawn, the rights granted by Participant to You under<br>
        Sections 2.1 and/or 2.2 automatically terminate at the expiration of<br>
        the 60 day notice period specified above.</tt></p>
<p><tt>(b) any software, hardware, or device, other than such Participant's<br>

        Contributor Version, directly or indirectly infringes any patent, then<br>
        any rights granted to You by such Participant under Sections 2.1(b)<br>
        and 2.2(b) are revoked effective as of the date You first made, used,<br>
        sold, distributed, or had made, Modifications made by that<br>
        Participant.</tt></p>
</blockquote>
<p><tt>  8.3. If You assert a patent infringement claim against Participant<br>

        alleging that such Participant's Contributor Version directly or<br>
        indirectly infringes any patent where such claim is resolved (such as<br>
        by license or settlement) prior to the initiation of patent<br>
        infringement litigation, then the reasonable value of the licenses<br>
        granted by such Participant under Sections 2.1 or 2.2 shall be taken<br>
        into account in determining the amount or value of any payment or<br>

        license.</tt></p>
<p><tt>8.4. In the event of termination under Sections 8.1 or 8.2 above,<br>
 all<br>
        end user license agreements (excluding distributors and resellers)<br>
        which have been validly granted by You or any distributor hereunder<br>
        prior to termination shall survive termination.</tt></p>

</blockquote>
<p><tt>9. LIMITATION OF LIABILITY.</tt></p>
<p><tt>UNDER NO CIRCUMSTANCES AND UNDER NO LEGAL THEORY, WHETHER TORT<br>
        (INCLUDING NEGLIGENCE), CONTRACT, OR OTHERWISE, SHALL YOU, THE INITIAL<br>
        DEVELOPER, ANY OTHER CONTRIBUTOR, OR ANY DISTRIBUTOR OF COVERED CODE,<br>
        OR ANY SUPPLIER OF ANY OF SUCH PARTIES, BE LIABLE TO ANY PERSON FOR<br>
        ANY INDIRECT, SPECIAL, INCIDENTAL, OR CONSEQUENTIAL DAMAGES OF ANY<br>

        CHARACTER INCLUDING, WITHOUT LIMITATION, DAMAGES FOR LOSS OF GOODWILL,<br>
        WORK STOPPAGE, COMPUTER FAILURE OR MALFUNCTION, OR ANY AND ALL OTHER<br>
        COMMERCIAL DAMAGES OR LOSSES, EVEN IF SUCH PARTY SHALL HAVE BEEN<br>
        INFORMED OF THE POSSIBILITY OF SUCH DAMAGES. THIS LIMITATION OF<br>
        LIABILITY SHALL NOT APPLY TO LIABILITY FOR DEATH OR PERSONAL INJURY<br>
        RESULTING FROM SUCH PARTY'S NEGLIGENCE TO THE EXTENT APPLICABLE LAW<br>

        PROHIBITS SUCH LIMITATION. SOME JURISDICTIONS DO NOT ALLOW THE<br>
        EXCLUSION OR LIMITATION OF INCIDENTAL OR CONSEQUENTIAL DAMAGES, SO<br>
        THIS EXCLUSION AND LIMITATION MAY NOT APPLY TO YOU.</tt></p>
<p><tt>10. U.S. GOVERNMENT END USERS.</tt></p>
<p><tt>The Covered Code is a "commercial item," as that term is defined in<br>
 48<br>
        C.F.R. 2.101 (Oct. 1995), consisting of "commercial computer software"<br>

        and "commercial computer software documentation,"../ as such terms are<br>
        used in 48 C.F.R. 12.212 (Sept. 1995). Consistent with 48 C.F.R.<br>
        12.212 and 48 C.F.R. 227.7202-1 through 227.7202-4 (June 1995), all<br>
        U.S. Government End Users acquire Covered Code with only those rights<br>
        set forth herein.
</tt></p>
<p><tt>11. MISCELLANEOUS.</tt></p>

<p><tt>This License represents the complete agreement concerning subject<br>
        matter hereof. If any provision of this License is held to be<br>
        unenforceable, such provision shall be reformed only to the extent<br>
        necessary to make it enforceable. This License shall be governed by<br>
        California law provisions (except to the extent applicable law, if<br>
        any, provides otherwise), excluding its conflict-of-law provisions.<br>

        With respect to disputes in which at least one party is a citizen of,<br>
        or an entity chartered or registered to do business in the United<br>
        States of America, any litigation relating to this License shall be<br>
        subject to the jurisdiction of the Federal Courts of the Northern<br>
        District of California, with venue lying in Santa Clara County,<br>
        California, with the losing party responsible for costs, including<br>

        without limitation, court costs and reasonable attorneys' fees and<br>
        expenses. The application of the United Nations Convention on<br>
        Contracts for the International Sale of Goods is expressly excluded.<br>
        Any law or regulation which provides that the language of a contract<br>
        shall be construed against the drafter shall not apply to this<br>
        License.</tt></p>

<p>
<tt>12. RESPONSIBILITY FOR CLAIMS.</tt></p>
<p><tt>As between Initial Developer and the Contributors, each party is<br>
        responsible for claims and damages arising, directly or indirectly,<br>
        out of its utilization of rights under this License and You agree to<br>
        work with Initial Developer and Contributors to distribute such<br>
        responsibility on an equitable basis. Nothing herein is intended or<br>

        shall be deemed to constitute any admission of liability.
</tt></p>
<p><tt>13. MULTIPLE-LICENSED CODE.</tt></p>
<p><tt>        Initial Developer may designate portions of the Covered Code as<br>
        ?Multiple-Licensed?. ?Multiple-Licensed? means that the Initial<br>
        Developer permits you to utilize portions of the Covered Code under<br>
        Your choice of the alternative licenses, if any, specified by the<br>

        Initial Developer in the file described in Exhibit A.</tt></p>
<p><tt>Exhibit A -Sun Public License Notice.</tt></p>
<blockquote><p>
<tt>        The contents of this file are subject to the Sun Public License<br><br>
        Version 1.0 (the License); you may not use this file except in<br><br>
        compliance with the License. A copy of the License is available at<br><br>
        http://www.sun.com/<br></tt></p>
		</blockquote>
</blockquote>
<?php
}
?>
