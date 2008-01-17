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
		$this->__dispatch_map['get_data']=array (
					'in' =>array ('user' =>'string','pass'=>'string','newer_then'=>'int'),
					'out' =>array ('return' => '{urn:'.$namespace.'}GetDataResult'),
			 	);
		$this->__typedef['Contact'] = array(
					'id'=>'int',
					'first' => 'string',
					'last' => 'string', 
					'company' => '{urn:'.$namespace.'}ArrayOfInt'
				);
		$this->__typedef['Company'] = array(
					'id'=>'int',
					'name' => 'string'
				);
		$this->__typedef['ArrayOfContacts'] = array(
					array(
						'item' => '{urn:'.$namespace.'}Contact'
					)
				);
		$this->__typedef['ArrayOfInt'] = array(
					array(
						'item' => 'int'
					)
				);
		$this->__typedef['ArrayOfCompanies'] = array(
					array(
						'itemc' => '{urn:'.$namespace.'}Company'
					)
				);
		$this->__typedef['GetDataResult'] = array(
					'contacts' => '{urn:'.$namespace.'}ArrayOfContacts',
					'companies' => '{urn:'.$namespace.'}ArrayOfCompanies',
					'error' => 'string'
				);
	}
	
	private function auth($user,$pass) {
		$error = '';
		$t = Variable::get('host_ban_time');
		if($t>0) {
			$fails = DB::GetOne('SELECT count(*) FROM user_login_ban WHERE failed_on>%d AND from_addr=%s',array(time()-$t,$_SERVER['REMOTE_ADDR']));
			if($fails>=3)
				$error = 'Host banned.';
		}
		
		if($error==='') {
			$ret = Base_User_LoginCommon::check_login($user, $pass);
			if(!$ret) {
				$error = 'Login failed.';
				if($t>0) {
					DB::Execute('DELETE FROM user_login_ban WHERE failed_on<=%d',array(time()-$t));
					DB::Execute('INSERT INTO user_login_ban(failed_on,from_addr) VALUES(%d,%s)',array(time(),$_SERVER['REMOTE_ADDR']));
					$fails = DB::GetOne('SELECT count(*) FROM user_login_ban WHERE failed_on>%d AND from_addr=%s',array(time()-$t,$_SERVER['REMOTE_ADDR']));
					if($fails>=3)
						$error .= ' Host banned.';
				}
			} else {
				$uid = Base_UserCommon::get_user_id($user);
				Acl::set_user($uid);
			}
		}
		return $error;
	}

	public function get_data($user,$pass,$newer_then=0){
		global $namespace;
		
		$error = $this->auth($user,$pass);
		
		$contacts = array();
		$companies = array();
		if($error==='') {
			$c = CRM_ContactsCommon::get_contacts(array(':Edited_on'=>'>'.DB::DBTimeStamp($newer_then)));
			foreach($c as $row) {
				foreach($row['company_name'] as &$v)
					$v = (int)$v;
				$contacts[] = new SOAP_Value('item',
      					'{urn:'.$namespace.'}Contact',array(
					'id'=> new SOAP_Value("id","int",(int)$row['id']),
					'first'=> new SOAP_Value("first","string",$row['first_name']),
					'last'=> new SOAP_Value("last","string",$row['last_name']),
					'company'=> new SOAP_Value("company",'{urn:'.$namespace.'}ArrayOfInt',$row['company_name'])
					));
			}
			$c = CRM_ContactsCommon::get_companies(array(':Edited_on'=>'>'.DB::DBTimeStamp($newer_then)));
			foreach($c as $row) {
				$companies[] = new SOAP_Value('item',
      					'{urn:'.$namespace.'}Company',array(
					'id'=> new SOAP_Value("id",'int',(int)$row['id']),
					'name'=> new SOAP_Value("name",'string',$row['company_name'])
					));
			}
		}
		return new SOAP_Value('return','{urn:'.$namespace.'}GetDataResult',array(
				'companies'=>new SOAP_Value('companies','{urn:'.$namespace.'}ArrayOfCompanies',$companies),
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

