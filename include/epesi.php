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
	
	public function init($cl) {
		ob_start(array('ErrorHandler','handle_fatal'));
		
		$this->client_id = $cl;
		$this->jses = array();
		ModuleManager :: load_modules();
	}

	public function js($js) {
		if(STRIP_OUTPUT)
			$this->jses[] = strip_js($js);
		else
			$this->jses[] = $js;
	}
	
	public function get_client_id() {
	        return $this->client_id;
	}
	
	public function & get_session() {
		return $_SESSION['cl'.$this->client_id]['stable'];
	}

	public function & get_tmp_session() {
		return $_SESSION['cl'.$this->client_id]['tmp'];
	}

	public function call_jses() {
		foreach($this->jses as $cc)
			parent::js($cc);
	}
}
?>
