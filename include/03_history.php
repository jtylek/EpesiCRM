<?php
/**
 * history.php file
 * 
 * Maintain history array.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 0.1
 * @package tcms-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class History {
	private static $action = false; //it is back or forward(don't save history then...)
	
	public static function back() {
		$session = & $GLOBALS['base']->get_session();
		self::$action = true;
		if(self::is_back()) $session['__history_id__']--;
		$session['__module_vars__'] = $session['__history__'][$session['__history_id__']-1];
		location(array());
	}
	
	public static function forward() {
		$session = & $GLOBALS['base']->get_session();
		if(self::is_forward()) 
			$session['__history_id__']++;
		self::$action = true;
		$session['__module_vars__'] = $session['__history__'][$session['__history_id__']-1];
		location(array());
	}
	
	public static function set() {
		$session = & $GLOBALS['base']->get_session();
		if(self::$action)
			return;

		$session['__history__'][$session['__history_id__']] = $session['__module_vars__'];
		$session['__history_id__']++;
		for($i=$session['__history_id__']; $i<count($session['__history__']); $i++)
			unset($session['__history__'][$i]);
	}
	
	public static function is_back() {
		$session = & $GLOBALS['base']->get_session();
		if(self::$action)
			return $session['__history_id__']>1;
		return $session['__history_id__']>0;
	}
	
	public static function is_forward() {
		$session = & $GLOBALS['base']->get_session();
		return $session['__history_id__']<count($session['__history__']);
	}
	
	public static function get_id() {
		static $value;
		if(isset($value)) return $value;
		$session = & $GLOBALS['base']->get_session();
		$value = $session['__history_id__'];
		return $value;
	}

	public static function set_id($id) {
		$session = & $GLOBALS['base']->get_session();
		if($id<1) $id = 1;
		elseif($id>count($session['__history__'])) $id=count($session['__history__']);
		$session['__history_id__']=$id;
		$session['__module_vars__'] = $session['__history__'][$session['__history_id__']-1];
	}
	
	public static function clear() {
		$session = & $GLOBALS['base']->get_session();
		unset($session['__history_id__']);
		unset($session['__history__']);
	}
	
	public static function soft_call() {
		return self::$action;
	}
}

?>
