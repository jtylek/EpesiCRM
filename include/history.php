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
		$session = & Epesi::get_session();
		self::$action = true;
		if(self::is_back()) $session['__history_id__']--;
		$data = DB::GetOne('SELECT data FROM history WHERE session_name=%s AND page_id=%d AND client_id=%d',array(session_id(),$session['__history_id__']-1,Epesi::get_client_id()));
		if(GZIP_HISTORY) $data = gzuncompress($data);
		$session['__module_vars__'] = unserialize($data);
		location(array());
	}
	
	public static function forward() {
		$session = & Epesi::get_session();
		if(self::is_forward()) 
			$session['__history_id__']++;
		self::$action = true;
		$data = DB::GetOne('SELECT data FROM history WHERE session_name=%s AND page_id=%d AND client_id=%d',array(session_id(),$session['__history_id__']-1,Epesi::get_client_id()));
		if(GZIP_HISTORY) $data = gzuncompress($data);
		$session['__module_vars__'] = unserialize($data);
		location(array());
	}
	
	public static function set() {
		$session = & Epesi::get_session();
		if(self::$action)
			return;

		if(!isset($session['__history_id__'])) $session['__history_id__']=0;
		$data = serialize($session['__module_vars__']);
		if(GZIP_HISTORY) $data = gzcompress($data);
		DB::Replace('history',array('data'=>$data,'page_id'=>$session['__history_id__'],'client_id'=>Epesi::get_client_id(), 'session_name'=>session_id()),array('session_name','client_id','page_id'),true);
		$session['__history_id__']++;
		DB::Execute('DELETE FROM history WHERE session_name=%s AND page_id>=%d AND client_id=%d',array(session_id(),$session['__history_id__'],Epesi::get_client_id()));
	}
	
	public static function is_back() {
		$session = & Epesi::get_session();
		if(self::$action)
			return $session['__history_id__']>1;
		return $session['__history_id__']>0;
	}
	
	public static function is_forward() {
		$session = & Epesi::get_session();
		$c = DB::GetOne('SELECT count(*) FROM history WHERE session_name=%s AND client_id=%d',array(session_id(),Epesi::get_client_id()));
		return $session['__history_id__']<$c;
	}
	
	public static function get_id() {
		static $value;
		if(isset($value)) return $value;
		$session = & Epesi::get_session();
		$value = $session['__history_id__'];
		return $value;
	}

	public static function set_id($id) {
		$session = & Epesi::get_session();
		$c = DB::GetOne('SELECT count(*) FROM history WHERE session_name=%s AND client_id=%d',array(session_id(),Epesi::get_client_id()));
		if($id<1) $id = 1;
		elseif($id>$c) $id=$c;
		$session['__history_id__']=$id;
		$data = DB::GetOne('SELECT data FROM history WHERE session_name=%s AND page_id=%d AND client_id=%d',array(session_id(),$session['__history_id__']-1,Epesi::get_client_id()));
		if(GZIP_HISTORY) $data = gzuncompress($data);
		$session['__module_vars__'] = unserialize($data);
	}
	
	public static function clear() {
		$session = & Epesi::get_session();
		unset($session['__history_id__']);
		DB::Execute('DELETE FROM history WHERE session_name=%s AND client_id=%d',array(session_id(),Epesi::get_client_id()));
	}
	
	public static function soft_call() {
		return self::$action;
	}
}

?>
