<?php
/*
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @licence SPL
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

if(!class_exists('saja')) return;

class Epesi extends saja {
	private $client_id;
	private $jses;
	
	/**
	 * Initializes ajax engine.
	 * 
	 * @param string client id
	 */
	public final function init($cl) {
		$this->client_id = $cl;
		$this->jses = array();
		ModuleManager :: load_modules();
	}

	/**
	 * Extends list of javascript commands to execute
	 * 
	 * @param string javascript code
	 */
	public final function js($js) {
		if(STRIP_OUTPUT)
			$this->jses[] = strip_js($js);
		else
			$this->jses[] = $js;
	}
	
	/**
	 * Returns client id.
	 * 
	 * @return string client id
	 */
	public final function get_client_id() {
	        return $this->client_id;
	}
	
	/**
	 * Returns current ajax session.
	 * 
	 * @return mixed ajax session
	 */
	public final function & get_session() {
		return $_SESSION['cl'.$this->client_id]['stable'];
	}

	/**
	 * Returns ajax temporary session.
	 * 
	 * @return mixed ajax temporary session
	 */
	public final function & get_tmp_session() {
		return $_SESSION['cl'.$this->client_id]['tmp'];
	}

	/**
	 * Executes list of javascrpit commands gathered with js() function.
	 */
	public final function call_jses() {
		foreach($this->jses as $cc)
			parent::js($cc);
		$this->jses=array();
	}
}
?>
