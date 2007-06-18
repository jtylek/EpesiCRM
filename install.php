<?php
/*
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @licence SPL
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

$ret = DB::CreateTable('modules',"name C(128) KEY,version I NOTNULL, priority I NOTNULL DEFAULT 0");
if($ret===false)
	die('Invalid SQL query - Setup module (modules table)');

$ret = DB::CreateTable('variables',"name C(32) KEY,value X");
if($ret===false)
	die('Invalid SQL query - Database module (variables table)');

$ret = DB::Execute("insert into variables values('default_module',%s)",array(serialize('Setup')));
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

?>
