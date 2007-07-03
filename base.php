<?php
/*
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @licence SPL
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

umask(0);

require_once('include.php');

class Base extends saja {
	public $content;
	private $client_id;
	private $jses;
	public $modules;
	public $root;
	
	private function load_modules() {

		$this->modules = array ();

		$installed_modules = ModuleManager::get_load_priority_array(true);
		ModuleManager::$not_loaded_modules = $installed_modules;
		ModuleManager::$loaded_modules = array();
		$first_run = false;
		foreach($installed_modules as $row) {
			$module = $row['name'];
			$version = $row['version'];
			ModuleManager :: include_init($module, $version);
			if(ModuleManager :: include_common($module, $version))
				ModuleManager :: create_common_virtual_classes($module, $version);
			ModuleManager :: register($module, $version, $this->modules);
			if($module=='FirstRun') $first_run=true;
		}
		if(!$first_run && !ModuleManager :: install('FirstRun'))
			trigger_error('Unable to install default module',E_USER_ERROR);
	}

	public function js($js) {
		if(STRIP_OUTPUT)
			$this->jses[] = strip_js($js);
		else
			$this->jses[] = $js;
	}
	
	private function & get_default_module() {
		ob_start();
		
		try {
			$default_module = Variable::get('default_module');
			$m = & ModuleManager :: new_instance($default_module,null,'0');
		} catch (Exception $e) {
			$m = & ModuleManager :: new_instance('FirstRun',null,'0');
		}
		$ret = trim(ob_get_contents());
		if(strlen($ret)>0 || $m==null) trigger_error($ret,E_USER_ERROR);
		ob_end_clean();
		return $m;
	}

	private function go(& $m) {
		//define key so it's first in array
		$path = $m->get_path();
		$this->content[$path]['span'] = 'main_content';
		$this->content[$path]['module'] = & $m;
		if(MODULE_TIMES)
		    $time = microtime(true);
		//go
		ob_start();
		if (!$m->check_access('body')) {
			print ('You don\'t have permission to access default module! It\'s probably wrong configuration.');
		} else
			$m->body();
		$this->content[$path]['value'] = ob_get_contents();
		ob_end_clean();
		if(MODULE_TIMES)
		    $this->content[$path]['time'] = microtime(true)-$time;
	}
	
	public function debug($msg) {
		if(DEBUG) {
			static $msgs = '';
			if($msg) $msgs .= $msg;
			return $msgs;
		}
	}

	public function process($cl_id, $url, $history_call) {
		$this->client_id = $cl_id;
		
		ob_start(array('ErrorHandler','handle_fatal'));
		
		if($history_call==='0')
		    History::clear();
		elseif($history_call)
		    History::set_id($history_call);
		
		$url = str_replace('&amp;','&',$url);
		
		if($url) {
			parse_str($url, $_POST);
			$_GET = $_REQUEST = & $_POST;
		}

		$this->load_modules();

		$session = & $this->get_session();
		$tmp_session = & $this->get_tmp_session();
	
		//on init call methods...
		$ret = on_init(null,null,null,true);
		foreach($ret as $k)
			call_user_func_array($k['func'],$k['args']);
	
		$this->root = & $this->get_default_module();
		$this->go($this->root);
		
		//on exit call methods...
		$ret = on_exit(null,null,null,true);
		foreach($ret as $k)
			call_user_func_array($k['func'],$k['args']);
		
		//go somewhere else?
		$loc = location(null,true);
		if($loc!==false) {
			if(isset($_REQUEST['__action_module__']))
				$loc['__action_module__'] = $_REQUEST['__action_module__'];
			
			//clean up
			foreach($this->content as $k=>$v)
				unset($this->content[$k]);
			
//			unset($this->jses);
			$this->load_modules();
	
			//go
			return $this->process($this->client_id,'__location&' . http_build_query($loc));
		}

		if(DEBUG || MODULE_TIMES || SQL_TIMES) {
			$debug = '';
		}
						
		//clean up old modules
		$to_cleanup = array_keys($tmp_session['__module_content__']);
		foreach($to_cleanup as $k) {
			$mod = ModuleManager::get_instance($k);
/*		
			if(is_object($mod)) {
				if($mod->is_fast_process())
					$debug .= 'skipped2 '.$k.': '.print_r($mod,true).'<br>';
				else
					$debug .= 'OK '.$k.': '.$mod->get_path().'<br>';
			} elseif($mod===null)
				$debug .= 'skipped '.$k.': '.print_r($mod,true).'<br>';
			else*/
			if($mod === null) {
				if(DEBUG)
					$debug .= 'Clearing mod vars & module content '.$k.'<br>';
				unset($session['__module_vars__'][$k]);
				unset($tmp_session['__module_content__'][$k]);
			}
		}
		
		$reloaded = array();
		foreach ($this->content as $k => $v) {
			$reload = $v['module']->get_reload();			
			$parent = $v['module']->get_parent_path();
			
			if ((!isset($reload) && (!isset ($tmp_session['__module_content__'][$k])
				 || $tmp_session['__module_content__'][$k]['value'] !== $v['value'] //content differs
				 || $tmp_session['__module_content__'][$k]['js'] !== $v['js']))
				 || $reload == true || $reloaded[$parent]) { //force reload or parent reloaded
				if(DEBUG){
					$debug .= 'Reloading '.$k.':&nbsp;&nbsp;&nbsp;&nbsp;parent='.$v['module']->get_parent_path().';&nbsp;&nbsp;&nbsp;&nbsp;span='.$v['span'].',&nbsp;&nbsp;&nbsp;&nbsp;triggered='.(($reload==true)?'force':'auto').',&nbsp;&nbsp;cmp='.((!isset($tmp_session['__old__'][$k]))?'old_null':(strcmp($v['value'],$tmp_session['__old__'][$k]))) .'&nbsp;&nbsp;&nbsp;&nbsp;<pre>'.htmlspecialchars($v['value']).'</pre><hr><pre>'.htmlspecialchars($tmp_session['__module_content__'][$k]).'</pre><hr>';
					if(@include_once('tools/Diff.php')) {
						include_once 'tools/Text/Diff/Renderer/inline.php';
						$xxx = new Text_Diff(explode("\n",$tmp_session['__module_content__'][$k]['value']),explode("\n",$v['value']));
						$renderer = &new Text_Diff_Renderer_inline();
						$debug .= '<pre>'.$renderer->render($xxx).'</pre><hr>';
					}
				}
				if(MODULE_TIMES)
					$debug .= 'Time of loading module <b>'.$k.'</b>: <i>'.$v['time'].'</i><hr>';
				
				if(isset($v['span']))
					$this->text($v['value'], $v['span']);
				$this->jses[] = join(";",$v['js']);
				$tmp_session['__module_content__'][$k]['value'] = $v['value'];
				$tmp_session['__module_content__'][$k]['js'] = $v['js'];				
				$tmp_session['__module_content__'][$k]['parent'] = $parent;				
				$reloaded[$k] = true;
				if(method_exists($v['module'],'reloaded')) $v['module']->reloaded();
			}
		}
		
		foreach($tmp_session['__module_content__'] as $k=>$v)
			if(!array_key_exists($k,$this->content) && $reloaded[$v['parent']]) {
				if(DEBUG)
					$debug .= 'Reloading missing '.$k.'<hr>';
				if(isset($v['span']))
					$this->text($v['value'], $v['span']);
				$this->jses[] = join(";",$v['js']);	
				$reloaded[$k] = true;
			}
	
		if(DEBUG) {
			$debug .= 'vars '.$this->client_id.': '.print_r($session['__module_vars__'],true).'<br>';
			$debug .= 'user='.Acl::get_user().'<br>';
			$debug .= 'action module='.$_REQUEST['__action_module__'].'<br>';
			$debug .= $this->debug();
		}
		
		if(SQL_TIMES) {
			$debug .= '<font size="+1">QUERIES</font><br>';
			$queries = DB::GetQueries();
			foreach($queries as $q)
				$debug .= '<b>'.$q['func'].'</b> '.var_export($q['args'],true).' <i>'.$q['time'].'</i><br>';
		}
		if(DEBUG || MODULE_TIMES || SQL_TIMES)
			$this->text($debug,'debug');
		
		if(!$history_call && !History::soft_call()) {
		        History::set();
		}
		
		if(!$history_call) {
//			$this->redirect('#'.History::get_id());
			$this->js('history_add('.History::get_id().')');
		}
		
		foreach($this->jses as $cc)
			parent::js($cc);
		
		
		ob_end_flush();
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
}

?>
