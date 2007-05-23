<?php
/*
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @licence TL
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

umask(0);


/**
 * Include database configuration file.
 */
require_once "data/config.php";

//include all other necessary files
$include_dir = "include/";
$to_include = scandir($include_dir);
foreach ($to_include as $entry)
	// Include all base files.
	if (ereg('.\.php$', $entry))
		require_once ($include_dir . $entry);




class Base extends saja {
	public $content;
	private $client_id;
	private $jses;
	public $modules;
	public $modules_instances;
	
	private function load_modules() {

		$this->modules = array ();
		$this->modules_instances = array();

		$installed_modules = ModuleManager::get_load_priority_array();
		if ($installed_modules) {
			foreach($installed_modules as $row) {
				$module = $row['name'];
				$version = $row['version'];
				ModuleManager :: include_init($module, $version);
				if(ModuleManager :: include_common($module, $version))
					ModuleManager :: create_common_virtual_classes($module, $version);
				ModuleManager :: register($module, $version, $this->modules);
			}
		} else {
			/////////////////////////////////
			require_once('install.php');
			if (!ModuleManager :: install('Setup',0)){
			    trigger_error('Unable to install default module',E_USER_ERROR);
			}
		}
	}

	public function js($str) {
		if($str!=='') $this->jses[] = strip_js($str);
	}
	
	private function & get_default_module() {
		ob_start();
		
		try {
			$default_module = Variable::get('default_module');
			$m = & ModuleManager :: new_instance($default_module);
		} catch (Exception $e) {
			$m = & ModuleManager :: new_instance('Setup');
		}
		$ret = trim(ob_get_contents());
		if(strlen($ret)>0 || $m==null) trigger_error($ret,E_USER_ERROR);
		ob_end_clean();
		return $m;
	}

	private function go(& $m) {
		//define key so it's first in array
		$this->content['main']['name'] = $m->get_path();
		$this->content['main']['module'] = & $m;
		if(MODULE_TIMES)
		    $time = microtime(true);
		//go
		ob_start();
		if (!$m->check_access('body')) {
			print ('You don\'t have permission to access default module! It\'s probably wrong configuration.');
		} else
			$m->body();
		$this->content['main']['value'] = ob_get_contents();
		ob_end_clean();
		if(MODULE_TIMES)
		    $this->content['main']['time'] = microtime(true)-$time;
	}
	
	public function process($cl_id, $url, $history_call) {
		$this->client_id = $cl_id;
		
		ob_start(array('ErrorHandler','handle_fatal'));
		
		if($history_call==='0')
		    History::clear();
		elseif($history_call)
		    History::set_id($history_call);
		
		$this->load_modules();
	
		if(DEBUG || MODULE_TIMES || SQL_TIMES)
			$debug = '';
		
		$url = str_replace('&amp;','&',$url);
		
		if($url) {
			parse_str($url, $_POST);
			$_GET = $_REQUEST = $_POST;
		}
		
		$session = & $this->get_session();

		$this->go($this->get_default_module());
		
		//on exit call methods...
		$ret = on_exit();
		foreach($ret as $k)
			call_user_func($k);
			
		//go somewhere else?
		$loc = location();
		if($loc!=false) {
			//clean up
			foreach($this->content as $k=>$v)
				unset($this->content[$k]);
//			unset($this->jses);
			$this->load_modules();
	
			//go
			return $this->process($this->client_id,$loc);
		}
		
		//clean up old modules
		foreach($session['__mod_md5__'] as $k=>$v)
			if(!array_key_exists($k, $this->content))
				unset($session['__mod_md5__'][$k]);
		
		foreach($session['__module_vars__'] as $k=>$v) {
			$xx = strrchr($k,'/');
			$xx = explode('|',$xx);
			$inst = $xx[1];
			$name = $xx[2];
			$type = substr($xx[0], 1);
			$mod = ModuleManager::get_instance($type,$inst);
			if(!$mod || $mod->get_name()!=$name)
				unset($session['__module_vars__'][$k]);
		}
		
		$reloaded = array();
		$instances_qty = array();
		foreach ($this->content as $k => $v) {
			$sum = md5($v['value']);
			
			$parent=substr($k,0,strrpos($k,'_')); 
			$reload = $v['module']->get_reload();
			
			$xx = strrchr($v['name'],'/');
			$name = explode('|',$xx);
			$name = substr($name[0], 1);
			
			if(isset($instances_qty[$name]))
				$qty = $instances_qty[$name];
			else 
				$qty = $instances_qty[$name] = count($this->modules_instances[$name]);
			
			if ((!isset($reload) && (!isset ($session['__mod_md5__'][$k]) || $session['__mod_md5__'][$k] != $sum)) || $reload == true || $reloaded[$parent] || $qty!=$session['instances_qty'][$name]) {
				if(DEBUG){
					$debug .= 'Reloading '.$v['name'].':&nbsp;&nbsp;&nbsp;&nbsp;parent='.$parent.',&nbsp;&nbsp;&nbsp;&nbsp;triggered='.(($reload==true)?'force':'auto').',&nbsp;&nbsp;cmp='.((!isset($session['__old__'][$k]))?'old_null':(strcmp($v['value'],$session['__old__'][$k]))) .'&nbsp;&nbsp;&nbsp;&nbsp;old md5='.$session['__mod_md5__'][$k].',&nbsp;&nbsp;&nbsp;&nbsp;new md5='.$sum.'<br><pre>'.htmlspecialchars($v['value']).'</pre><hr><pre>'.htmlspecialchars($session['__old__'][$k]).'</pre><hr>';
					if(@include_once('tools/Diff.php')) {
						include_once 'tools/Text/Diff/Renderer/inline.php';
						$xxx = new Text_Diff(explode("\n",$session['__old__'][$k]),explode("\n",$v['value']));
						$renderer = &new Text_Diff_Renderer_inline();
						$debug .= '<pre>'.$renderer->render($xxx).'</pre><hr>';
					}
				}
				if(MODULE_TIMES)
					$debug .= 'Time of loading module <b>'.$v['name'].'</b>: <i>'.$v['time'].'</i><hr>';
					
				$this->text($v['value'], $k . '_content');
				$session['__mod_md5__'][$k] = $sum;
				$reloaded[$v['name']] = true;
				if(method_exists($v['module'],'reloaded')) $v['module']->reloaded();
				if(DEBUG)
					$session['__old__'][$k] = $v['value'];
			} else
				$this->content[$k] = false;
		}
		if(DEBUG)
			foreach($instances_qty as $name=>$v)
				$debug .= $name.' : old='.$session['instances_qty'][$name].' new='.$v.'<hr>';
		
		$session['instances_qty'] = $instances_qty;
		
		if(DEBUG) {
			$debug .= 'vars '.$this->client_id.': '.var_export($session['__module_vars__'],true).'<br>';
			$debug .= 'user='.Acl::get_user().'<br>';
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
		return $_SESSION['cl'.$this->client_id];
	}
}

?>
