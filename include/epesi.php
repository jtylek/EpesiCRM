<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @license SPL
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Epesi {
	private static $client_id;
	private static $jses = array();
	private static $txts = '';
	
	/**
	 * Initializes ajax engine.
	 * 
	 * @param string client id
	 */
	public final static function init($cl,$register_shutdown=true) {
		self::$client_id = $cl;
		self::$jses = array();
		ModuleManager :: load_modules();
	}

	/**
	 * Extends list of javascript commands to execute
	 * 
	 * @param string javascript code
	 */
	public final static function js($js) {
		if(!is_string($js) || strlen($js)==0) return false;
		if(STRIP_OUTPUT)
			self::$jses[] = strip_js($js);
		else
			self::$jses[] = $js;
		return true;
	}
	
	/**
	 * Returns client id.
	 * 
	 * @return string client id
	 */
	public final static function get_client_id() {
	        return self::$client_id;
	}
	
	/**
	 * Returns current ajax session.
	 * 
	 * @return mixed ajax session
	 */
	public final static function & get_session() {
		return $_SESSION['cl'.self::$client_id]['stable'];
	}

	/**
	 * Returns ajax temporary session.
	 * 
	 * @return mixed ajax temporary session
	 */
	public final static function & get_tmp_session() {
		return $_SESSION['cl'.self::$client_id]['tmp'];
	}

	/**
	 * Executes list of javascrpit commands gathered with js() function.
	 */
	public final static function send_output() {
		print(self::get_output());
	}

	public final static function get_output() {
		$ret = self::$txts;
		foreach(self::$jses as $cc) {
			$x = rtrim($cc,';');
			if($x) $ret.=$x.';';
		}
		self::clean();
		//file_put_contents('data/jses',implode(self::$jses,"\n\n\n"));
		return $ret;
	}
	
	public final static function clean() {
		self::$txts = '';
		self::$jses=array();
	}
	
	public final static function text($txt,$id,$type='instead') {
		//self::$txts .= 'Epesi.text(decodeURIComponent(\''.rawurlencode(utf8_encode($txt)) .'\'),\''.self::escapeJS($id).'\',\''.self::escapeJS($type{0}).'\');';
		self::$txts .= 'Epesi.text(\''.self::escapeJS($txt).'\',\''.self::escapeJS($id).'\',\''.self::escapeJS($type{0}).'\');';
	}
	
	public final static function alert($txt) {
		self::$jses[] = 'alert(\''.self::escapeJS($txt).'\')';
	}

	/**
	 * Escapes special characters in js code.
	 * 
	 * @param string js code to escape
	 * @return string escaped js code
	 */
	public final static function escapeJS($str) {
		// borrowed from smarty
		return strtr($str, array (
			'\\' => '\\\\',
			"'" => "\\'",
			'"' => '\\"',
			"\r" => '\\r',
			"\n" => '\\n',
			'</' => '<\/'
		));
	}
}
?>
