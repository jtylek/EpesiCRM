<?php
/**
 * history.php file
 * 
 * Maintain history array.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @license SPL
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class History {
	private static $action = false; //it is back or forward(don't save history then...)
	
	public static function back() {
		self::$action = true;
		if(self::is_back()) $_SESSION['client']['__history_id__']--;
		$data = DB::GetOne('SELECT data FROM history WHERE session_name=%s AND page_id=%d AND client_id=%d',array(session_id(),$_SESSION['client']['__history_id__']-1,CID));
		if(GZIP_HISTORY && function_exists('gzuncompress')) $data = gzuncompress($data);
		$_SESSION['client']['__module_vars__'] = unserialize($data);
		location(array());
	}
	
	public static function forward() {
		if(self::is_forward()) 
			$_SESSION['client']['__history_id__']++;
		self::$action = true;
		$data = DB::GetOne('SELECT data FROM history WHERE session_name=%s AND page_id=%d AND client_id=%d',array(session_id(),$_SESSION['client']['__history_id__']-1,CID));
		if(GZIP_HISTORY && function_exists('gzuncompress')) $data = gzuncompress($data);
		$_SESSION['client']['__module_vars__'] = unserialize($data);
		location(array());
	}
	
	public static function set() {
		if(self::$action)
			return;

		if(!isset($_SESSION['client']['__history_id__'])) $_SESSION['client']['__history_id__']=0;
		$data = serialize($_SESSION['client']['__module_vars__']);
		if(GZIP_HISTORY && function_exists('gzcompress')) $data = gzcompress($data);
		DB::StartTrans();
		DB::Replace('history',array('data'=>$data,'page_id'=>$_SESSION['client']['__history_id__'], 'session_name'=>session_id(), 'client_id'=>CID),array('session_name','page_id'),true);
		$_SESSION['client']['__history_id__']++;
		DB::Execute('DELETE FROM history WHERE session_name=%s AND (page_id>=%d OR page_id<%d) AND client_id=%d',array(session_id(),$_SESSION['client']['__history_id__'],$_SESSION['client']['__history_id__']-20,CID));
		DB::CompleteTrans();
	}
	
	public static function is_back() {
		if(self::$action)
			return $_SESSION['client']['__history_id__']>1;
		return $_SESSION['client']['__history_id__']>0;
	}
	
	public static function is_forward() {
		$c = DB::GetOne('SELECT count(*) FROM history WHERE session_name=%s AND client_id=%d',array(session_id(),CID));
		return $_SESSION['client']['__history_id__']<$c;
	}
	
	public static function get_id() {
		static $value;
		if(isset($value)) return $value;
		$value = $_SESSION['client']['__history_id__'];
		return $value;
	}

	public static function set_id($id) {
		$c = DB::GetOne('SELECT count(*) FROM history WHERE session_name=%s AND client_id=%d',array(session_id(),CID));
		if($id<1) $id = 1;
		elseif($id>$c) $id=$c;
		$_SESSION['client']['__history_id__']=intval($id);
		$data = DB::GetOne('SELECT data FROM history WHERE session_name=%s AND client_id=%d AND page_id=%d',array(session_id(),CID,$_SESSION['client']['__history_id__']-1));
		if($data===false) {
			Epesi::alert('History expired.');
			return;
		}
		if(GZIP_HISTORY && function_exists('gzuncompress')) $data = gzuncompress($data);
		$_SESSION['client']['__module_vars__'] = unserialize($data);
	}
	
	public static function clear() {
		unset($_SESSION['client']['__history_id__']);
		DB::Execute('DELETE FROM history WHERE session_name=%s AND client_id=%d',array(session_id(),CID));
	}
	
	public static function soft_call() {
		return self::$action;
	}
}

?>
