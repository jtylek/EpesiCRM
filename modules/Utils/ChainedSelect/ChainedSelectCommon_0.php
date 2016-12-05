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
	/**
	 * @param string $dest_id - the id attribute of the element to enable chained select for
	 * @param string|array $prev_ids - the id or list of ids of the fields this field is dependent upon
	 * @param string|array $url_or_callback - a callback or file that generates the updated list of select items
	 * Three parameters are passed to the callback: values of the previous selects, parameters, id of current element
	 * @param array $params - parameters to pass to the callback or the generating file
	 * @param mixed $default_val - the default value of the field
	 */
	public static function create($dest_id, $prev_ids, $url_or_callback,array $params = null, $default_val=null) {
		if (is_callable($url_or_callback))
			return self::create_callback($dest_id, $prev_ids, $url_or_callback, $params, $default_val);
		
		load_js('modules/Utils/ChainedSelect/cs.js');
		if(empty($prev_ids))
			trigger_error('Chained select can exists only with previous selects',E_USER_ERROR);
		if (!is_array($prev_ids)) $prev_ids = array($prev_ids);
		if($params===null) $params=array();
		if($default_val===null) $default_val='';
		$js = 'var params = new Hash();';
		$_SESSION['client']['utils_chainedselect'][$dest_id] = $url_or_callback;
		foreach($params as $k=>$v)
			$js.='params.set("'.$k.'","'.$v.'");';
		eval_js($js.'new Utils_ChainedSelect("'.$dest_id.'",new Array("'.implode('","',$prev_ids).'"),params, "'.$default_val.'")');
	}
	
	public static function create_callback($dest_id, array $prev_ids, $callback, array $params = null, $default_val=null) {
		$params['__field__'] = $dest_id;
		$callback = is_array($callback)? implode('::', $callback): $callback;		 
		$callback_hash = md5(serialize($callback));
		
		$_SESSION['client']['utils_chainedselect'][$callback_hash] = $callback;
		
		$params['__callback__'] = $callback_hash;
		
		self::create($dest_id, $prev_ids, 'modules/Utils/ChainedSelect/callback.php', $params, $default_val);
	}
}

?>