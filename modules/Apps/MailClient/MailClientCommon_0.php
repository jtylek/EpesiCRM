<?php
/**
 * Simple mail client
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package apps-mail
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');
ini_set('include_path',dirname(__FILE__).'/PEAR'.PATH_SEPARATOR.ini_get('include_path'));

class Apps_MailClientCommon extends ModuleCommon {
	public static function user_settings() {
		if(Acl::is_user()) return array('Mail accounts'=>'account_manager');
		return array();
	}

	public static function account_manager_access() {
		return Acl::is_user();
	}
	
	public static function applet_caption() {
		return "Mail indicator";
	}

	public static function applet_info() {
		return "Checks if there is new mail";
	}

	public static function applet_settings() {
		$ret = DB::Execute('SELECT id,mail FROM apps_mailclient_accounts WHERE user_login_id=%d',array(Base_UserCommon::get_my_user_id()));
		$conf = array(array('type'=>'header','label'=>'Choose accounts'));
		while($row=$ret->FetchRow())
			$conf[] = array('name'=>'account_'.$row['id'], 'label'=>$row['mail'], 'type'=>'checkbox', 'default'=>0);
		return $conf;
	}	

	public static function update_num_of_msgs($id) {
		$account = DB::GetAll('SELECT pop3_method,incoming_ssl,incoming_server,login,password,incoming_protocol FROM apps_mailclient_accounts WHERE id=%d',array($id));
		$account = $account[0];

		$host = explode(':',$account['incoming_server']);
		if(isset($host[1])) $port=$host[1];
			else $port = null;
		$host = $host[0];
		$user = $account['login'];
		$pass = $account['password'];
		$ssl = $account['incoming_ssl'];

		if($account['incoming_protocol']==0) { //pop3
			require_once('Net/POP3.php');
			$pop3 = new Net_POP3();
			
			if($port==null) {
				if($ssl) $port=995;
				else $port=110;
			}

			if(PEAR::isError( $ret= $pop3->connect(($ssl?'ssl://':'').$host , $port) )){
				return $ret->getMessage();
			}
			
			$method = $account['pop3_method']!='auto'?$account['pop3_method']:null;
			if(PEAR::isError( $ret= $pop3->login($user , $pass, $method))){
				return $ret->getMessage();
			}
			$num_msgs = $pop3->numMsg();
			$pop3->disconnect();
		} else { //imap
			require_once('Net/IMAP.php');

			if($port==null) {
				if($ssl) $port=993;
				else $port=143;
			}

			$imap = new Net_IMAP(($ssl?'ssl://':'').$host,$port);

			if(PEAR::isError( $ret= $imap->login($user , $pass))){
				return $ret->getMessage();
			}
		
			$imap->selectMailbox('inbox');
			$num_msgs = $imap->getNumberOfMessages();
			$imap->disconnect();
		}
		DB::Execute('UPDATE apps_mailclient_accounts SET num_msgs=%d WHERE id=%d',array($num_msgs,$id));
		return true;
	}
}

?>