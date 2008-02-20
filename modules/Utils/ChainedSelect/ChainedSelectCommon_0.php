<?php
/**
 * 
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package utils-chainedselect
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_ChainedSelectCommon extends ModuleCommon {
	public static function create($dest_id,array $prev_ids,$req_url,array $params = null, $default_val=null) {
		load_js('modules/Utils/ChainedSelect/cs.js');
		if(empty($prev_ids))
			trigger_error('Chained select can exists only with previous selects',E_USER_ERROR);
		if($params===null) $params=array();
		if($default_val===null) $default_val='';
		eval_js('new ChainedSelect("'.$dest_id.'",new Array("'.implode('","',$prev_ids).'"),"'.$req_url.'",new Array("'.implode('","',$params).'"), "'.$default_val.'")');
	}
}

?>