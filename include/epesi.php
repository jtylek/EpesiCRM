<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @license SPL
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

if(!class_exists('Saja')) return;

class Epesi extends Saja {
	private $client_id;
	private $jses = array();
	
	/**
	 * Initializes ajax engine.
	 * 
	 * @param string client id
	 */
	public final function init($cl) {
		$this->client_id = $cl;
		$this->jses = array();
		eval_js_once('_chj=function(href,indicator,mode){'.
			'if(saja.procOn==0 || mode==\'allow\'){'.
				'if(indicator==\'\') indicator=\'loading...\';'.
				'saja.updateIndicatorText(indicator);'.
				$this->run("process(client_id,href)",'base.php').
			'}else if(mode==\'queue\') setTimeout(\'create_href_js("\'+href+\'", "\'+indicator+\'", "\'+mode+\'")\',500);};'.
			'create_href_js=_chj;'
		);
		ModuleManager :: load_modules();
	}

	/**
	 * Extends list of javascript commands to execute
	 * 
	 * @param string javascript code
	 */
	public final function js($js) {
		if(!is_string($js) || strlen($js)==0) return false;
		if(STRIP_OUTPUT)
			$this->jses[] = strip_js($js);
		else
			$this->jses[] = $js;
		return true;
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
		foreach($this->jses as $cc) {
			$x = rtrim($cc,';');
			if($x) {
				parent::js($x);
			}
		}
		//file_put_contents('data/jses',implode($this->jses,"\n\n\n"));
		$this->jses=array();
	}
}
?>
