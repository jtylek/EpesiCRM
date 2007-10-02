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
	private static $client_id = null;
	private static $jses = array();
	private static $txts = '';
	
	/**
	 * Initializes ajax engine.
	 * 
	 * @param string client id
	 */
	public final static function init($register_shutdown=true, $client_id = null) {
		if(isset($_SERVER['HTTP_CLIENT_ID']))
			self::$client_id = $_SERVER['HTTP_CLIENT_ID'];
		elseif($client_id!==null)
			self::$client_id = $client_id;
		else
			trigger_error('Invalid request without client id');
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
	
	public final static function redirect($addr='') {
		self::js('document.location=\''.escapeJS($addr).'\'');
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

	//============================================
	public static $content;
	
	private static function check_firstrun() {
		$first_run = false;

		foreach(ModuleManager::$modules as $row) {
			$module = $row['name'];
			if($module=='FirstRun') $first_run=true;
		}
		ob_start();
		if(!$first_run && !ModuleManager :: install('FirstRun')) {
			$x = ob_get_contents();
			ob_end_clean();
			trigger_error('Unable to install default module: '.$x,E_USER_ERROR);
		}
		ob_end_clean();
	}

	private static function go(& $m) {
		//define key so it's first in array
		$path = $m->get_path();
		self::$content[$path]['span'] = 'main_content';
		self::$content[$path]['module'] = & $m;
		if(MODULE_TIMES)
		    $time = microtime(true);
		//go
		ob_start();
		if (!$m->check_access('body')) {
			print ('You don\'t have permission to access default module! It\'s probably wrong configuration.');
		} else
			$m->body();
		self::$content[$path]['value'] = ob_get_contents();
		ob_end_clean();
		self::$content[$path]['js'] = $m->get_jses();

		if(MODULE_TIMES)
		    self::$content[$path]['time'] = microtime(true)-$time;
	}
	
	public static function debug($msg=null) {
		if(DEBUG) {
			static $msgs = '';
			if($msg) $msgs .= $msg;
			return $msgs;
		}
	}
	
	public static function process($url, $history_call=false,$refresh=false) {
		if(MODULE_TIMES) 
			$time = microtime(true);

		$url = str_replace('&amp;','&',$url);
			
		if($url) {
			parse_str($url, $_POST);
			$_GET = $_REQUEST = & $_POST;
		}

		self::init();
		self::check_firstrun();

		if($history_call==='0')
		    History::clear();
		elseif($history_call)
		    History::set_id($history_call);
		
		$session = & self::get_session();
		$tmp_session = & self::get_tmp_session();
	
		//on init call methods...
		$ret = on_init(null,null,null,true);
		foreach($ret as $k)
			call_user_func_array($k['func'],$k['args']);
	
		$root = & ModuleManager::create_root();
		self::go($root);
		
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
			foreach(self::$content as $k=>$v)
				unset(self::$content[$k]);
			
//			unset(self::$jses);
//			ModuleManager::load_modules();
	
			//go
			return self::process('__location&' . http_build_query($loc),false,true);
		}

		if(DEBUG || MODULE_TIMES || SQL_TIMES) {
			$debug = '';
			if(DEBUG && ($debug_diff = @include_once('tools/Diff.php'))) {
				require_once 'tools/Text/Diff/Renderer/inline.php';
				$diff_renderer = &new Text_Diff_Renderer_inline();
			}
		}
						
		//clean up old modules
		if(isset($tmp_session['__module_content__'])) {
			$to_cleanup = array_keys($tmp_session['__module_content__']);
			foreach($to_cleanup as $k) {
				$mod = ModuleManager::get_instance($k);
				if($mod === null) {
					$xx = explode('/',$k);
					$yy = explode('|',$xx[count($xx)-1]);
					$mod = $yy[0];
					if(!is_callable(array($mod.'Common','destroy')) || !call_user_func(array($mod.'Common','destroy'),$k,isset($session['__module_vars__'][$k])?$session['__module_vars__'][$k]:null)) {
						if(DEBUG)
							$debug .= 'Clearing mod vars & module content '.$k.'<br>';
						unset($session['__module_vars__'][$k]);
						unset($tmp_session['__module_content__'][$k]);
					}
				}
			}
		}
		
		$reloaded = array();
		foreach (self::$content as $k => $v) {
			$reload = $v['module']->get_reload();			
			$parent = $v['module']->get_parent_path();
			if(DEBUG) {
				$debug .= '<hr style="height: 3px; background-color:black">';
				$debug .= '<b> Checking '.$k.', &nbsp;&nbsp;&nbsp; parent='.$v['module']->get_parent_path().'</b><ul>'.
					'<li>Force - '.(isset($reload)?print_r($reload,true):'not set').'</li>'.
					'<li>First display - '.(isset ($tmp_session['__module_content__'][$k])?'no</li><li>Content changed - '.(($tmp_session['__module_content__'][$k]['value'] !== $v['value'])?'yes':'no').'</li><li>JS changed - '.(($tmp_session['__module_content__'][$k]['js'] !== $v['js'])?'yes':'no'):'yes').'</li>'.
					'<li>Parent reloaded - '.(isset($reloaded[$parent])?'yes':'no').'</li>'.
					'</ul>';
			}
			if ((!isset($reload) && (!isset ($tmp_session['__module_content__'][$k])
				 || $tmp_session['__module_content__'][$k]['value'] !== $v['value'] //content differs
				 || $tmp_session['__module_content__'][$k]['js'] !== $v['js']))
				 || $reload == true || isset($reloaded[$parent])) { //force reload or parent reloaded
				if(DEBUG && isset($tmp_session['__module_content__'])){
					$debug .= '<b>Reloading: '.(isset($v['span'])?';&nbsp;&nbsp;&nbsp;&nbsp;span='.$v['span'].',':'').'&nbsp;&nbsp;&nbsp;&nbsp;triggered='.(($reload==true)?'force':'auto').',&nbsp;&nbsp;</b><hr><b>New value:</b><br><pre>'.htmlspecialchars($v['value']).'</pre>'.(isset($tmp_session['__module_content__'][$k]['value'])?'<hr><b>Old value:</b><br><pre>'.htmlspecialchars($tmp_session['__module_content__'][$k]['value']).'</pre>':'');
					if($debug_diff && isset($tmp_session['__module_content__'][$k]['value'])) {
						$xxx = new Text_Diff(explode("\n",$tmp_session['__module_content__'][$k]['value']),explode("\n",$v['value']));
						$debug .= '<hr><b>Diff:</b><br><pre>'.$diff_renderer->render($xxx).'</pre>';
					}
					$debug .= '<hr style="height: 5px; background-color:black">';
				}
				
				if(isset($v['span']))
					self::text($v['value'], $v['span']);
				if($v['js'])
					self::js('_ajs(\''.self::escapeJS(join(";",$v['js'])).'\')');
				$tmp_session['__module_content__'][$k]['value'] = $v['value'];
				$tmp_session['__module_content__'][$k]['js'] = $v['js'];				
				$tmp_session['__module_content__'][$k]['parent'] = $parent;				
				$reloaded[$k] = true;
				if(method_exists($v['module'],'reloaded')) $v['module']->reloaded();
			}
		}
		
		foreach($tmp_session['__module_content__'] as $k=>$v)
			if(!array_key_exists($k,self::$content) && isset($reloaded[$v['parent']])) {
				if(DEBUG)
					$debug .= 'Reloading missing '.$k.'<hr>';
				if(isset($v['span']))
					self::text($v['value'], $v['span']);
				if($v['js'])
					self::js('_ajs(\''.self::escapeJS(join(";",$v['js'])).'\')');
				$reloaded[$k] = true;
			}
	
		if(DEBUG) {
			$debug .= 'vars '.self::get_client_id().': '.print_r($session['__module_vars__'],true).'<br>';
			$debug .= 'user='.Acl::get_user().'<br>';
			if(isset($_REQUEST['__action_module__']))
				$debug .= 'action module='.$_REQUEST['__action_module__'].'<br>';
			$debug .= self::debug();
		}
		
		if(MODULE_TIMES) {
			foreach (self::$content as $k => $v)
				$debug .= 'Time of loading module <b>'.$k.'</b>: <i>'.$v['time'].'</i><br>';
			$debug .= 'Page renderered in '.(microtime(true)-$time).'s<hr>';
		}

		if(SQL_TIMES) {
			$debug .= '<font size="+1">QUERIES</font><br>';
			$queries = DB::GetQueries();
			foreach($queries as $q)
				$debug .= '<b>'.$q['func'].'</b> '.var_export($q['args'],true).' <i>'.$q['time'].'</i><br>';
		}
		if(DEBUG || MODULE_TIMES || SQL_TIMES)
			self::text($debug,'debug');
		
		if(!$history_call && !History::soft_call()) {
		        History::set();
		}
		
		if(!$history_call) {
			self::js('history_add('.History::get_id().')');
		}

		self::send_output();
	}


}
?>
