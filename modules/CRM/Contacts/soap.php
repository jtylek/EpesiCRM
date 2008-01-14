<?php
define('_VALID_ACCESS',true);
define('CID',false);
require_once('../../../include.php');
ModuleManager::load_modules();

require_once ('SOAP/Server.php'); 

global $namespace;
$namespace="EpesiContacts";

class EpesiContacts
{
	public $__dispatch_map = array ();

	public function __construct() {
		global $namespace;
		$this->__dispatch_map['get_contacts']=array (
					'in' =>array ('user' =>'string','pass'=>'string','newer_then'=>'int'),
					'out' =>array ('return' => '{urn:'.$namespace.'}GetContactsResult'),
			 	);
/*		$this->__dispatch_map['get_contact']=array (
					'in' =>array (),
					'out' =>array ('return' => '{urn:'.$namespace.'}Contact'),
			 	);*/
		$this->__typedef['Contact'] = array(
					'first' => 'string',
					'last' => 'string', 
					'company' => 'int'
				);
		$this->__typedef['ArrayOfContacts'] = array(
					array(
						'item' => '{urn:'.$namespace.'}Contact'
					)
				);
		$this->__typedef['GetContactsResult'] = array(
					'contacts' => '{urn:'.$namespace.'}ArrayOfContacts',
					'error' => 'string'
				);
	}
//	public function get_contact() {
//		return new SOAP_Value('return','{urn:'.$namespace.'}Contact',array('first'=>new SOAP_Value("first","string",'dupa'),'last'=>'los'));
//	}
	public function get_contacts($user,$pass,$newer_then){
		global $namespace;
		$error = '';
		$t = Variable::get('host_ban_time');
		if($t>0) {
			$fails = DB::GetOne('SELECT count(*) FROM user_login_ban WHERE failed_on>%d AND from_addr=%s',array(time()-$t,$_SERVER['REMOTE_ADDR']));
			if($fails>=3)
				$error = 'ban';
		}
		
		if($error==='') {
			$ret = Base_User_LoginCommon::check_login($user, $pass);
			if(!$ret) {
				if($t>0) {
					DB::Execute('DELETE FROM user_login_ban WHERE failed_on<=%d',array(time()-$t));
					DB::Execute('INSERT INTO user_login_ban(failed_on,from_addr) VALUES(%d,%s)',array(time(),$_SERVER['REMOTE_ADDR']));
					$fails = DB::GetOne('SELECT count(*) FROM user_login_ban WHERE failed_on>%d AND from_addr=%s',array(time()-$t,$_SERVER['REMOTE_ADDR']));
					if($fails>=3)
						$error = 'ban';
				}
				$error = 'login failed';
			} else {
				$uid = Base_UserCommon::get_user_id($user);
				Acl::set_user($uid);
			}
		}
		
		$contacts = array();
		if($error==='') {
			$c = CRM_ContactsCommon::get_contacts();
			foreach($c as $row) {
				$contacts[] = new SOAP_Value('item',
      					'{urn:'.$namespace.'}Contact',array(
					'first'=> new SOAP_Value("first","string",'x'),
					'last'=> new SOAP_Value("last","string",'y'),
					'company'=> new SOAP_Value("company","int",3)
					));
			}
		}
		return new SOAP_Value('return','{urn:'.$namespace.'}GetContactsResult',array(
				'contacts'=>new SOAP_Value('contacts','{urn:'.$namespace.'}ArrayOfContacts',$contacts),
				'error'=>new SOAP_Value('error','string',$error),
				));
	}
}
//go SOAP server
$server = new SOAP_Server ();
ini_set("soap.wsdl_cache_enabled", "0"); // disabling WSDL cache
//go our webservice
$webservice = new EpesiContacts();
$server->addObjectMap ($webservice,'http://schemas.xmlsoap.org/soap/envelope/');
//if post then webservice query
if (isset ($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST') {
	ob_start();
	$server->service ($HTTP_RAW_POST_DATA);
	error_log(ob_get_contents()."\n\n\n",3,'data/log');
	ob_end_flush();
} else { //else discovery query
	require_once ('SOAP/Disco.php'); //automatic WSDL
	$disco = new SOAP_DISCO_Server ($server, $namespace);
	header ("Content-type: text/xml");
	if (isset ($_SERVER['QUERY_STRING']) && strcasecmp ($_SERVER['QUERY_STRING'], 'wsdl') == 0) {
		echo $disco->getWSDL ();
	} else {
		echo $disco->getDISCO ();
	}
}
?>

