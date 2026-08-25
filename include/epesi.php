<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @license MIT
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Epesi {
	private static $jses = array();
	private static $load_jses = array();
	private static $load_csses = array();
	private static $txts = '';

	/**
	 * Returns ajax temporary session.
	 *
	 * @return mixed ajax temporary session
	 */
	/**
	 * Executes list of javascrpit commands gathered with js() function.
	 */
	public final static function send_output() {
		print(self::get_output());
	}
	
	public final static function prepare_minified_files($arr) {
		$out = array();
		require_once('libs/minify/Minify/Build.php');
		foreach($arr as $loader=>$css) {
			if($loader=='') {
			    foreach($css as $c2)
    			    $out[] = $c2;
			} else  {
			    if(DEBUG_CSS) {
			        foreach($css as $c2) {
		            	$out[] = $c2;
			        }
			    } else {
        			$csses_build = new Minify_Build($css);
	        		$f = $csses_build->uri($loader.'?'.http_build_query(array('f'=>array_values($css))));
		        	$out[] = $f;
		        }
		    }
		}
		return $out;
	}
	
	public final static function get_csses() {
		return self::prepare_minified_files(self::$load_csses);
	}

	public final static function get_jses() {
		return self::prepare_minified_files(self::$load_jses);
	}
	
	public final static function get_eval_jses() {
		$jjj = '';
		foreach(self::$jses as $cc) {
			$x = rtrim($cc[0],';');
			if ($x) {
                if (DEBUG_JS) {
                    $debug_info = isset($cc[2]) ? "/* {$cc[2]} */\n" : '';
                    $jjj .= $debug_info . $x . ";\n";
                } else {
                    $jjj .= $x . ';';
                }
            }
		}
		return $jjj;
	}

	public final static function get_content() {
		return self::$txts;
	}

	public final static function get_output() {
		$ret = '';
		$out_css = self::get_csses();
		foreach($out_css as $css) {
			$ret .= 'Epesi.load_css(\''.self::escapeJS($css,false).'\');';
		}
		$out_js = self::get_jses();
		foreach($out_js as $js) {
			$ret .= 'Epesi.load_js(\''.self::escapeJS($js,false).'\');';
		}
		$ret .= self::$txts;
		$jjj = self::get_eval_jses();
		if($jjj!=='')
			$ret .= 'Epesi.append_js(\''.self::escapeJS($jjj,false).'\');';
		self::clean();
		return $ret;
	}

	public final static function clean() {
		self::$txts = '';
		self::$jses = array();
		self::$load_jses = array();
	}

	// load_js()/load_css() mark a URL as sent in $_SESSION the instant they're
	// called, before the response is actually flushed to the client - correct
	// for the normal get_output() path (clean() only runs after $ret already
	// captured the queued Epesi.load_js()/load_css() calls), but wrong for an
	// abort: if a fatal error elsewhere in the same request reaches
	// ErrorHandler::notify_client(), it discards everything queued so far and
	// sends only the error alert - any load_js()/load_css() calls already made
	// this request are silently dropped without ever reaching the browser,
	// while the session still thinks they were delivered. Every later render
	// of that same field/module (same client session) then skips re-queuing
	// the script/stylesheet load but still runs code that depends on it -
	// e.g. HTML_QuickForm_autocomplete's eval_js('new EpesiAutocompleter(...)')
	// throwing "EpesiAutocompleter is not defined" forever after, since
	// autocomplete.js never actually loaded. Release the pending URLs' session
	// flags before discarding so the next request retries them.
	public final static function discard() {
		foreach (self::$load_jses as $urls)
			foreach ($urls as $u)
				unset($_SESSION['client']['__loaded_jses__'][$u]);
		foreach (self::$load_csses as $urls)
			foreach ($urls as $u)
				unset($_SESSION['client']['__loaded_csses__'][$u]);
		self::$load_csses = array();
		self::clean();
	}

	public final static function load_js($u,$loader=null) {
		if(!is_string($u) || strlen($u)==0) return false;
		if (!isset($_SESSION['client']['__loaded_jses__'][$u])) {
    		if (!isset($loader)) $loader = 'serve.php';
			if(!isset(self::$load_jses[$loader])) self::$load_jses[$loader] = array();
			self::$load_jses[$loader][] = $u;
			$_SESSION['client']['__loaded_jses__'][$u] = true;
			return true;
		}
		return false;
	}

	public final static function load_css($u,$loader=null) {
		if(!is_string($u) || strlen($u)==0) return false;
		if (!isset($_SESSION['client']['__loaded_csses__'][$u])) {
    		if (!isset($loader)) $loader = 'serve.php';
			if(!isset(self::$load_csses[$loader])) self::$load_csses[$loader] = array();
			self::$load_csses[$loader][] = $u;
			$_SESSION['client']['__loaded_csses__'][$u] = true;
			return true;
		}
		return false;
	}

	public final static function text($txt,$id,$type='instead') {
		self::$txts .= 'Epesi.text(\''.self::escapeJS($txt,false).'\',\''.self::escapeJS($id,false).'\',\''.self::escapeJS($type[0],false).'\');';
	}

	public final static function alert($txt,$del = false) {
		// epesi_alert(), when defined (see Module::inject_alert_modal()), shows a styled
		// AdminLTE Bootstrap modal instead of the native alert() popup; the typeof check keeps
		// this safe for contexts where that never got injected (non-AdminLTE theme, or a raw
		// JS response that bypasses the normal page render - see inject_alert_modal()'s doc).
		$msg = self::escapeJS($txt,false);
		self::js('if(typeof epesi_alert===\'function\'){epesi_alert(\''.$msg.'\')}else{alert(\''.$msg.'\')}',$del);
	}

	public final static function redirect($addr='') {
		self::js('document.location=\''.self::escapeJS($addr,false).'\'');
	}

	/**
	 * Extends list of javascript commands to execute
	 *
	 * @param string javascript code
	 */
	public final static function js($js,$del_on_loc=true) {
		if(!is_string($js) || strlen($js)==0) return false;
		$js = rtrim($js,';');
		if(STRIP_OUTPUT) {
			require_once('libs/minify/JSMin.php');
			$js = JSmin::minify($js);
		}
        $js_def = array($js,$del_on_loc);
        if (DEBUG_JS && function_exists('debug_backtrace')) {
            $arg = false;
            if (version_compare(PHP_VERSION, '5.3.6', '>=')) {
                $arg = DEBUG_BACKTRACE_IGNORE_ARGS;
            }
            $bt = debug_backtrace($arg);
            array_shift($bt); // remove first, because it's this function
            $debug_str = '';
            $limit = (int) DEBUG_JS;
            while ($limit--) {
                $x = array_shift($bt);
                if (!$x) break;
                $file = & $x['file'];
                $line = & $x['line'];
                $func = & $x['function'];
                $debug_str .= "$func ($file:$line)";
                if ($limit) $debug_str .= ' <-- ';
            }
            $js_def[] = $debug_str;
        }
		self::$jses[] = $js_def;
		return true;
	}

	/**
	 * Escapes special characters in js code.
	 *
	 * @param string $str js code to escape
     * @param bool $double escape double quotes
     * @param bool $single escape single quotes
	 * @return string escaped js code
	 */
	public final static function escapeJS($str,$double=true,$single=true) {
		$arr = array (
			'\\' => '\\\\',
			"\r" => '\\r',
			"\n" => '\\n',
			'</' => '<\/',
			"\xe2\x80\xa8" => '\\u2028',
			"\xe2\x80\xA9" => '\\u2029'
		);
		if($single)
			$arr["'"] = "\\'";
		if($double)
			$arr['"'] = '\\"';
		// borrowed from smarty
		return strtr($str, $arr);
	}

	//============================================
	public static $content;

	private static function check_firstrun() {
		$first_run = false;

		foreach(ModuleManager::$modules as $name=>$version) {
			if($name==FIRST_RUN) $first_run=true;
		}
		ob_start();
		if(!$first_run) {
            if (ModuleManager :: install(FIRST_RUN)) {
                $processed_modules = ModuleManager::get_processed_modules();
                $_SESSION['first-run_post-install'] = $processed_modules['install'];                
            } else {
                $x = ob_get_contents();
                ob_end_clean();
                trigger_error('Unable to install default module: '.$x,E_USER_ERROR);
            }
		}
		ob_end_clean();
	}

	/**
	 * @param $m Module
     */
	
	private static function go($m) {
		self::register_custom_qf_types(); // PHP 8.2 migration: ensure custom QF element types are registered before any form renders (REVERSIBLE — see function def)
		//define key so it's first in array
		$path = $m->get_path();
	/*private static function go($m) {
		//define key so it's first in array
		$path = $m->get_path();*/

		if(method_exists($m,'construct')) {
			ob_start();
			call_user_func_array(array($m,'construct'),array());
			ob_end_clean();
		}

		self::$content[$path]['span'] = 'main_content';
		self::$content[$path]['module'] = $m;
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
		static $msgs = '';
		if($msg) $msgs .= $msg.'<br>';
		return $msgs;
	}

	// === BEGIN custom-type eager-load (PHP 8.2 migration / openpsa QuickForm drop-in) ===
	// openpsa throws on unregistered element types. Epesi registers custom types (autoselect,
	// multiselect, commondata, datepicker, currency, ...) lazily inside module Common files, which
	// load too late for dashboard applets. Eager-load those modules once, before rendering.
	// openpsa's stock _loadElement() instantiates the registered value via ReflectionClass — it wants
	// a plain classname string with the class already loaded (no vendor patch, no autoload for Epesi's
	// own classes per CLAUDE.md), NOT the array($file,$class) pair Epesi historically used. So each
	// entry below require_once's its file explicitly and registers the string, matching what stock
	// openpsa expects — see AI-shared/MIGRATION_NOTES.md §12.7/§13/§15.1 for the history (an earlier
	// approach patched the vendor file to accept both formats; that patch is lost on every composer
	// update, so this eager-load function is the permanent, vendor-edit-free fix instead).
	// REVERSIBLE: delete this whole function + its single call site to restore lazy behavior.
	private static function register_custom_qf_types() {
		static $done = false;
		if ($done) return;
		$done = true;
		class_exists('HTML_QuickForm'); // force openpsa autoload first — its QuickForm.php line 17 RESETS the global to built-in types; our custom types must be added AFTER
		$t = &$GLOBALS['HTML_QUICKFORM_ELEMENT_TYPES'];
		$types = array(
			'checkbox'         => array('modules/Libs/QuickForm/FieldTypes/epesi_checkbox/epesi_checkbox.php','HTML_QuickForm_epesi_checkbox'),
			'advcheckbox'      => array('modules/Libs/QuickForm/FieldTypes/epesi_advcheckbox/epesi_advcheckbox.php','HTML_QuickForm_epesi_advcheckbox'),
			'multiselect'      => array('modules/Libs/QuickForm/FieldTypes/multiselect/multiselect.php','HTML_QuickForm_multiselect'),
			'autocomplete'     => array('modules/Libs/QuickForm/FieldTypes/autocomplete/autocomplete.php','HTML_QuickForm_autocomplete'),
			'automulti'        => array('modules/Libs/QuickForm/FieldTypes/automulti/automulti.php','HTML_QuickForm_automulti'),
			'autoselect'       => array('modules/Libs/QuickForm/FieldTypes/autoselect/autoselect.php','HTML_QuickForm_autoselect'),
			'groupselect'      => array('modules/Libs/QuickForm/FieldTypes/groupselect/groupselect.php','HTML_QuickForm_groupselect'),
			'commondata'       => array('modules/Utils/CommonData/qf.php','HTML_QuickForm_commondata'),
			'commondata_group' => array('modules/Utils/CommonData/qf_group.php','HTML_QuickForm_commondata_group'),
			'datepicker'       => array('modules/Utils/PopupCalendar/datepicker.php','HTML_QuickForm_datepicker'),
			'timestamp'        => array('modules/Utils/PopupCalendar/timestamp.php','HTML_QuickForm_timestamp'),
			'currency'         => array('modules/Utils/CurrencyField/currency.php','HTML_QuickForm_currency'),
			'quill'            => array('modules/Libs/Quill/quill.php','HTML_Quickform_quill'),
			'codepress'        => array('modules/Libs/Codepress/HTML_Quickform_codepress_0.php','HTML_Quickform_codepress'),
			'critsvalue'       => array('modules/Utils/QueryBuilder/quickform_crits.php','HTML_QuickForm_crits'),
		);
		foreach ($types as $type => $reg) {
			require_once($reg[0]);
			$t[$type] = $reg[1];
		}
	}
	// === END custom-type eager-load ===

	public static function process($url, $history_call=false,$refresh=false) {
		if(MODULE_TIMES)
			$time = microtime(true);

		$url = str_replace('&amp;','&',$url); //do we need this if we set arg_separator.output to &?

		if($url) {
			$_POST = array();
			parse_str($url, $_POST);
			if (false)
			        $_POST = undoMagicQuotes($_POST);
			$_GET = $_REQUEST = & $_POST;
		}

		ModuleManager::load_modules();
		self::check_firstrun();

		if($history_call==='0')
		    History::clear();
		elseif($history_call)
		    History::set_id($history_call);

		//on init call methods...
		$ret = on_init(null,null,null,true);
		foreach($ret as $k) {
			call_user_func_array($k['func'],$k['args']);
		}

		$root = ModuleManager::create_root();
		self::go($root);

		//go somewhere else?
		$loc = location(null,true);

		//on exit call methods...
		$ret = on_exit(null,null,null,true,$loc===false);
		foreach($ret as $k)
			call_user_func_array($k['func'],$k['args']);

		if($loc!==false) {
			if(isset($_REQUEST['__action_module__']))
				$loc['__action_module__'] = $_REQUEST['__action_module__'];

			//clean up
			foreach(self::$content as $k=>$v)
				unset(self::$content[$k]);

			foreach(self::$jses as $k=>$v)
				if($v[1]) unset(self::$jses[$k]);

			//go
			$loc['__location'] = microtime(true);
			return self::process(http_build_query($loc),false,true);
		}

		$debug = '';
		if(DEBUG && ($debug_diff = @include_once('tools/Diff.php'))) {
			require_once 'tools/Text/Diff/Renderer/inline.php';
			$diff_renderer = new Text_Diff_Renderer_inline();
		}

		//clean up old modules
		if(isset($_SESSION['client']['__module_content__'])) {
			$to_cleanup = array_keys($_SESSION['client']['__module_content__']);
			foreach($to_cleanup as $k) {
				$mod = ModuleManager::get_instance($k);
				if($mod === null) {
					$xx = explode('/',$k);
					$yy = explode('|',$xx[count($xx)-1]);
					$mod = $yy[0];
					if(is_callable(array($mod.'Common','destroy')))
						call_user_func(array($mod.'Common','destroy'),$k,$_SESSION['client']['__module_vars__'][$k] ?? null);
					if(DEBUG)
						$debug .= 'Clearing mod vars & module content '.$k.'<br>';
					unset($_SESSION['client']['__module_vars__'][$k]);
					unset($_SESSION['client']['__module_content__'][$k]);
				}
			}
		}

		$reloaded = array();
		foreach (self::$content as $k => $v) {
			$reload = $v['module']->get_reload();
			$parent = $v['module']->get_parent_path();
			if(DEBUG && REDUCING_TRANSFER) {
				$debug .= '<hr style="height: 3px; background-color:black">';
				$debug .= '<b> Checking '.$k.', &nbsp;&nbsp;&nbsp; parent='.$v['module']->get_parent_path().'</b><ul>'.
					'<li>Force - '.(isset($reload)?print_r($reload,true):'not set').'</li>'.
					'<li>First display - '.(isset ($_SESSION['client']['__module_content__'][$k])?'no</li>'.
					'<li>Content changed - '.(($_SESSION['client']['__module_content__'][$k]['value'] !== $v['value'])?'yes':'no').'</li>'.
					'<li>JS changed - '.(($_SESSION['client']['__module_content__'][$k]['js'] !== $v['js'])?'yes':'no'):'yes').'</li>'.
					'<li>Parent reloaded - '.(isset($reloaded[$parent])?'yes':'no').'</li>'.
					'<li>History call - '.(($history_call)?'yes':'no').'</li>'.
					'</ul>';
			}
			if (!REDUCING_TRANSFER
				 || ((!isset($reload) && (!isset ($_SESSION['client']['__module_content__'][$k])
				 || $_SESSION['client']['__module_content__'][$k]['value'] !== $v['value'] //content differs
				 || $_SESSION['client']['__module_content__'][$k]['js'] !== $v['js']))
				 || $history_call
				 || $reload == true || isset($reloaded[$parent]))) { //force reload or parent reloaded
				if(DEBUG && isset($_SESSION['client']['__module_content__'])){
					$debug .= '<b>Reloading: '.(isset($v['span'])?';&nbsp;&nbsp;&nbsp;&nbsp;span='.$v['span'].',':'').'&nbsp;&nbsp;&nbsp;&nbsp;triggered='.(($reload==true)?'force':'auto').',&nbsp;&nbsp;</b><hr><b>New value:</b><br><pre>'.htmlspecialchars($v['value']).'</pre>'.(isset($_SESSION['client']['__module_content__'][$k]['value'])?'<hr><b>Old value:</b><br><pre>'.htmlspecialchars($_SESSION['client']['__module_content__'][$k]['value']).'</pre>':'');
					if($debug_diff && isset($_SESSION['client']['__module_content__'][$k]['value'])) {
						$xxx = new Text_Diff(explode("\n",$_SESSION['client']['__module_content__'][$k]['value']),explode("\n",$v['value']));
						$debug .= '<hr><b>Diff:</b><br><pre>'.$diff_renderer->render($xxx).'</pre>';
					}
					$debug .= '<hr style="height: 5px; background-color:black">';
				}

				if(isset($v['span']))
					self::text($v['value'], $v['span']);
				if($v['js'])
					self::js(join(";",$v['js']));
				if (REDUCING_TRANSFER) {
					$_SESSION['client']['__module_content__'][$k]['value'] = $v['value'];
					$_SESSION['client']['__module_content__'][$k]['js'] = $v['js'];
				}
				$_SESSION['client']['__module_content__'][$k]['parent'] = $parent;
				$reloaded[$k] = true;
				if(method_exists($v['module'],'reloaded')) $v['module']->reloaded();
			}
		}

		foreach($_SESSION['client']['__module_content__'] as $k=>$v)
			if(!array_key_exists($k,self::$content) && isset($reloaded[$v['parent']])) {
				if(DEBUG)
					$debug .= 'Reloading missing '.$k.'<hr>';
				if(isset($v['span']))
					self::text($v['value'], $v['span']);
				if(isset($v['js']) && $v['js'])
					self::js(join(";",$v['js']));
				$reloaded[$k] = true;
			}

		if(DEBUG) {
			$debug .= 'vars '.(defined('CID') ? CID : false).': '.print_r($_SESSION['client']['__module_vars__'],true).'<br>';
			$debug .= 'user='.Base_AclCommon::get_user().'<br>';
			if(isset($_REQUEST['__action_module__']))
				$debug .= 'action module='.$_REQUEST['__action_module__'].'<br>';
		}
		$debug .= self::debug();

		if(MODULE_TIMES) {
			foreach (self::$content as $k => $v) {
				$style='color:red;font-weight:bold';
				if ($v['time']<0.5) $style = 'color:orange;font-weight:bold';
				if ($v['time']<0.05) $style = 'color:green;font-weight:bold';
				$debug .= 'Time of loading module <b>'.$k.'</b>: <i>'.'<span style="'.$style.';">'.number_format($v['time'],4).'</span>'.'</i><br>';
			}
			$debug .= 'Page renderered in '.(microtime(true)-$time).'s<hr>';
		}

		if(SQL_TIMES) {
			$debug .= '<font size="+1">QUERIES</font><br>';
			$queries = DB::GetQueries();
			$sum = 0;
			$qty = 0;
			foreach($queries as $kk=>$q) {
				$style='color:red;font-weight:bold';
				if ($q['time']<0.5) $style = 'color:orange;font-weight:bold';
				if ($q['time']<0.05) $style = 'color:green';
				for($kkk=0; $kkk<$kk; $kkk++)
					if($queries[$kkk]['args']==$q['args']) {
						$style .= ';text-decoration:underline';
					}
				$debug .= '<span style="'.$style.';">'.'<b>'.$q['func'].'</b> '.htmlspecialchars(var_export($q['args'],true)).' <i><b>'.number_format($q['time'],4).'</b></i>' . (isset($q['caller'])?', '.$q['caller']:'') . '<br>'.'</span>';
				$sum+=$q['time'];
				$qty++;
			}
			$debug .= '<b>Number of queries:</b> '.$qty.'<br>';
			$debug .= '<b>Queries times:</b> '.$sum.'<br>';
		}
		if(!isset($_SESSION['client']['custom_debug']) || $debug!=$_SESSION['client']['custom_debug']) {
			self::text($debug,'debug');
			if ($debug) Epesi::js("document.getElementById('debug_content').style.display='block';");
			$_SESSION['client']['custom_debug'] = $debug;
		}

		if(!$history_call && !History::soft_call()) {
		        History::set();
		}

		if(!$history_call) {
			self::js('Epesi.history_add('.History::get_id().')');
		}

		self::send_output();
	}
}
