<?php
define('_VALID_ACCESS',true);
define('CID',false);
require_once('../../../include.php');
ModuleManager::load_modules();

set_time_limit(0);


require_once ('SOAP/Server.php'); 

global $namespace;
$namespace="EpesiCalendar";

class EpesiCalendar
{
	public $__dispatch_map = array ();

	public function __construct() {
		global $namespace;
		$this->__dispatch_map['get_data']=array (
					'in' =>array ('user' =>'string','pass'=>'string','newer_then'=>'int'),
					'out' =>array ('return' => '{urn:'.$namespace.'}GetDataResult'),
			 	);
		$this->__typedef['Event'] = array(
					'id'=>'int',
					'title' => 'string',
					'description' => 'string', 
					'start_time'=>'int',
					'end_time'=>'int',
					'timeless'=>'boolean',
					'priority'=>'int',
					'color'=>'int'
				);
		$this->__typedef['ArrayOfEvents'] = array(
					array(
						'item' => '{urn:'.$namespace.'}Event'
					)
				);
		$this->__typedef['GetDataResult'] = array(
					'events' => '{urn:'.$namespace.'}ArrayOfEvents',
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
		
		$events = array();
		
		return new SOAP_Value('return','{urn:'.$namespace.'}GetDataResult',array(
				'events'=>new SOAP_Value('events','{urn:'.$namespace.'}ArrayOfEvents',$events),
				'error'=>new SOAP_Value('error','string',$error),
				));
	}
}
//go SOAP server
$server = new SOAP_Server ();
ini_set("soap.wsdl_cache_enabled", "0"); // disabling WSDL cache
//go our webservice
$webservice = new EpesiCalendar();
$server->addObjectMap ($webservice,'http://schemas.xmlsoap.org/soap/envelope/');
//if post then webservice query
if (isset ($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST') {
	ob_start();
	$server->service ($HTTP_RAW_POST_DATA);
//	error_log(ob_get_contents()."\n\n\n",3,'data/log');
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

