<?php
/**
 * Cache class.
 * 
 * Displays file content
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.2
 * @license SPL
 * @package epesi-utils
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * Displays file content
 * 
 * @package epesi-utils
 * @subpackage Cache
 */
abstract class Utils_Cache_Base extends Module {
	protected $id;
	protected $interval;
	private $modules_contents;
	private $modules_instances;
	private $parent_vars;

	public function body() {
	}
	
	public function construct($options, $interval=60) {
		$this->interval = $interval;
		$this->id = md5($this->parent->get_path().serialize($options));
	}
	
	private function start_cache() {
		$session = & $GLOBALS['base']->get_sesion();
		$this->modules_instances = $base->modules_instances;
		$this->modules_contents = $base->content;
		$this->parent_vars = $session['__module_vars__'][$this->parent->get_path()];

		ob_start();
	}
	
	public function cached() {
		global $base;
		$session = & $base->get_session();
		$ret = $this->_in_cache();
		if($ret) {
			$ret = unserialize($this->_load());

			//sprawdz czy unique_href odwoluja sie do jakiegos dziecka...
			$id = $this->parent->get_path();
			foreach($_REQUEST as $k=>$v) {
				if(strncmp($k,$id,strlen($id))==0) {
					print('fuck<br>');
					$this->start_cache();
					return false;
				}
			}

			if(DEBUG) print('CACHED<hr>');
			print($ret['this']);
			foreach($ret['instances'] as $k=>$v) {
//				print($k.'<hr>');
				if(array_key_exists($k,$base->module_instaces))
					$base->modules_instances[$k] = array_merge($base->modules_instances[$k],$v);
				else
					$base->modules_instances[$k] = $v;
			}
			foreach($ret['vars'] as $mod=>$var) {
//				print($mod.'=>'.print_r($var,true).'<hr>');
				if(array_key_exists($mod,$session['__module_vars__']))
					$session['__module_vars__'][$mod] = array_merge($session['__module_vars__'][$mod],$var);
				else
					$session['__module_vars__'][$mod] = $var;
			}
			$base->content = array_merge($base->content,$ret['contents']);
			return true;
		}
		
		$this->start_cache();
		
		return false;
	}
	
	protected function _in_cache() {
		return false;
	}
	
	public function save() {
		$ret = array();
		$ret['this'] = ob_get_contents();
		ob_end_flush();
		$session = & Epesi::get_session();
		
		//instances
		$diff = array();
		foreach($base->modules_instances as $k=>$v) {
			if(array_key_exists($k,$this->modules_instances))
				$diff[$k] = array_diff_key($v,$this->modules_instances[$k]);
			else
				$diff[$k] = $v;
			if(empty($diff[$k])) unset($diff[$k]);
		}
		$ret['instances'] = & $diff;
		
		//children vars
		foreach($diff as $k=>$v)
			foreach($v as $x=>$y) {
				$id = $y->get_path();
				$ret['vars'][$id] = $session['__module_vars__'][$id];
			}
		//parent vars
		$id = $this->parent->get_path();
		foreach($session['__module_vars__'][$id] as $k=>$v) {
			if(!array_key_exists($k,$this->parent_vars) || 
				(array_key_exists($k,$this->parent_vars) && $this->parent_vars[$k]!=$v))
					$ret['vars'][$id][$k] = $v;
		}
//		$ret['vars'][$id] = array_diff_key(,$this->parent_vars);
		
		//contents
		$ret['contents'] = array_diff_key($base->content,$this->modules_contents);
		
		//print_r($ret);
		
		$this->_save(serialize($ret));
	}
	
	abstract protected function _load();
	abstract protected function _save($str);
}

?>
