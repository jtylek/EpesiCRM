<?php
/**
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com> and Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-Utils
 * @subpackage ChainedSelect
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_ChainedSelectCommon extends ModuleCommon {
	public static function create($dest_id,array $prev_ids,$req_url,array $params = null, $default_val=null) {
		load_js('modules/Utils/ChainedSelect/cs.js');
		if(empty($prev_ids))
			trigger_error('Chained select can exists only with previous selects',E_USER_ERROR);
		if($params===null) $params=array();
		if($default_val===null) $default_val='';
		$js = 'var params = new Hash();';
		$_SESSION['client']['utils_chainedselect'][$dest_id] = $req_url;
		foreach($params as $k=>$v)
			$js.='params.set("'.$k.'","'.$v.'");';
		eval_js($js.'new ChainedSelect("'.$dest_id.'",new Array("'.implode('","',$prev_ids).'"),params, "'.$default_val.'")');
	}
}

?>