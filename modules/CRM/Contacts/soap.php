<?php
/**
 * CRM Contacts class.
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage contacts
 */
define('_VALID_ACCESS',true);
define('CID',false);
define('READ_ONLY_SESSION');
require_once('../../../include.php');
ModuleManager::load_modules();

@set_time_limit(0);


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
					'company' => '{urn:'.$namespace.'}ArrayOfInt',
					'title' => 'string',
					'work_phone' => 'string',
					'mobile_phone' => 'string',
					'fax'=>'string',
					'mail'=>'string',
					'web_address'=>'string',
					'address1'=>'string',
					'address2'=>'string',
					'city'=>'string',
					'country'=>'string',
					'zone'=>'string',
					'postal'=>'string',
					'home_phone'=>'string',
					'home_address1'=>'string',
					'home_address2'=>'string',
					'home_city'=>'string',
					'home_country'=>'string',
					'home_zone'=>'string',
					'home_postal'=>'string',
					'birth'=>'string'
				);
		$this->__typedef['Company'] = array(
					'id'=>'int',
					'name' => 'string',
					'short_name'=>'string',
					'phone'=>'string',
					'fax'=>'string',
					'web_address'=>'string',
					'address1'=>'string',
					'address2'=>'string',
					'city'=>'string',
					'country'=>'string',
					'zone'=>'string',
					'postal'=>'string'
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
				Acl::set_user($uid, true);
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
					'title'=> new SOAP_Value("title","string",$row['title']),
					'work_phone'=> new SOAP_Value("work_phone","string",$row['work_phone']),
					'mobile_phone'=> new SOAP_Value("mobile_phone","string",$row['mobile_phone']),
					'fax'=> new SOAP_Value("fax","string",$row['fax']),
					'mail'=> new SOAP_Value("mail","string",$row['email']),
					'web_address'=> new SOAP_Value("web_address","string",$row['web_address']),
					'address1'=> new SOAP_Value("address1","string",$row['address_1']),
					'address2'=> new SOAP_Value("address2","string",$row['address_2']),
					'city'=> new SOAP_Value("city","string",$row['city']),
					'country'=> new SOAP_Value("country","string",$row['country']),
					'zone'=> new SOAP_Value("zone","string",$row['zone']),
					'postal'=> new SOAP_Value("postal","string",$row['postal_code']),
					'home_phone'=> new SOAP_Value("home_phone","string",$row['home_phone']),
					'home_address1'=> new SOAP_Value("home_address1","string",$row['home_address_1']),
					'home_address2'=> new SOAP_Value("home_address2","string",$row['home_address_2']),
					'home_city'=> new SOAP_Value("home_city","string",$row['home_city']),
					'home_zone'=> new SOAP_Value("home_zone","string",$row['home_zone']),
					'home_country'=> new SOAP_Value("home_country","string",$row['home_country']),
					'home_postal'=> new SOAP_Value("home_postal","string",$row['home_postal_code']),
					'birth'=> new SOAP_Value("birth","string",$row['birth_date']),
					'company'=> new SOAP_Value("company",'{urn:'.$namespace.'}ArrayOfInt',$row['company_name'])
					));
			}
			$c = CRM_ContactsCommon::get_companies(array(':Edited_on'=>'>'.DB::DBTimeStamp($newer_then)));
			foreach($c as $row) {
				$companies[] = new SOAP_Value('item',
      					'{urn:'.$namespace.'}Company',array(
					'id'=> new SOAP_Value("id",'int',(int)$row['id']),
					'name'=> new SOAP_Value("name",'string',$row['company_name']),
					'short_name'=> new SOAP_Value("short_name",'string',$row['short_name']),
					'phone'=> new SOAP_Value("phone",'string',$row['phone']),
					'fax'=> new SOAP_Value("fax",'string',$row['fax']),
					'web_address'=> new SOAP_Value("web_address",'string',$row['web_address']),
					'address1'=> new SOAP_Value("address1",'string',$row['address_1']),
					'address2'=> new SOAP_Value("address2",'string',$row['address_2']),
					'city'=> new SOAP_Value("city",'string',$row['city']),
					'country'=> new SOAP_Value("country",'string',$row['country']),
					'zone'=> new SOAP_Value("zone",'string',$row['zone']),
					'postal'=> new SOAP_Value("postal",'string',$row['postal_code'])
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

